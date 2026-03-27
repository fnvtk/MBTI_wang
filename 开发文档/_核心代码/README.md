# 🎯 MBTI性格测试系统 - 核心代码文档

> **版本**: v1.0.2  
> **更新时间**: 2026-01-28  
> **技术栈**: Next.js 14 + React 18 + MongoDB + 微信小程序

---

## 📊 项目概述

MBTI性格测试系统是一个多端（Web H5 + 微信小程序）的性格测试平台，包含：
- **MBTI测试**: 90道专业题目，16种人格类型
- **DISC测试**: 20道题目，4种行为风格
- **PDP测试**: 20道题目，5种动物性格
- **AI人脸分析**: 基于Groq API的面相性格分析

---

## 🏗️ 系统架构

```mermaid
graph TB
    subgraph 用户端
        A1[Web H5<br/>Next.js 14]
        A2[微信小程序<br/>原生开发]
    end
    
    subgraph 后端服务[Next.js API Routes]
        B1[/api/test/*<br/>测试相关]
        B2[/api/analyze<br/>AI分析]
        B3[/api/payment/*<br/>支付相关]
        B4[/api/wechat/*<br/>微信登录]
    end
    
    subgraph AI服务
        C1[Groq API<br/>llama3-8b-8192]
    end
    
    subgraph 数据存储
        D1[(MongoDB<br/>用户/测试结果)]
        D2[LocalStorage<br/>本地缓存]
    end
    
    subgraph 支付
        E1[微信支付<br/>小程序JSAPI]
    end
    
    A1 --> B1
    A1 --> B2
    A2 --> B3
    A2 --> B4
    B2 --> C1
    B1 --> D1
    B3 --> E1
    A2 --> D2
```

---

## 📁 目录结构

```
MBTI/
├── app/                          # Next.js App Router
│   ├── page.tsx                  # 首页（个人版/企业版切换）
│   ├── layout.tsx                # 根布局
│   ├── globals.css               # 全局样式（苹果毛玻璃风格）
│   │
│   ├── test/                     # 测试页面
│   │   ├── mbti/page.tsx         # MBTI测试（90题）
│   │   ├── disc/page.tsx         # DISC测试（20题）
│   │   └── pdp/page.tsx          # PDP测试（20题）
│   │
│   ├── camera/page.tsx           # AI人脸拍照页面
│   ├── analysis/page.tsx         # AI分析结果页面
│   │
│   ├── mbti-result/page.tsx      # MBTI结果页
│   ├── disc-result/page.tsx      # DISC结果页
│   ├── pdp-result/page.tsx       # PDP结果页
│   │
│   ├── admin/                    # 管理后台
│   │   ├── login/page.tsx        # 管理员登录
│   │   ├── dashboard/page.tsx    # 数据仪表盘
│   │   ├── users/page.tsx        # 用户管理
│   │   └── pricing/page.tsx      # 定价管理
│   │
│   └── api/                      # API路由
│       ├── analyze/route.ts      # ⭐ AI分析核心接口
│       ├── test/submit/route.ts  # 测试提交
│       ├── questions/route.ts    # 获取题目
│       └── payment/route.ts      # 支付接口
│
├── lib/                          # 核心库
│   ├── mbti-calculator.ts        # ⭐ MBTI计算器（核心算法）
│   ├── groq-service.ts           # ⭐ Groq AI服务
│   ├── db.ts                     # MongoDB连接
│   └── wechat-config.ts          # 微信配置
│
├── data/                         # 测试数据
│   ├── mbti-questions.ts         # MBTI题库（90题）
│   ├── mbti-descriptions.ts      # MBTI类型描述
│   ├── disc-questions.ts         # DISC题库（20题）
│   └── pdp-questions.ts          # PDP题库（20题）
│
├── components/                   # UI组件
│   ├── bottom-nav.tsx            # 底部导航
│   └── ui/                       # Shadcn UI组件
│
├── miniprogram/                  # 微信小程序
│   ├── app.js                    # 小程序入口
│   ├── app.json                  # 小程序配置
│   ├── pages/
│   │   ├── index/                # 首页
│   │   ├── test/                 # 测试页面
│   │   ├── result/               # 结果页面
│   │   ├── ai-test/              # AI测试模块
│   │   ├── purchase/             # 购买页面
│   │   └── profile/              # 个人中心
│   └── utils/
│       ├── payment.js            # ⭐ 微信支付核心
│       ├── questions.js          # 题库数据
│       └── descriptions.js       # 类型描述
│
└── 开发文档/                      # 开发文档
    ├── _核心代码/                 # 核心代码提取
    ├── 1、需求/
    ├── 2、架构/
    └── ...
```

