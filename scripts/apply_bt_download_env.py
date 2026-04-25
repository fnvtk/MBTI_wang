#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
将 kr 宝塔（或任意面板）下载的环境文件写入 api/.env，并可选追加本机覆盖层。

用法:
  python3 scripts/apply_bt_download_env.py ~/Downloads/mbti_api.env

可选: 在 api 目录放置 .env.local.merge（勿提交，已加入 .gitignore），
      脚本会在主内容后追加，便于覆盖宝塔里写死的 127.0.0.1 数据库等为本地隧道端口等。
"""
from __future__ import annotations

import argparse
import shutil
import sys
import time
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
API_ENV = ROOT / "api" / ".env"
MERGE = ROOT / "api" / ".env.local.merge"


def main() -> int:
    p = argparse.ArgumentParser(description="合并宝塔下载的 .env 到 api/.env")
    p.add_argument("source", help="从宝塔下载的环境文件路径")
    args = p.parse_args()
    src = Path(args.source).expanduser().resolve()
    if not src.is_file():
        print(f"源文件不存在: {src}", file=sys.stderr)
        return 1

    data = src.read_bytes()
    if data.startswith(b"\xef\xbb\xbf"):
        data = data[3:]
    try:
        text = data.decode("utf-8")
    except UnicodeDecodeError:
        text = data.decode("utf-8-sig", errors="replace")

    API_ENV.parent.mkdir(parents=True, exist_ok=True)
    if API_ENV.exists():
        bak = API_ENV.parent / f".env.bak.{int(time.time())}"
        shutil.copy2(API_ENV, bak)
        print(f"已备份: {bak}")

    out = text.rstrip() + "\n"
    if MERGE.is_file():
        merge_txt = MERGE.read_text(encoding="utf-8").strip()
        if merge_txt:
            out += "\n# ----- merged from api/.env.local.merge (本机覆盖，勿提交) -----\n"
            out += merge_txt + "\n"
            print(f"已追加: {MERGE}")

    API_ENV.write_text(out, encoding="utf-8", newline="\n")
    print(f"已写入: {API_ENV}")
    print("提示: 管理端请用 admin/.env.development 留空 VITE_API_BASE_URL，并先起 api/public 的 PHP 再 npm run dev。")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
