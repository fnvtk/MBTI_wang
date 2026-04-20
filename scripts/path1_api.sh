#!/usr/bin/env bash
# 路径一：一键调用宝塔 API 上传 API 相关文件（依赖 scripts/.env.bt 或环境变量 BT_API_KEY）
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
if [[ ! -f scripts/.env.bt ]]; then
  cp scripts/env.bt.example scripts/.env.bt
fi
exec python3 scripts/deploy_mbti_bt_api.py --api-only
