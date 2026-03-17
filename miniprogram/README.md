# MBTI性格测试小程序

## 小程序信息
- **AppID**: wx3d15ed02e98b04e3
- **名称**: MBTI性格测试
- **版本**: 1.0.0

## 功能模块

### 1. MBTI测试
- 30道精简题目
- 4个维度分析（E/I、S/N、T/F、J/P）
- 16种人格类型描述
- 置信度计算

### 2. DISC测试  
- 20道题目
- 4种行为风格（D/I/S/C）
- 职业建议和沟通技巧

### 3. PDP测试
- 20道题目
- 5种动物性格（老虎/孔雀/考拉/猫头鹰/变色龙）
- 团队角色分析

## 目录结构

```
miniprogram/
├── app.js              # 小程序入口
├── app.json            # 全局配置
├── app.wxss            # 全局样式
├── project.config.json # 项目配置
├── sitemap.json        # 站点地图
├── pages/
│   ├── index/          # 首页
│   ├── test/           # 测试页面
│   │   ├── mbti.*      # MBTI测试
│   │   ├── disc.*      # DISC测试
│   │   └── pdp.*       # PDP测试
│   ├── result/         # 结果页面
│   │   ├── mbti.*
│   │   ├── disc.*
│   │   └── pdp.*
│   └── profile/        # 个人中心
├── utils/
│   ├── questions.js    # 题库
│   └── descriptions.js # 类型描述
└── images/             # 图片资源
```

## 使用方法

1. 下载并安装 [微信开发者工具](https://developers.weixin.qq.com/miniprogram/dev/devtools/download.html)

2. 打开微信开发者工具，选择"导入项目"

3. 选择 `miniprogram` 目录作为项目目录

4. 填入AppID: `wx3d15ed02e98b04e3`

5. 点击"编译"即可预览

## 上传发布

1. 在微信开发者工具中点击"上传"

2. 填写版本号和项目备注

3. 登录 [微信公众平台](https://mp.weixin.qq.com) 提交审核

## 注意事项

- tabBar图标需要替换为实际的PNG图片（81x81像素）
- 分享图片需要放置到 images/share.png
- 后端API地址需要在 app.js 中配置

## 技术栈

- 微信小程序原生开发
- CSS3动画和过渡效果
- 本地存储（wx.setStorageSync）
