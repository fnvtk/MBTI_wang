#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
MBTI 王：通过 kr 宝塔面板 API 上传 PHP 与 admin 静态资源并触发表层服务重载。

鉴权：环境变量 BT_PANEL_URL（默认 https://43.139.27.93:9988）、BT_API_KEY（面板「设置 → API 接口」密钥）。
本机出口 IP 须加入面板 API 白名单。若报 IP 校验失败，可用腾讯云 TAT 在机内追加白名单（无需先加白）：
  卡若AI/…/服务器管理/scripts/腾讯云_TAT_kr宝塔_API白名单_追加出口IP.py
  可加 --ip 与宝塔报错括号内 IPv4 一致。

用法：
  BT_API_KEY=xxx python3 scripts/deploy_mbti_bt_api.py --list-sites
  BT_API_KEY=xxx python3 scripts/deploy_mbti_bt_api.py --all
  BT_API_KEY=xxx python3 scripts/deploy_mbti_bt_api.py --api-only
  BT_API_KEY=xxx python3 scripts/deploy_mbti_bt_api.py --admin-only

  也可将密钥写入 scripts/.env.bt（见 scripts/env.bt.example），勿提交；脚本启动时会自动加载。

可选覆盖（自动识别失败时）：
  MBTI_API_CODE_ROOT   服务器上与本地 api/ 同级的目录（含 app/、public/），例：/www/wwwroot/self/mbti-api/api
  MBTI_ADMIN_SITE_ROOT 静态站点根（含 index.html），例：/www/wwwroot/self/mbti-admin

PHP-FPM 重载（版本因机而异，不填则跳过）：
  BT_PHP_FPM_SERVICE=php-fpm-82
