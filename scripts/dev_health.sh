#!/usr/bin/env bash
# 快速检查本地 API + 管理端 + 经 Vite 代理的接口是否通
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
API_PORT="${MBTI_API_PORT:-8787}"
ADMIN_PORT="${MBTI_ADMIN_PORT:-5173}"

check() {
  local name="$1" url="$2" want="${3:-200}"
  local maxt="${4:-30}"
  local code
  code="$(curl -sS -m "$maxt" -o /dev/null -w "%{http_code}" "$url" 2>/dev/null || echo 000)"
  if [[ "$code" == "$want" ]]; then
    echo "OK  $name  HTTP $code  $url"
  else
    echo "BAD $name  HTTP $code (want $want)  $url" >&2
    return 1
  fi
}

fail=0
check "API runtime" "http://127.0.0.1:${API_PORT}/api/config/runtime" 200 60 || fail=1
check "Vite root" "http://127.0.0.1:${ADMIN_PORT}/" 200 15 || fail=1
# 管理端 axios 走 /api/v1 -> 由 Vite 代理到 PHP
check "Proxy /api/v1 (via Vite)" "http://127.0.0.1:${ADMIN_PORT}/api/config/runtime" 200 120 || fail=1

if [[ "$fail" != "0" ]]; then
  echo "" >&2
  echo "若 API 失败：检查 api/.env 数据库是否可达；看 api/runtime/php-dev-server.log" >&2
  echo "若代理失败：确认 admin/.env.development 里 VITE_API_BASE_URL 为空；Vite 已起。" >&2
  exit 1
fi
echo "All checks passed."
exit 0
