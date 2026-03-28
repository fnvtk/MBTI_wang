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

1. 启动 API（需 PHP 8+，示例用 Homebrew 的 8.4）：

```bash
cd api/public
/usr/local/opt/php@8.4/bin/php -S 127.0.0.1:8787 router.php
```

2. 启动管理后台（`admin/.env.development` 已默认走代理，无需改 hosts）：

```bash
cd admin
npm install
npm run dev
```

浏览器打开 `http://localhost:5173` ，接口会经 Vite 转发到 `http://127.0.0.1:8787`。若 API 端口不是 8787，可执行  
`VITE_DEV_API_PROXY=http://127.0.0.1:端口 npm run dev`。
