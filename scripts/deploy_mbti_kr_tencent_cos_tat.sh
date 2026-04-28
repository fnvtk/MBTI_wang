#!/usr/bin/env bash
# MBTI 王 → kr：不经宝塔面板 API（绕过 IP 白名单），走 COS + TAT。
# 凭证：优先环境变量；否则读取 scripts/.env.tencent（可由 env.tencent.example 复制）。
# 依赖：pip install cos-python-sdk-v5 tencentcloud-sdk-python-tat
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"
exec python3 "$ROOT/scripts/tencent_cos_tat_deploy_mbti.py" "$@"
