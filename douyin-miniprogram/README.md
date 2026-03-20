# MBTI王 · 抖音小程序版

从微信小程序 1:1 移植的抖音小程序版本。

## 项目结构

```
douyin-miniprogram/
├── app.js              # 主入口（抖音登录 tt.login）
├── app.json            # 页面与 TabBar 配置
├── app.ttss            # 全局样式
├── project.tt.json     # 抖音项目配置（需填入 AppID）
├── custom-tab-bar/     # 自定义 TabBar 组件
├── images/             # 静态资源
├── pages/              # 23 个页面
│   ├── index/          # 首页、相机、上传、AI 结果
│   ├── test-select/    # 测试类型选择
│   ├── test/           # MBTI/DISC/PDP 问卷
│   ├── result/         # 测试结果展示
│   ├── purchase/       # 购买页
│   ├── recharge/       # 企业充值
│   ├── enterprise/     # 企业版
│   ├── profile/        # 个人中心
│   ├── user-profile/   # 个人资料编辑
│   ├── history/        # 测试历史
│   ├── phone-auth/     # 手机号授权
│   └── promo/          # 推广中心、海报、提现
└── utils/              # 工具模块
    ├── request.js      # HTTP 请求封装
    ├── payment.js      # 抖音支付（tt.pay）
    ├── phoneAuth.js    # 手机号授权
    ├── share.js        # 分享参数
    ├── questions.js    # 测试题目
    ├── descriptions.js # 类型描述
    └── resultFormat.js # 结果格式化
```

## 与微信版差异

| 模块 | 微信 | 抖音 |
|:--|:--|:--|
| 文件扩展名 | .wxml / .wxss | .ttml / .ttss |
| API 前缀 | wx. | tt. |
| 模板指令 | wx:if / wx:for | tt:if / tt:for |
| 登录接口 | /api/auth/wechat | /api/auth/douyin |
| 支付 | wx.requestPayment | tt.pay({ orderInfo, service: 5 }) |
| 商户转账 | wx.requestMerchantTransfer | 服务端直接处理 |
| 朋友圈分享 | onShareTimeline | 不支持 |
| 文件选择 | wx.chooseMessageFile | tt.chooseImage（仅图片） |

## 后端需新增的接口

### 1. 抖音登录 `/api/auth/douyin` (POST)

接收 `{ code, anonymousCode }` → 调用抖音 `code2session` 接口：

```
GET https://developer.toutiao.com/api/apps/v2/jscode2session
参数: appid, secret, code
```

返回格式同微信版：`{ code: 200, data: { token, user } }`

### 2. 抖音用户资料 `/api/auth/douyin/profile` (PUT)

同 `/api/auth/wechat/profile`，接收 `{ nickname, avatar, birthday }`。

### 3. 抖音手机号 `/api/auth/douyin/phone` (POST)

抖音手机号获取方式与微信类似（button open-type="getPhoneNumber"），
回调中拿到 code 后传给后端解密。

### 4. 抖音支付 `/api/payment/create` (POST)

当 `paymentMethod === 'douyin'` 时，需调用抖音担保支付预下单接口，
返回 `{ order_id, order_token }` 供前端 `tt.pay` 使用。

抖音担保支付文档：
https://developer.open-douyin.com/docs/resource/zh-CN/mini-app/develop/server/ecpay/pay-list/pay

### 5. 提现确认 `/api/distribution/withdrawals/confirm-receipt` (POST)

抖音没有 `requestMerchantTransfer`，提现需服务端直接转账到支付宝/银行卡。

## 使用方式

1. 在抖音开放平台（https://developer.open-douyin.com）创建小程序，获取 AppID
2. 将 AppID 填入 `project.tt.json` 的 `appid` 字段
3. 用抖音开发者工具打开本项目目录
4. 在开发者工具中预览/上传

### CLI 上传

```bash
npm install -g tt-ide-cli
tma login
tma upload <YOUR_APPID> --project-path ./douyin-miniprogram --version "1.0.0"
```
