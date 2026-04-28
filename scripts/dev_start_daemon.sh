#!/usr/bin/env bash
# mbti王 本地常驻启动：PHP API + Vite 后台运行，关闭终端/Cursor 后仍可用（用 dev_stop.sh 停止）
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
API_PUBLIC="$ROOT/api/public"
ADMIN="$ROOT/admin"
LOG_DIR="$ROOT/api/runtime"
PHP_LOG="$LOG_DIR/php-dev-server.log"
VITE_LOG="$LOG_DIR/vite-dev-server.log"
PHP_PID_FILE="$LOG_DIR/dev-php.pid"
VITE_PID_FILE="$LOG_DIR/dev-vite.pid"
API_PORT="${MBTI_API_PORT:-8787}"
ADMIN_PORT_DESIRED="${MBTI_ADMIN_PORT:-5173}"

die() { echo "ERROR: $*" >&2; exit 1; }

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

[[ -n "$PHP_BIN" ]] || die "Need PHP 8.0+. Install: brew install php@8.4  OR  export MBTI_PHP_BIN=/path/to/php"
[[ -f "$API_PUBLIC/router.php" ]] || die "Missing router.php"
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
    echo "WARN: freeing port $port -> $pids"
    # shellcheck disable=SC2086
    kill -9 ${pids} 2>/dev/null || true
    sleep 0.4
  fi
}

free_port "$API_PORT"
rm -f "$PHP_PID_FILE" "$VITE_PID_FILE"

echo "Starting PHP API on 127.0.0.1:${API_PORT} ..."
: >"$PHP_LOG"
cd "$API_PUBLIC"
nohup "$PHP_BIN" -S "127.0.0.1:${API_PORT}" router.php >>"$PHP_LOG" 2>&1 </dev/null &
PHP_PID=$!
echo "$PHP_PID" >"$PHP_PID_FILE"
sleep 0.35
kill -0 "$PHP_PID" 2>/dev/null || {
  echo "PHP failed. Last lines of $PHP_LOG:" >&2
  tail -n 40 "$PHP_LOG" >&2 || true
  die "PHP built-in server exited"
}

api_curl_sec="${MBTI_API_CURL_TIMEOUT:-120}"
echo "Waiting for API /api/config/runtime (curl max ${api_curl_sec}s, cloud DB may be slow) ..."
api_ok=0
code=""
for _ in $(seq 1 30); do
  code="$(curl -sS -m "$api_curl_sec" -o /dev/null -w "%{http_code}" "http://127.0.0.1:${API_PORT}/api/config/runtime" 2>/dev/null || echo 000)"
  if [[ "$code" == "200" ]]; then api_ok=1; break; fi
  sleep 1
done
if [[ "$api_ok" != "1" ]]; then
  kill "$PHP_PID" 2>/dev/null || true
  rm -f "$PHP_PID_FILE"
  echo "Last HTTP code: ${code:-unknown}. Tail $PHP_LOG:" >&2
  tail -n 50 "$PHP_LOG" >&2 || true
  die "API not HTTP 200. Check api/.env DB reachable / firewall. See $PHP_LOG"
fi

if [[ ! -d "$ADMIN/node_modules" ]]; then
  echo "Running npm install in admin/ ..."
  (cd "$ADMIN" && npm install)
fi

echo "Starting Vite on 0.0.0.0:${ADMIN_PORT} (proxy -> API) ..."
: >"$VITE_LOG"
cd "$ADMIN"
export VITE_DEV_API_PROXY="http://127.0.0.1:${API_PORT}"
export MBTI_ADMIN_PORT="$ADMIN_PORT"
# 强制走本机代理，避免系统级 VITE_API_BASE_URL 覆盖导致 Network Error
unset VITE_API_BASE_URL
export VITE_API_BASE_URL=
nohup npx vite --host 0.0.0.0 --port "$ADMIN_PORT" >>"$VITE_LOG" 2>&1 </dev/null &
VITE_PID=$!
echo "$VITE_PID" >"$VITE_PID_FILE"
sleep 0.6
kill -0 "$VITE_PID" 2>/dev/null || {
  echo "Vite failed. Last lines of $VITE_LOG:" >&2
  tail -n 60 "$VITE_LOG" >&2 || true
  kill "$PHP_PID" 2>/dev/null || true
  die "Vite exited"
}

echo "Waiting for Vite ..."
vite_ok=0
for _ in $(seq 1 60); do
  code="$(curl -sS -m 5 -o /dev/null -w "%{http_code}" "http://127.0.0.1:${ADMIN_PORT}/" 2>/dev/null || echo 000)"
  if [[ "$code" == "200" ]]; then vite_ok=1; break; fi
  sleep 0.35
done
if [[ "$vite_ok" != "1" ]]; then
  echo "Vite not ready. Last lines of $VITE_LOG:" >&2
  tail -n 60 "$VITE_LOG" >&2 || true
  kill "$PHP_PID" 2>/dev/null || true
  rm -f "$PHP_PID_FILE" "$VITE_PID_FILE"
  die "Vite not HTTP 200. See $VITE_LOG"
fi

echo ""
echo "=== mbti王 后台已就绪（关终端仍运行）==="
echo "  Admin:  http://127.0.0.1:${ADMIN_PORT}/admin/login"
echo "  API:    http://127.0.0.1:${API_PORT}/api/config/runtime"
echo "  Logs:   $PHP_LOG"
echo "          $VITE_LOG"
echo "  Stop:   bash scripts/dev_stop.sh"
echo ""

bash "$ROOT/scripts/dev_health.sh" || true
