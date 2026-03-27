#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
MBTI王：转发到卡若AI 真源脚本（F01e）。Skill 正文勿放本仓库，见卡若AI 同 Skill。
"""
from __future__ import annotations

import os
import sys

_KARUO_SCRIPT = (
    "/Users/karuo/Documents/个人/卡若AI/04_卡火（火）/火炬_全栈消息/"
    "开发五角色与飞书里程碑/feishu_milestone_notify.py"
)
_DEFAULT_REPO = "/Users/karuo/Documents/开发/3、自营项目/mbti王"


def main() -> int:
    if not os.path.isfile(_KARUO_SCRIPT):
        print("未找到卡若AI 脚本:", _KARUO_SCRIPT, file=sys.stderr)
        return 2
    prefix = [
        sys.executable,
        _KARUO_SCRIPT,
        "--webhook-env",
        "FEISHU_WEBHOOK_MBTI",
        "--product",
        "MBTI王",
        "--keyword-line",
        "MBTI王 项目更新",
        "--repo",
        _DEFAULT_REPO,
    ]
    os.execv(sys.executable, prefix + sys.argv[1:])


if __name__ == "__main__":
    raise SystemExit(main())