---

## ⭐ 核心代码清单

| 文件 | 功能 | 重要度 | 说明 |
|:---|:---|:---:|:---|
| `lib/mbti-calculator.ts` | MBTI计算器 | ⭐⭐⭐ | 核心算法，计算维度得分、确定类型、置信度 |
| `lib/groq-service.ts` | AI分析服务 | ⭐⭐⭐ | 调用Groq API进行面相分析 |
| `app/api/analyze/route.ts` | AI分析接口 | ⭐⭐⭐ | 接收照片、调用AI、返回结果 |
| `miniprogram/utils/payment.js` | 微信支付 | ⭐⭐⭐ | VIP购买、次数购买、权限检查 |
| `data/mbti-questions.ts` | MBTI题库 | ⭐⭐ | 90道专业测试题 |
| `app/camera/page.tsx` | 拍照页面 | ⭐⭐ | 摄像头调用、多角度拍摄 |
| `app/analysis/page.tsx` | 分析结果 | ⭐⭐ | AI分析结果展示 |

---

## 🔧 技术栈

| 层级 | 技术选型 | 说明 |
|:---|:---|:---|
| **前端框架** | Next.js 14 (App Router) | React 服务端渲染 |
| **UI库** | Shadcn UI + Tailwind CSS | 苹果毛玻璃风格 |
| **AI服务** | Groq API (llama3-8b-8192) | 快速、低成本 |
| **数据库** | MongoDB + Mongoose | 文档型数据库 |
| **小程序** | 微信原生开发 | 无框架依赖 |
| **支付** | 微信支付 JSAPI | 小程序内支付 |
| **部署** | Vercel / 宝塔 | 自动化部署 |

---

## 📈 项目状态

### ✅ 已完成功能

| 模块 | 功能 | 状态 |
|:---|:---|:---:|
| **测试功能** | MBTI/DISC/PDP测试 | ✅ |
| **AI分析** | 人脸性格分析 | ✅ |
| **结果展示** | 详细报告页面 | ✅ |
| **小程序** | 完整功能迁移 | ✅ |
| **支付系统** | VIP/次数/单次购买 | ✅ |
| **管理后台** | 用户管理/定价管理 | ✅ |

### 🔄 待完善功能

| 模块 | 功能 | 优先级 |
|:---|:---|:---:|
| **后端API** | 支付接口对接微信 | P0 |
| **数据库** | MongoDB正式部署 | P0 |
| **AI优化** | 接入更准确的模型 | P1 |
| **分享功能** | 生成分享海报 | P2 |

---

## 🚀 快速启动

```bash
# 1. 安装依赖
pnpm install

# 2. 配置环境变量
cp .env.local.example .env.local
# 编辑 .env.local 填写：
# - MONGODB_URI
# - GROQ_API_KEY
# - WECHAT_APP_ID
# - WECHAT_APP_SECRET

# 3. 启动开发服务器
pnpm dev

# 4. 访问
# Web: http://localhost:3000
# 管理后台: http://localhost:3000/admin (连点标题5次进入)
```

---

## 📞 联系方式

- **开发者**: 卡若
- **微信**: 28533368
- **项目地址**: /Users/karuo/Documents/开发/3、自营项目/MBTI
