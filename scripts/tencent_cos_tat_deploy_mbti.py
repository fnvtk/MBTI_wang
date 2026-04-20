#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
MBTI 王 → kr 宝塔：本机 npm build → 打 tar（api + admin/dist）→ 腾讯云 COS → TAT 在 CVM 内拉取解压同步。

不依赖本机 SSH/rsync、不依赖宝塔面板 API（避免出口 IP 白名单与封禁）。

前置：
  - pip install cos-python-sdk-v5 tencentcloud-sdk-python-tat
  - 凭证：TENCENTCLOUD_SECRET_ID / TENCENTCLOUD_SECRET_KEY，或卡若AI
    「运营中枢/工作台/00_账号与API索引.md」§ 腾讯云（SecretId / SecretKey 行内 `...`）
  - COS：MBTI_COS_BUCKET（或 YINZHANGUI_COS_BUCKET），未设则 ListBuckets 取首个桶

用法：
  python3 scripts/tencent_cos_tat_deploy_mbti.py
  python3 scripts/tencent_cos_tat_deploy_mbti.py --dry-run
  python3 scripts/tencent_cos_tat_deploy_mbti.py --tat-url 'https://...'   # 仅下发 TAT
"""
from __future__ import annotations

import argparse
import base64
import json
import os
import re
import subprocess
import sys
import tempfile
import time
from pathlib import Path

KR_INSTANCE_ID = os.environ.get("MBTI_CVM_INSTANCE_ID", "ins-aw0tnqjo")
REGION = os.environ.get("MBTI_COS_REGION", "ap-guangzhou")
DEST_API_PARENT = "/www/wwwroot/self/mbti-api"
DEST_ADMIN = "/www/wwwroot/self/mbti-admin"

KARUO_IDX_DEFAULT = Path("/Users/karuo/Documents/个人/卡若AI/运营中枢/工作台/00_账号与API索引.md")


def repo_root() -> Path:
    return Path(__file__).resolve().parents[1]


def _read_tencent_creds() -> tuple[str | None, str | None]:
    sid = os.environ.get("TENCENTCLOUD_SECRET_ID")
    skey = os.environ.get("TENCENTCLOUD_SECRET_KEY")
    if sid and skey:
        return sid, skey
    idx = Path(os.environ.get("KARUO_API_INDEX_MD", str(KARUO_IDX_DEFAULT)))
    if not idx.is_file():
        return None, None
    text = idx.read_text(encoding="utf-8")
    in_tx = False
    sid = skey = None
    for line in text.splitlines():
        if "### 腾讯云" in line:
            in_tx = True
            continue
        if in_tx and line.strip().startswith("###"):
            break
        if not in_tx:
            continue
        if "SecretId" in line and "`" in line:
            m = re.search(r"`([^`]+)`", line)
            if m and m.group(1).strip().startswith("AKID"):
                sid = m.group(1).strip()
        if "SecretKey" in line and "`" in line:
            m = re.search(r"`([^`]+)`", line)
            if m:
                skey = m.group(1).strip()
    return sid, skey


def _npm_build_admin(root: Path) -> None:
    admin = root / "admin"
    if not admin.is_dir():
        raise SystemExit(f"缺少 admin/: {admin}")
    r = subprocess.run(
        ["npm", "run", "build"],
        cwd=str(admin),
        capture_output=True,
        text=True,
    )
    if r.returncode != 0:
        raise SystemExit(f"npm run build 失败:\n{r.stderr or r.stdout}")


def _make_tarball(root: Path) -> Path:
    api = root / "api"
    dist = root / "admin" / "dist"
    if not api.is_dir():
        raise SystemExit(f"缺少 api/: {api}")
    if not dist.is_dir():
        raise SystemExit(f"缺少 admin/dist，请先构建: {dist}")
    tmp = Path(tempfile.gettempdir()) / f"mbti_cos_{int(time.time())}.tar.gz"
    cmd = [
        "tar",
        "-czf",
        str(tmp),
        "--exclude=.git",
        "--exclude=__pycache__",
        "--exclude=.DS_Store",
        "--exclude=api/runtime",
        "-C",
        str(root),
        "api",
        "-C",
        str(root / "admin"),
        "dist",
    ]
    r = subprocess.run(cmd, capture_output=True, text=True)
    if r.returncode != 0:
        raise SystemExit(f"tar 失败: {r.stderr or r.stdout}")
    return tmp


def _pick_bucket(sid: str, skey: str) -> str:
    b = (os.environ.get("MBTI_COS_BUCKET") or os.environ.get("YINZHANGUI_COS_BUCKET", "")).strip()
    if b:
        return b
    try:
        from qcloud_cos import CosConfig, CosS3Client
    except ImportError:
        raise SystemExit("请设置 MBTI_COS_BUCKET 并 pip install cos-python-sdk-v5")
    cfg = CosConfig(Region=REGION, SecretId=sid, SecretKey=skey, Scheme="https")
    svc = CosS3Client(cfg)
    resp = svc.list_buckets()
    raw = (resp or {}).get("Buckets") or {}
    buckets = raw.get("Bucket") or []
    if isinstance(buckets, dict):
        buckets = [buckets]
    if not buckets:
        raise SystemExit("账号下无 COS 桶，请设置 MBTI_COS_BUCKET=桶名-APPID")
    pref = [x for x in buckets if "wordpress-serverless" not in (x.get("Name") or "")]
    if pref:
        buckets = pref
    name = buckets[0].get("Name")
    if not name:
        raise SystemExit("ListBuckets 返回异常")
    print(f"  未指定 MBTI_COS_BUCKET，自动使用: {name}")
    return name


def _upload_cos(local_path: Path, bucket: str, sid: str, skey: str) -> tuple[str, str]:
    from qcloud_cos import CosConfig, CosS3Client

    key = f"deploy/mbti_wang/{int(time.time())}_{local_path.name}"
    cfg = CosConfig(Region=REGION, SecretId=sid, SecretKey=skey, Scheme="https")
    client = CosS3Client(cfg)
    with open(local_path, "rb") as f:
        client.put_object(Bucket=bucket, Body=f, Key=key, EnableMD5=False)
    url = client.get_presigned_url(Method="GET", Bucket=bucket, Key=key, Expired=3600)
    return key, url


def _tat_shell(presigned_url: str) -> str:
    url_b64 = base64.b64encode(presigned_url.encode()).decode()
    d_api = DEST_API_PARENT
    d_adm = DEST_ADMIN
    return f"""#!/bin/bash
