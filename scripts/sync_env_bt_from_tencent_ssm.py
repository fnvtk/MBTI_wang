#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
用腾讯云 API 从「凭据管理系统 Secrets Manager / SSM」拉取宝塔相关配置，写入 scripts/.env.bt
（宝塔 BT_API_KEY 只能在面板里生成，腾讯云不会替你生成；需你先在控制台把密钥存成一条凭据。）

凭据内容（自定义 SecretString，推荐 JSON）示例：
  {
    "BT_API_KEY": "面板 设置→API接口 的密钥",
    "BT_PANEL_URL": "https://你的面板:端口",
    "MBTI_API_CODE_ROOT": "/www/wwwroot/xxx/mbti-api/api",
    "MBTI_ADMIN_SITE_ROOT": "/www/wwwroot/xxx/mbti-admin",
    "BT_PHP_FPM_SERVICE": "php-fpm-82"
  }

也可存纯文本：整段 SecretString 仅一行，则视为 BT_API_KEY。

腾讯云 CAM 凭证（与 COS/TAT 脚本相同）：
  - 环境变量 TENCENTCLOUD_SECRET_ID / TENCENTCLOUD_SECRET_KEY
  - 或 卡若AI「运营中枢/工作台/00_账号与API索引.md」§ 腾讯云 内 SecretId / SecretKey

本脚本依赖：
  python3 -m venv .venv-bt && .venv-bt/bin/pip install -r scripts/requirements_bt_tencent.txt

环境变量：
  MBTI_BT_SECRET_NAME   必填，凭据名称
  MBTI_BT_SSM_REGION    可选，默认 ap-guangzhou
  MBTI_BT_SECRET_VERSION 可选，默认 SSM_Current

用法：
  MBTI_BT_SECRET_NAME=mbti-baota .venv-bt/bin/python scripts/sync_env_bt_from_tencent_ssm.py
  MBTI_BT_SECRET_NAME=mbti-baota .venv-bt/bin/python scripts/sync_env_bt_from_tencent_ssm.py --deploy-api
"""
from __future__ import annotations

import argparse
import json
import os
import re
import subprocess
import sys
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[1]
ENV_BT_PATH = REPO_ROOT / "scripts" / ".env.bt"
KARUO_IDX_DEFAULT = Path("/Users/karuo/Documents/个人/卡若AI/运营中枢/工作台/00_账号与API索引.md")


def read_tencent_creds() -> tuple[str | None, str | None]:
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


def fetch_secret_string(secret_name: str, version_id: str, region: str, sid: str, skey: str) -> str:
    try:
        from tencentcloud.common import credential
        from tencentcloud.common.exception.tencent_cloud_sdk_exception import TencentCloudSDKException
        from tencentcloud.ssm.v20190923 import models, ssm_client
    except ImportError as e:
        raise SystemExit(
            "缺少腾讯云 SDK。请执行：\n"
            "  cd \"%s\"\n"
            "  python3 -m venv .venv-bt\n"
            "  .venv-bt/bin/pip install -r scripts/requirements_bt_tencent.txt\n"
            "原错误: %s" % (REPO_ROOT, e)
        ) from e

    cred = credential.Credential(sid, skey)
    client = ssm_client.SsmClient(cred, region)
    req = models.GetSecretValueRequest()
    req.SecretName = secret_name
    req.VersionId = version_id
    try:
        resp = client.GetSecretValue(req)
    except TencentCloudSDKException as err:
        raise SystemExit("GetSecretValue 失败: %s" % err) from err
    s = (resp.SecretString or "").strip()
    if not s:
        raise SystemExit("凭据明文为空（请检查凭据类型与版本）")
    return s


def secret_to_env_lines(secret_raw: str) -> list[str]:
    lines: list[str] = []
    secret_raw = secret_raw.strip()
    if not secret_raw:
        return lines
    if secret_raw.startswith("{") and secret_raw.endswith("}"):
        try:
            obj = json.loads(secret_raw)
        except json.JSONDecodeError as e:
            raise SystemExit("SecretString 不是合法 JSON: %s" % e) from e
        if not isinstance(obj, dict):
            raise SystemExit("SecretString JSON 须为对象")
        order = [
            "BT_API_KEY",
            "MBTI_BT_API_KEY",
            "BT_PANEL_URL",
            "MBTI_API_CODE_ROOT",
            "MBTI_ADMIN_SITE_ROOT",
            "BT_PHP_FPM_SERVICE",
        ]
        for k in order:
            if k in obj and obj[k] is not None and str(obj[k]).strip() != "":
                lines.append("%s=%s" % (k, str(obj[k]).strip()))
        for k, v in sorted(obj.items()):
            if k in order:
                continue
            if v is None or str(v).strip() == "":
                continue
            lines.append("%s=%s" % (k, str(v).strip()))
        return lines
    # 纯文本 → 仅宝塔密钥
    return ["BT_API_KEY=%s" % secret_raw]


def main() -> int:
    ap = argparse.ArgumentParser(description="从腾讯云 SSM 同步 scripts/.env.bt")
    ap.add_argument(
        "--deploy-api",
        action="store_true",
        help="写入后执行 scripts/deploy_mbti_bt_api.py --api-only",
    )
    ap.add_argument(
        "--print-only",
        action="store_true",
        help="只打印将写入的内容，不写文件",
    )
    args = ap.parse_args()

    name = (os.environ.get("MBTI_BT_SECRET_NAME") or "").strip()
    if not name:
        raise SystemExit("请设置环境变量 MBTI_BT_SECRET_NAME（腾讯云凭据名称）")

    region = (os.environ.get("MBTI_BT_SSM_REGION") or "ap-guangzhou").strip()
    version = (os.environ.get("MBTI_BT_SECRET_VERSION") or "SSM_Current").strip()

    sid, skey = read_tencent_creds()
    if not sid or not skey:
        raise SystemExit(
            "未找到腾讯云 CAM 凭证。请设置 TENCENTCLOUD_SECRET_ID / TENCENTCLOUD_SECRET_KEY，\n"
            "或配置 KARUO_API_INDEX_MD 指向含「### 腾讯云」与 SecretId/SecretKey 的索引文件。"
        )

    raw = fetch_secret_string(name, version, region, sid, skey)
    env_lines = secret_to_env_lines(raw)
    if not any(x.startswith("BT_API_KEY=") or x.startswith("MBTI_BT_API_KEY=") for x in env_lines):
        raise SystemExit("凭据中未解析出 BT_API_KEY，请检查 JSON 字段名或改用纯文本凭据。")

    header = (
        "# 由 sync_env_bt_from_tencent_ssm.py 生成，勿提交。来源凭据: %s (%s)\n" % (name, region)
    )
    body = header + "\n".join(env_lines) + "\n"

    if args.print_only:
        print(body)
        return 0

    ENV_BT_PATH.parent.mkdir(parents=True, exist_ok=True)
    ENV_BT_PATH.write_text(body, encoding="utf-8")
    print("已写入:", ENV_BT_PATH)

    if args.deploy_api:
        deploy = REPO_ROOT / "scripts" / "deploy_mbti_bt_api.py"
        r = subprocess.run([sys.executable, str(deploy), "--api-only"], cwd=str(REPO_ROOT))
        return int(r.returncode)

    print("下一步: python3 scripts/deploy_mbti_bt_api.py --api-only")
    return 0


if __name__ == "__main__":
    sys.exit(main())
