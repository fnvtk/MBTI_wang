#!/usr/bin/env bash
# mbti王 本地一键启动（前台）：自动找 PHP>=8、释放 8787/5173、起 API + Vite；Ctrl+C 会停 PHP。
# 需要「关终端仍运行」请用: bash scripts/dev_start_daemon.sh
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
API_PUBLIC="$ROOT/api/public"
ADMIN="$ROOT/admin"
LOG_DIR="$ROOT/api/runtime"
LOG_FILE="$LOG_DIR/php-dev-server.log"
API_PORT="${MBTI_API_PORT:-8787}"
ADMIN_PORT_DESIRED="${MBTI_ADMIN_PORT:-5173}"

die() { echo "❌ $*" >&2; exit 1; }

php_ok() {
  local bin="$1"
  [[ -x "$bin" ]] || [[ -f "$bin" && -r "$bin" ]] || return 1
  "$bin" -r 'exit(version_compare(PHP_VERSION, "8.0.0", ">=") ? 0 : 1);' 2>/dev/null
}

PHP_BIN=""
for c in \
  "${MBTI_PHP_BIN:-}" \
  "/opt/homebrew/bin/php" \
  "/opt/homebrew/opt/php@8.4/bin/php" \
  "/opt/homebrew/opt/php@8.3/bin/php" \
  "/opt/homebrew/opt/php/bin/php" \
  "/usr/local/opt/php@8.4/bin/php" \
  "/usr/local/opt/php@8.3/bin/php" \
  "/usr/local/opt/php/bin/php" \
  "php"
do
  [[ -z "$c" ]] && continue
  if command -v "$c" >/dev/null 2>&1 && php_ok "$(command -v "$c")"; then
    PHP_BIN="$(command -v "$c")"
    break
  fi
  if [[ -x "$c" ]] && php_ok "$c"; then
    PHP_BIN="$c"
    break
  fi
done

[[ -n "$PHP_BIN" ]] || die "未找到 PHP 8.0+。请安装：brew install php@8.4，或 export MBTI_PHP_BIN=/你的/php"

[[ -f "$API_PUBLIC/router.php" ]] || die "缺少 $API_PUBLIC/router.php"
[[ -f "$API_PUBLIC/index.php" ]] || die "缺少 $API_PUBLIC/index.php"

mkdir -p "$LOG_DIR"

_port_listen_pids() {
  lsof -tiTCP:"$1" -sTCP:LISTEN 2>/dev/null || true
}

_pick_next_free_admin_port_from() {
  local start="$1"
  local max_jump=80
  local p="$start"
  local end=$((start + max_jump))
  while [[ "$p" -le "$end" ]]; do
    if [[ -z "$(_port_listen_pids "$p")" ]]; then
      echo "$p"
      return 0
    fi
    p=$((p + 1))
  done
  die "无法在端口 ${start}-${end} 内找到空闲端口（请先关闭多余前端或设置 MBTI_ADMIN_PORT）"
}

ADMIN_PORT="$(_pick_next_free_admin_port_from "$ADMIN_PORT_DESIRED")"
if [[ "$ADMIN_PORT" != "$ADMIN_PORT_DESIRED" ]]; then
  echo "WARN: 端口 ${ADMIN_PORT_DESIRED} 已被占用，前端改用 ${ADMIN_PORT}"
fi

free_port() {
  local port="$1"
  local pids
  pids="$(lsof -tiTCP:"$port" -sTCP:LISTEN 2>/dev/null || true)"
  if [[ -n "${pids:-}" ]]; then
    echo "WARN: port $port in use, killing: $pids"
    # shellcheck disable=SC2086
    kill -9 ${pids} 2>/dev/null || true
    sleep 0.4
  fi
}

free_port "$API_PORT"

PHP_VER="$("$PHP_BIN" -r 'echo PHP_VERSION;')"
echo "OK PHP: $PHP_BIN ($PHP_VER)"
echo "OK API:  http://127.0.0.1:${API_PORT}  log=${LOG_FILE}"

cd "$API_PUBLIC"
: >"${LOG_FILE}"
"$PHP_BIN" -S "127.0.0.1:${API_PORT}" router.php >>"${LOG_FILE}" 2>&1 &
PHP_PID=$!

cleanup() {
  if kill -0 "$PHP_PID" 2>/dev/null; then
    echo ""
    echo "🛑 已停止 PHP (pid $PHP_PID)"
    kill "$PHP_PID" 2>/dev/null || true
  fi
}
trap cleanup EXIT INT TERM

sleep 0.2
if ! kill -0 "$PHP_PID" 2>/dev/null; then
  echo "❌ PHP 内置服务器未能启动，请查看日志:" >&2
  tail -n 30 "$LOG_FILE" >&2 || true
  exit 1
fi

echo "OK Admin: http://127.0.0.1:${ADMIN_PORT} (proxy -> API)"
cd "$ADMIN"
if [[ ! -d node_modules ]]; then
  echo "📦 首次运行，正在 npm install …"
  npm install
fi

export VITE_DEV_API_PROXY="http://127.0.0.1:${API_PORT}"
export MBTI_ADMIN_PORT="$ADMIN_PORT"
exec npm run dev -- --port "$ADMIN_PORT" --host 0.0.0.0