set -euo pipefail
B64="{url_b64}"
URL="$(echo "$B64" | base64 -d)"
TMP=/tmp/mbti_cos_deploy.tgz
WORKDIR=/tmp/mbti_cos_deploy_$$
echo "=== MBTI 王 COS+TAT 部署 ==="
curl -fsSL "$URL" -o "$TMP"
mkdir -p "$WORKDIR"
tar xzf "$TMP" -C "$WORKDIR"
rm -f "$TMP"

API_DST="{d_api}/api"
ADM_DST="{d_adm}"
if [ -f "$API_DST/.env" ]; then cp -a "$API_DST/.env" /tmp/mbti_api_env.bak; fi

mkdir -p "$API_DST" "$ADM_DST"
# 包内顶层为 api/ 与 dist/
cp -a "$WORKDIR/api/." "$API_DST/"
# 静态站：清空旧资源（保留 .user.ini）
find "$ADM_DST" -mindepth 1 -maxdepth 1 ! -name '.user.ini' -exec rm -rf {{}} +
cp -a "$WORKDIR/dist/." "$ADM_DST/"

if [ -f /tmp/mbti_api_env.bak ]; then mv -f /tmp/mbti_api_env.bak "$API_DST/.env"; fi
chown -R www:www "$API_DST" "$ADM_DST" || true

