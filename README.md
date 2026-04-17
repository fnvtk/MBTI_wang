# mbti王

MBTI 性格测试小程序 · 管理后台 · API 服务

## GitHub 仓库

- **地址**: https://github.com/fnvtk/MBTI_wang
- **克隆**: `git clone https://github.com/fnvtk/MBTI_wang.git`

## 日常同步

```bash
# 拉取最新
git pull origin main

# 提交并推送
git add .
git commit -m "描述"
git push origin main
```

## 产品与后台边界

- 见 `开发文档/1、需求/管理后台与产品目标对齐.md`（概览 / 用户运营 / 订单运营 / 系统设置 与小程序全链路文档对齐说明）

## 项目结构

- `admin/` - Vue 管理后台
- `api/` - ThinkPHP API
- `miniprogram/` - 微信小程序

## 本地开发（管理后台 + API）

### 推荐 A：后台常驻（关终端 / 关 Cursor 仍可用）

在仓库根目录执行（自动释放 **8787**、**5173**，起 PHP + Vite，**nohup 后台**，并做接口自检）：

```bash
bash scripts/dev_stop.sh          # 可选：先停旧进程
bash scripts/dev_start_daemon.sh
# 或 admin 目录：npm run dev:daemon
```

浏览器：**http://127.0.0.1:5173/admin/login**  
日志：`api/runtime/php-dev-server.log`、`api/runtime/vite-dev-server.log`  
自检：`bash scripts/dev_health.sh`（或 `npm run dev:health`）  
停止：`bash scripts/dev_stop.sh`

### 推荐 B：前台一键（调试用，Ctrl+C 即停）

```bash
bash scripts/dev_start.sh
# 或 admin 目录：npm run dev:all
```

（请保持 `admin/.env.development` 里 `VITE_API_BASE_URL` **留空**，走 Vite 代理。）

可选环境变量：`MBTI_PHP_BIN`、`MBTI_API_PORT`、`MBTI_ADMIN_PORT`、`MBTI_API_CURL_TIMEOUT`（守护进程等 API 的 curl 秒数，默认 120）。  
前端开发环境 axios 默认 **180s** 超时（云库慢）；可在 `admin/.env.development` 设 `VITE_REQUEST_TIMEOUT_MS`。  
Vite 代理默认 **180s**（`VITE_PROXY_TIMEOUT_MS` 可改）。

### 手动分步（与旧流程一致）

1. 启动 API（需 PHP 8+）：

```bash
cd api/public
/usr/local/opt/php@8.4/bin/php -S 127.0.0.1:8787 router.php
```

2. 启动管理后台：

```bash
cd admin
npm install
npm run dev
```

接口经 Vite 转发到 `http://127.0.0.1:8787`。若 API 端口不是 8787，可执行  
`VITE_DEV_API_PROXY=http://127.0.0.1:端口 npm run dev`。

### 从 kr 宝塔同步 `.env`

1. 在宝塔站点目录下载或复制线上 `api/.env` 到本机（任意路径）。  
2. 执行：`python3 scripts/apply_bt_download_env.py /你下载的路径/.env`  
   - 会自动备份当前 `api/.env` 为 `api/.env.bak.<时间戳>`。  
3. 若宝塔里数据库是 `127.0.0.1`（指服务器本机），本机需隧道或改连云库：可复制 `api/.env.local.merge.example` 为 `api/.env.local.merge`，按注释写好覆盖项，再重新执行第 2 步（会追加合并）。  
4. **管理端**请保持 `admin/.env.development` 里 `VITE_API_BASE_URL` **留空**，否则浏览器会直连 `mbti.com` 等域名，易出现 **Network Error**。