"""
from __future__ import annotations

import argparse
import hashlib
import json
import os
import ssl
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

ssl._create_default_https_context = ssl._create_unverified_context

REPO_ROOT = Path(__file__).resolve().parents[1]
API_LOCAL = REPO_ROOT / "api"
ADMIN_DIST = REPO_ROOT / "admin" / "dist"


def load_env_bt() -> None:
    """从 scripts/.env.bt 注入环境变量（仅当当前环境未设置同名变量时）。"""
    p = REPO_ROOT / "scripts" / ".env.bt"
    if not p.is_file():
        return
    try:
        raw_text = p.read_text(encoding="utf-8")
    except OSError:
        return
    for raw in raw_text.splitlines():
        line = raw.strip()
        if not line or line.startswith("#"):
            continue
        if line.startswith("export "):
            line = line[7:].strip()
        if "=" not in line:
            continue
        key, _, val = line.partition("=")
        key = key.strip()
        val = val.strip().strip("'").strip('"')
        # 跳过空值，避免 .env.bt 里 BT_API_KEY= 占位导致覆盖不了终端里已 export 的密钥
        if key and val and key not in os.environ:
            os.environ[key] = val

DEFAULT_API_REL_PATHS = [
    "app/controller/admin/AppUser.php",
    "app/controller/admin/Order.php",
    "app/controller/admin/Dashboard.php",
    "app/controller/admin/Finance.php",
    "app/controller/admin/concern/ExtractsTestResults.php",
    "app/model/WechatUser.php",
    # 路由与小程序核心 API
    "route/api.php",
    "app/controller/api/Auth.php",
    "app/controller/api/AppConfig.php",
    "app/controller/api/MpConfig.php",
    "app/controller/api/Order.php",
    # 神仙 AI 异步投递（triggerAiChatDeferredJob → /api/internal/outbound-push/dispatch）
    "app/controller/api/InternalPushHook.php",
    # 神仙 AI（对话 + 报告）
    "app/controller/api/AiChat.php",
    "app/controller/api/AiReport.php",
    "app/controller/api/Analyze.php",
    "app/controller/api/Test.php",
    "app/common/service/AiCallService.php",
    "app/common/service/AiChatArticleDisplayService.php",
    "app/common/service/SoulArticleService.php",
    "app/common/service/AiReportService.php",
    # 神仙 AI 依赖模型（避免线上仍为旧版表映射）
    "app/model/AiConversation.php",
    "app/model/AiMessage.php",
    "app/model/AiProvider.php",
    "app/model/SoulArticle.php",
    "app/model/SystemConfig.php",
    # 微信支付 / 转账回调
    "app/controller/api/Payment.php",
    "app/controller/api/WechatTransferNotify.php",
    "app/common/service/WechatService.php",
    "app/common/service/WechatAuditSyncService.php",
    "app/common/service/WechatTransferService.php",
    "app/controller/superadmin/Settings.php",
    # 认证、配置、埋点/推送依赖
    "app/common/service/JwtService.php",
    "app/common/service/MpTabbarService.php",
    "app/common/service/FeishuLeadWebhookService.php",
    "app/common/service/OutboundPushHookService.php",
    "app/common/service/ThirdPartyChannelService.php",
    # 上线自检 / 冒烟（可选；上传至服务器 api/scripts 供 SSH 执行）
    "scripts/check_ai_chat_ready.php",
    "scripts/smoke_ai_provider.php",
]

API_DOMAIN_HINT = "mbtiapi"
# 面板上可能是 mbtiadmin / mbti.quwanzhi.com 等
ADMIN_DOMAIN_HINTS = ("mbtiadmin", "mbti.quwanzhi", "mbti-admin")


def panel_url() -> str:
    return os.environ.get("BT_PANEL_URL", "https://43.139.27.93:9988").rstrip("/")


def api_key() -> str:
    k = os.environ.get("BT_API_KEY") or os.environ.get("MBTI_BT_API_KEY") or ""
    return k.strip()


def sign(key: str) -> dict:
    t = int(time.time())
    s = str(t) + hashlib.md5(key.encode("utf-8")).hexdigest()
    return {"request_time": t, "request_token": hashlib.md5(s.encode("utf-8")).hexdigest()}


def post(endpoint: str, data: dict | None, key: str, timeout: int = 120) -> dict:
    url = panel_url() + endpoint
    payload = sign(key)
    if data:
        payload.update(data)
    body = urllib.parse.urlencode(payload).encode()
    req = urllib.request.Request(url, data=body, method="POST")
    try:
        with urllib.request.urlopen(req, timeout=timeout) as resp:
            raw = resp.read().decode("utf-8", errors="replace")
            return json.loads(raw)
    except urllib.error.HTTPError as e:
        try:
            raw = e.read().decode("utf-8", errors="replace")
            return {"status": False, "msg": "HTTP %s: %s" % (e.code, raw[:500])}
        except Exception:
            return {"status": False, "msg": "HTTP %s" % e.code}
    except Exception as e:
        return {"status": False, "msg": str(e)}


def get_sites(key: str, limit: int = 500) -> list:
    r = post("/data?action=getData", {"table": "sites", "limit": str(limit), "p": "1"}, key)
    data = r.get("data")
    return data if isinstance(data, list) else []


def find_site_path(sites: list, hint: str) -> str | None:
    hint_l = hint.lower()
    for s in sites:
        name = str(s.get("name", "")).lower()
        if hint_l in name:
            p = s.get("path")
            if p:
                return str(p).rstrip("/")
    return None


def find_admin_site_path(sites: list) -> str | None:
    for h in ADMIN_DOMAIN_HINTS:
        p = find_site_path(sites, h)
        if p:
            return p
    return None


def resolve_api_code_root(site_path: str | None) -> str | None:
    """服务器上对应本地仓库 api/ 的目录（内含 app、public）。"""
    override = os.environ.get("MBTI_API_CODE_ROOT", "").strip()
    if override:
        return override.rstrip("/")
    if not site_path:
        return None
    p = Path(site_path.rstrip("/"))
    if p.name.lower() == "public":
        return str(p.parent)
    # 面板网站目录已指向 .../mbti-api/api 时，basename 为 api，即代码根
    if p.name.lower() == "api":
        return str(p)
    # 站点根为仓库根（如 .../mbti-api）时，代码在子目录 api/
    return str(p / "api")


def ensure_remote_dir(remote_dir: str, key: str) -> None:
    r = post("/files?action=CreateDir", {"path": remote_dir}, key)
    if r.get("status") is not True and "已存在" not in str(r.get("msg", "")):
        pass


def save_remote_file(remote_path: str, text: str, key: str) -> dict:
    r = post(
        "/files?action=SaveFileBody",
        {"path": remote_path, "data": text, "encoding": "utf-8"},
        key,
        timeout=180,
    )
    if r.get("status") is True:
        return r
    msg = str(r.get("msg", ""))
    # 宝塔对新文件需要先 CreateFile，再 SaveFileBody
    if "不存在" in msg or "not exist" in msg.lower():
        cf = post("/files?action=CreateFile", {"path": remote_path}, key)
        if cf.get("status") is True or "已存在" in str(cf.get("msg", "")):
            return post(
                "/files?action=SaveFileBody",
                {"path": remote_path, "data": text, "encoding": "utf-8"},
                key,
                timeout=180,
            )
    return r


def read_text_local(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def deploy_api_files(api_root_remote: str, key: str, rel_paths: list[str]) -> int:
    base = Path(api_root_remote.rstrip("/"))
    errs = 0
    for rel in rel_paths:
        local = API_LOCAL / rel
        if not local.is_file():
            print("  跳过（本地不存在）:", local)
            continue
        remote = str(base / rel).replace("\\", "/")
        text = read_text_local(local)
        r = save_remote_file(remote, text, key)
        if r.get("status") is True:
            print("  OK", remote)
        else:
            print("  FAIL", remote, r.get("msg", r))
            errs += 1
    return errs


def deploy_admin_dist(admin_root_remote: str, key: str) -> int:
    if not ADMIN_DIST.is_dir():
        print("  本地无 admin/dist，请先: cd admin && npm run build")
        return 1
    root = admin_root_remote.rstrip("/")
    errs = 0
    for dirpath, _dirnames, filenames in os.walk(ADMIN_DIST):
        rel = os.path.relpath(dirpath, ADMIN_DIST)
        if rel == ".":
            remote_dir = root
        else:
            remote_dir = root + "/" + rel.replace("\\", "/")
        ensure_remote_dir(remote_dir, key)
        for name in filenames:
            lp = Path(dirpath) / name
            remote_path = remote_dir.rstrip("/") + "/" + name
            try:
                text = read_text_local(lp)
            except UnicodeDecodeError:
                print("  非 UTF-8 跳过:", lp)
                errs += 1
                continue
            r = save_remote_file(remote_path, text, key)
            if r.get("status") is True:
                print("  OK", remote_path)
            else:
                print("  FAIL", remote_path, r.get("msg", r))
                errs += 1
    return errs


def service_admin(name: str, op: str, key: str) -> None:
    r = post("/system?action=ServiceAdmin", {"name": name, "type": op}, key)
    print("  ServiceAdmin %s %s -> %s" % (name, op, r.get("msg", r.get("status", r))))


def main() -> int:
    load_env_bt()
    ap = argparse.ArgumentParser(description="MBTI 宝塔 API 部署")
    ap.add_argument("--list-sites", action="store_true", help="列出站点并匹配 mbti 路径后退出")
    ap.add_argument("--all", action="store_true", help="上传 API 默认文件 + admin/dist")
    ap.add_argument("--api-only", action="store_true")
    ap.add_argument("--admin-only", action="store_true")
    ap.add_argument("--dry-run", action="store_true", help="只打印将上传的路径，不请求面板")
    args = ap.parse_args()

    key = api_key()
    if args.list_sites and not key:
        print("请设置环境变量 BT_API_KEY（或 MBTI_BT_API_KEY）")
        return 1
    if not key and not args.dry_run:
        print("请设置环境变量 BT_API_KEY（或 MBTI_BT_API_KEY）")
        return 1

    sites: list = []
    if key and not args.dry_run:
        ping = post("/system?action=GetSystemTotal", {}, key)
        if ping.get("cpuRealUsed") is None:
            print("面板连接失败:", ping)
            return 1
        sites = get_sites(key)

    admin_override = os.environ.get("MBTI_ADMIN_SITE_ROOT", "").strip()

    api_site_path = find_site_path(sites, API_DOMAIN_HINT)
    admin_site_path = admin_override or find_admin_site_path(sites)

    if args.list_sites:
        print("面板:", panel_url())
        for s in sites:
            n = s.get("name", "")
            if "mbti" in str(n).lower():
                print(" ", n, "->", s.get("path"))
        print(
            "解析 API：面板站点 path -> 代码根（本地 api/ 对应）:",
            api_site_path,
            "->",
            resolve_api_code_root(api_site_path),
        )
        print("解析 Admin 根:", admin_site_path)
        return 0

    if not args.all and not args.api_only and not args.admin_only:
        print("请指定 --all / --api-only / --admin-only 或 --list-sites")
        return 1

    api_code_root = resolve_api_code_root(api_site_path)
    if not api_code_root:
        print("未解析到 API 代码根。请 --list-sites 核对，或设置 MBTI_API_CODE_ROOT")
        return 1
    if (args.all or args.admin_only) and not admin_site_path:
        print("未找到 mbtiadmin 站点路径。请 --list-sites 核对，或设置 MBTI_ADMIN_SITE_ROOT")
        return 1

    if args.dry_run:
        print("API 代码根:", api_code_root)
        for rel in DEFAULT_API_REL_PATHS:
            print("  将写:", api_code_root + "/" + rel)
        if args.all or args.admin_only:
            print("Admin 根:", admin_site_path)
        return 0

    err = 0
    if args.all or args.api_only:
        print("[API] 上传到", api_code_root)
        err += deploy_api_files(api_code_root, key, DEFAULT_API_REL_PATHS)

    if args.all or args.admin_only:
        print("[Admin] 上传到", admin_site_path)
        err += deploy_admin_dist(admin_site_path, key)

    php_svc = os.environ.get("BT_PHP_FPM_SERVICE", "").strip()
    if php_svc:
        print("[重载 PHP-FPM]", php_svc)
        service_admin(php_svc, "reload", key)
    print("[重载 Nginx]")
    service_admin("nginx", "reload", key)

    if err:
        print("完成，但有 %s 个文件失败" % err)
        return 1
    print("完成。")
    return 0


if __name__ == "__main__":
    sys.exit(main())