/www/server/nginx/sbin/nginx -t && /www/server/nginx/sbin/nginx -s reload || true
systemctl reload php-fpm-82 2>/dev/null || systemctl reload php-fpm-81 2>/dev/null || systemctl reload php-fpm-80 2>/dev/null || true
rm -rf "$WORKDIR"
echo "=== 完成 ==="
"""


def _decode_tat_task_text(task) -> tuple[str, str]:
    """从 InvocationTask 解析终端输出文本，返回 (TaskStatus, 解码后的 Output)。"""
    st = getattr(task, "TaskStatus", "") or ""
    tr = getattr(task, "TaskResult", None)
    if not tr:
        op = getattr(task, "Output", None)
        if op:
            return st, str(op)[:12000]
        return st, ""

    try:
        jj = json.loads(tr) if isinstance(tr, str) else tr
    except Exception:
        return st, str(tr)[:4000]

    exit_code = jj.get("ExitCode")
    raw = jj.get("Output", "") or ""
    if raw:
        try:
            raw = base64.b64decode(raw).decode("utf-8", errors="replace")
        except Exception:
            pass
    if exit_code is not None:
        raw = f"ExitCode: {exit_code}\n{raw}"
    return st, raw[:12000]


def _run_tat(shell_text: str, timeout: int = 600) -> str:
    try:
        from tencentcloud.common import credential
        from tencentcloud.tat.v20201028 import models, tat_client
    except ImportError:
        raise SystemExit("请安装: pip install tencentcloud-sdk-python-tat")

    sid, skey = _read_tencent_creds()
    if not sid or not skey:
        raise SystemExit("未配置腾讯云 SecretId/SecretKey")

    cred = credential.Credential(sid, skey)
    client = tat_client.TatClient(cred, REGION)
    req = models.RunCommandRequest()
    req.Content = base64.b64encode(shell_text.encode()).decode()
    req.InstanceIds = [KR_INSTANCE_ID]
    req.CommandType = "SHELL"
    req.Timeout = timeout
    req.CommandName = "mbti_wang_cos_deploy"
    resp = client.RunCommand(req)
    inv = resp.InvocationId
    print(f"✅ TAT 已下发 InvocationId={inv}，等待回传（首轮 60s）…")
    time.sleep(60)

    req2 = models.DescribeInvocationTasksRequest()
    flt = models.Filter()
    flt.Name = "invocation-id"
    flt.Values = [inv]
    req2.Filters = [flt]

    chunks: list[str] = []
    last_text = ""
    terminal = ("SUCCESS", "FAILED", "TIMEOUT", "CANCELLED")
    for i in range(48):
        r2 = client.DescribeInvocationTasks(req2)
        tasks = r2.InvocationTaskSet or []
        done = bool(
            tasks
            and all(getattr(x, "TaskStatus", "") in terminal for x in tasks)
        )
        for t in tasks:
            st, text = _decode_tat_task_text(t)
            if text and text != last_text:
                chunks.append(f"--- 状态: {st} ---\n{text}")
                last_text = text
            elif done and not text and st:
                chunks.append(f"--- 状态: {st} ---\n(无标准输出)")

        if done:
            break
        time.sleep(10)

    return "\n".join(chunks) if chunks else "(无输出，请到腾讯云控制台 TAT 查看)"


def main() -> int:
    ap = argparse.ArgumentParser(description="MBTI 王：COS + TAT 部署到 kr")
    ap.add_argument("--dry-run", action="store_true")
    ap.add_argument("--tat-url", default="", help="已有可下载 URL 时跳过打包与上传")
    ap.add_argument("--skip-build", action="store_true", help="跳过 npm run build（需已有 admin/dist）")
    args = ap.parse_args()

    root = repo_root()
    print("=" * 60)
    print("  MBTI 王 · 腾讯云 COS + TAT 部署")
    print("=" * 60)
    print(f"  仓库: {root}")
    print(f"  CVM:  {KR_INSTANCE_ID} / {REGION}")
    print(f"  API:  {DEST_API_PARENT}/api")
    print(f"  站点: {DEST_ADMIN}")
    print("=" * 60)

    sid, skey = _read_tencent_creds()
    if not sid or not skey:
        print("❌ 未找到腾讯云凭证")
        return 1

    presigned = args.tat_url.strip()

    if not presigned:
        if not args.skip_build:
            print("  执行 npm run build …")
            _npm_build_admin(root)
        tar_path = _make_tarball(root)
        print(f"  本地包: {tar_path} ({tar_path.stat().st_size // 1024} KB)")
        if args.dry_run:
            print("  --dry-run 结束")
            return 0
        bucket = _pick_bucket(sid, skey)
        _, presigned = _upload_cos(tar_path, bucket, sid, skey)
        try:
            tar_path.unlink(missing_ok=True)
        except OSError:
            pass
        shell = _tat_shell(presigned)
    else:
        if args.dry_run:
            print("  --dry-run：已有 URL，跳过")
            return 0
        shell = _tat_shell(presigned)

    print(_run_tat(shell, timeout=600))
    print("\n  验证:")
    print("    curl -sI https://mbti.quwanzhi.com/ | head -3")
    print("    curl -sI https://mbtiadmin.quwanzhi.com/ | head -3")
    print("    curl -s -o /dev/null -w '%{http_code}\\n' https://mbtiapi.quwanzhi.com/api/v1/admin/app-users")
    return 0


if __name__ == "__main__":
    sys.exit(main())
