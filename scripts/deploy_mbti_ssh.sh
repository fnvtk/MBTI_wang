#!/usr/bin/env bash
# MBTI 王：经 SSH/rsync 同步到 kr 机（面板 API IP 未放行时的等价部署）。
# 依赖：sshpass、rsync。密码：export SSHPASS='...' 后再执行。
# 默认远端路径与线上 nginx 一致，可用环境变量覆盖。
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SSH_HOST="${MBTI_SSH_HOST:-43.139.27.93}"
SSH_PORT="${MBTI_SSH_PORT:-22022}"
SSH_USER="${MBTI_SSH_USER:-root}"
REMOTE_API="${MBTI_REMOTE_API:-/www/wwwroot/self/mbti-api/api}"
REMOTE_ADMIN="${MBTI_REMOTE_ADMIN:-/www/wwwroot/self/mbti-admin}"

if [[ -z "${SSHPASS:-}" ]]; then
  echo "请先: export SSHPASS='服务器 root 密码'" >&2
  exit 1
fi

RSYNC=(rsync -avz --no-owner --no-group -e "sshpass -e ssh -o StrictHostKeyChecking=no -p ${SSH_PORT}")

echo "== API PHP -> ${SSH_USER}@${SSH_HOST}:${REMOTE_API}"
"${RSYNC[@]}" \
  "${ROOT}/api/app/controller/admin/AppUser.php" \
  "${ROOT}/api/app/controller/admin/Order.php" \
  "${ROOT}/api/app/controller/admin/Dashboard.php" \
  "${ROOT}/api/app/controller/admin/Finance.php" \
  "${SSH_USER}@${SSH_HOST}:${REMOTE_API}/app/controller/admin/"

"${RSYNC[@]}" \
  "${ROOT}/api/app/controller/admin/concern/ExtractsTestResults.php" \
  "${SSH_USER}@${SSH_HOST}:${REMOTE_API}/app/controller/admin/concern/"

"${RSYNC[@]}" \
  "${ROOT}/api/app/model/WechatUser.php" \
  "${SSH_USER}@${SSH_HOST}:${REMOTE_API}/app/model/"

"${RSYNC[@]}" \
  "${ROOT}/api/route/api.php" \
  "${SSH_USER}@${SSH_HOST}:${REMOTE_API}/route/"

"${RSYNC[@]}" \
  "${ROOT}/api/app/controller/api/Auth.php" \
  "${ROOT}/api/app/controller/api/AppConfig.php" \
  "${ROOT}/api/app/controller/api/MpConfig.php" \
  "${ROOT}/api/app/controller/api/Order.php" \
  "${ROOT}/api/app/controller/api/AiChat.php" \
  "${ROOT}/api/app/controller/api/AiReport.php" \
  "${ROOT}/api/app/controller/api/Payment.php" \
  "${ROOT}/api/app/controller/api/WechatTransferNotify.php" \
  "${SSH_USER}@${SSH_HOST}:${REMOTE_API}/app/controller/api/"

"${RSYNC[@]}" \
  "${ROOT}/api/app/common/service/AiCallService.php" \
  "${ROOT}/api/app/common/service/AiChatArticleDisplayService.php" \
  "${ROOT}/api/app/common/service/SoulArticleService.php" \
  "${ROOT}/api/app/common/service/AiReportService.php" \
  "${ROOT}/api/app/common/service/WechatService.php" \
  "${ROOT}/api/app/common/service/WechatAuditSyncService.php" \
  "${ROOT}/api/app/common/service/WechatTransferService.php" \
  "${ROOT}/api/app/common/service/JwtService.php" \
  "${ROOT}/api/app/common/service/MpTabbarService.php" \
  "${ROOT}/api/app/common/service/FeishuLeadWebhookService.php" \
  "${ROOT}/api/app/common/service/OutboundPushHookService.php" \
  "${ROOT}/api/app/common/service/ThirdPartyChannelService.php" \
  "${SSH_USER}@${SSH_HOST}:${REMOTE_API}/app/common/service/"

"${RSYNC[@]}" \
  "${ROOT}/api/app/controller/superadmin/Settings.php" \
  "${SSH_USER}@${SSH_HOST}:${REMOTE_API}/app/controller/superadmin/"

if [[ ! -d "${ROOT}/admin/dist" ]]; then
  echo "缺少 admin/dist，正在构建..." >&2
  (cd "${ROOT}/admin" && npm run build)
fi

echo "== admin/dist -> ${SSH_USER}@${SSH_HOST}:${REMOTE_ADMIN}"
# 排除宝塔生成的 .user.ini，避免 rsync --delete 因权限无法删除导致退出码 23
"${RSYNC[@]}" --delete --exclude '.user.ini' \
  "${ROOT}/admin/dist/" \
  "${SSH_USER}@${SSH_HOST}:${REMOTE_ADMIN}/"

echo "== 重载 PHP-FPM + Nginx"
sshpass -e ssh -o StrictHostKeyChecking=no -p "${SSH_PORT}" "${SSH_USER}@${SSH_HOST}" \
  "nginx -t && nginx -s reload; (systemctl reload php-fpm-82 2>/dev/null || systemctl reload php-fpm-81 2>/dev/null || systemctl reload php-fpm-80 2>/dev/null || service php-fpm-82 reload 2>/dev/null || true)"

echo "完成。探活: curl -s -o /dev/null -w '%{http_code}' https://mbtiapi.quwanzhi.com/api/v1/admin/app-users"
