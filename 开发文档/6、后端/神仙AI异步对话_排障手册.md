# 神仙 AI 异步对话 · 排障手册

> 真源经验摘要见卡若：`02_卡人（水）/水溪_整理归档/经验库/待沉淀/2026-04-17_MBTI王_神仙AI异步对话_ThinkORM_strict驼峰字段与任务排障.md`

---

## 一、链路速览

1. `POST /api/ai/chat` → 落库 user 消息 → 返回 `jobId`（异步）  
2. `GET /api/ai/chat/job?jobId=` 轮询  
3. `InternalPushHook` / `register_shutdown_function` → `AiChat::runDeferredChatJob` → `executeAssistantTurn` → `AiCallService::chat`

**核心代码**：`api/app/controller/api/AiChat.php`、`api/app/common/service/AiCallService.php`  
**模型**：`api/app/model/AiConversation.php`、`api/app/model/AiMessage.php`

---

## 二、小程序「出了点状况 + 降级兜底」

| 优先查 | 说明 |
|:---|:---|
| **`mbti_ai_chat_jobs.errorMessage`** | 若为 **`会话不存在`** → 见第三节 |
| **`AiConversation` / `AiMessage` 的 `$strict`** | 表列为 **驼峰**（`userId`、`conversationId`）时 **必须为 `true`**，否则 ORM 会按 snake 写字段名导致 **写库为 0** |
| **`mbti_ai_messages.isDegraded` 列** | 缺列则助手消息 INSERT 失败 → 任务 error |
| **JWT / 异步路由** | `POST /api/internal/outbound-push/dispatch` 验签与 Nginx 放行 |

---

## 三、ThinkORM `strict` 与驼峰表（必记）

- **`protected $strict = false`**：`userId` 会被当成 **`user_id`** 参与 SQL；库中只有 **`userId`** → **值落不进去 → 0**。  
- **修复**：`AiConversation`、`AiMessage` 使用 **`protected $strict = true;`**（与当前仓库一致）。  
- **验证**：新对话后 `ai_conversations.userId` 应为真实用户 id，`ai_messages.conversationId` 应为会话 id。

---

## 四、服务器自检

在 **`api` 根目录**使用 **PHP 8+**（勿用默认 `php` 7.4）：

```bash
/www/server/php/80/bin/php scripts/check_ai_chat_ready.php
/www/server/php/80/bin/php scripts/smoke_ai_provider.php
```

---

## 五、部署与密钥

- 路径一：`scripts/deploy_mbti_bt_api.py --api-only`（`scripts/.env.bt` 或环境变量 `BT_API_KEY`）  
- 卡若侧凭据拉取：**G24 · MBTI 王 · 腾讯云 SSM 拉宝塔凭据**

---

## 六、用户侧

修复发布后建议用户：**删除小程序数据或删小程序重进**，避免本地缓存错误 `conversationId` 指向历史脏行。
