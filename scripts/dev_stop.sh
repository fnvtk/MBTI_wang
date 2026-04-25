#!/usr/bin/env bash
# 结束本机 mbti王 开发进程（先按 pid 文件，再按端口）
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
LOG_DIR="$ROOT/api/runtime"
PHP_PID_FILE="$LOG_DIR/dev-php.pid"
VITE_PID_FILE="$LOG_DIR/dev-vite.pid"
API_PORT="${MBTI_API_PORT:-8787}"
ADMIN_PORT="${MBTI_ADMIN_PORT:-5173}"

kill_pidfile() {
  local f="$1"
  [[ -f "$f" ]] || return 0
  local p
  p="$(tr -d ' \n\r\t' <"$f" || true)"
  [[ -n "${p:-}" ]] || { rm -f "$f"; return 0; }
  if kill -0 "$p" 2>/dev/null; then
    echo "停止 pid $p ($f)"
    kill -9 "$p" 2>/dev/null || true
  fi
  rm -f "$f"
}

kill_pidfile "$PHP_PID_FILE"
kill_pidfile "$VITE_PID_FILE"

for port in "$API_PORT" "$ADMIN_PORT"; do
  pids="$(lsof -tiTCP:"$port" -sTCP:LISTEN 2>/dev/null || true)"
  if [[ -n "${pids:-}" ]]; then
    echo "停止端口 $port: $pids"
    # shellcheck disable=SC2086
    kill -9 ${pids} 2>/dev/null || true
  else
    echo "端口 $port 无监听"
  fi
done
echo "完成"
