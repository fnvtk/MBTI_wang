// pages/index/index.js
const app = getApp()
const { request } = require('../../utils/request.js')

Page({
  data: {
    statusBarHeight: 0,
    navbarHeight: 88,
    showEnterpriseEntry: false,
    siteTitle: '神仙团队AI性格测试',
    startButtonText: '开始面相测试',
    aiAnalysisText: '智能分析',
    maintenanceMode: false
  },

  onLoad(options) {
    // 获取状态栏高度和屏幕信息
    const systemInfo = wx.getSystemInfoSync()
    const statusBarHeight = systemInfo.statusBarHeight || 0
    const screenWidth = systemInfo.screenWidth || 375
    // 将状态栏高度从px转换为rpx
    const statusBarHeightRpx = (statusBarHeight * 750) / screenWidth
    const navbarHeightRpx = statusBarHeightRpx + 88// 状态栏 + 导航栏内容
    
    const userInfo = getApp().globalData.userInfo || wx.getStorageSync('userInfo') || {}
    const gd = getApp().globalData
    const maintenanceMode = !!(gd.maintenanceMode)
    this.setData({
      statusBarHeight: statusBarHeightRpx,
      navbarHeight: navbarHeightRpx,
      showEnterpriseEntry: userInfo.hasEnterprise === true,
      siteTitle: gd.siteTitle || '神仙团队AI性格测试',
      startButtonText: maintenanceMode ? '开始性格测试' : ((gd.textConfig && gd.textConfig.startButtonText) || '开始面相测试'),
      aiAnalysisText: (gd.textConfig && gd.textConfig.aiAnalysisText) || '智能分析',
      maintenanceMode
    })
    // 预加载站点名称与文案配置
    app.getRuntimeConfig().then((cfg) => {
      if (cfg) {
        if (cfg.siteTitle) {
          getApp().globalData.siteTitle = cfg.siteTitle
          this.setData({ siteTitle: cfg.siteTitle })
        }
        if (cfg.textConfig) {
          getApp().globalData.textConfig = cfg.textConfig
        }
        const maintenanceMode = !!(cfg && cfg.maintenanceMode)
        if (cfg.maintenanceMode !== undefined) getApp().globalData.maintenanceMode = maintenanceMode
        this.setData({
          startButtonText: maintenanceMode ? '开始性格测试' : (cfg.textConfig && cfg.textConfig.startButtonText || '开始面相测试'),
          aiAnalysisText: (cfg.textConfig && cfg.textConfig.aiAnalysisText) || '智能分析',
          maintenanceMode
        })
        const tabBar = typeof this.getTabBar === 'function' && this.getTabBar()
        if (tabBar) tabBar.setData({ maintenanceMode })
      }
    }).catch(() => {})
    // ── 解析入参（兼容扫码 scene / 分享链接 options）──
    const rawScene = (options && options.scene) ? decodeURIComponent(options.scene) : ''
    const sceneParams = {}
    if (rawScene) {
      rawScene.split('&').forEach(pair => {
        const [k, v] = pair.split('=')
        if (k) sceneParams[k] = v || ''
      })
    }

    const uid = parseInt(sceneParams.uid || (options && options.uid) || 0, 10)
    const eid = parseInt(sceneParams.eid || (options && options.eid) || 0, 10)

    console.log('[index/onLoad] 解码参数 =>', {
      rawScene,
      sceneParams,
      options,
      uid,
      eid
    })

    // 携带 eid：跳转企业版首页
    if (eid > 0) {
      getApp().globalData.enterpriseIdFromScene = eid
      wx.navigateTo({ url: '/pages/enterprise/index?uid=' + uid + '&eid=' + eid })
      return
    }

    // 个人版分销绑定
    if (uid > 0) {
      app.globalData._pendingInviterId    = uid
      app.globalData._pendingInviterScope = 'personal'
    }
  },

  onShow() {
    const tabBar = typeof this.getTabBar === 'function' && this.getTabBar()
    if (tabBar) tabBar.setData({ selected: 0, maintenanceMode: !!getApp().globalData.maintenanceMode })
    // 个人版首页：固定 scope=personal，并清除企业来源上下文
    try {
      getApp().globalData.appScope = 'personal'
      getApp().globalData.enterpriseIdFromScene = null
    } catch (e) {}
    const gd = getApp().globalData
    const maintenanceMode = !!(gd.maintenanceMode)
    this.setData({
      siteTitle: gd.siteTitle || '神仙团队AI性格测试',
      startButtonText: maintenanceMode ? '开始性格测试' : ((gd.textConfig && gd.textConfig.startButtonText) || '开始面相测试'),
      aiAnalysisText: (gd.textConfig && gd.textConfig.aiAnalysisText) || '智能分析',
      maintenanceMode
    })
    const userInfo = getApp().globalData.userInfo || wx.getStorageSync('userInfo') || {}
    this.setData({ showEnterpriseEntry: userInfo.hasEnterprise === true })
    // 有 token 时拉取最新用户信息（含 hasEnterprise），绑定企业后无需重新登录即可展示企业版入口
    const token = getApp().globalData.token || wx.getStorageSync('token')
    if (token) {
      request({
        url: '/api/auth/me',
        method: 'GET',
        success: (res) => {
          if (res.statusCode === 200 && res.data && res.data.code === 200 && res.data.data) {
            const user = res.data.data
            getApp().globalData.userInfo = user
            wx.setStorageSync('userInfo', user)
            this.setData({ showEnterpriseEntry: user.hasEnterprise === true })
          }
        }
      })
    }
  },

  // 开始拍照（个人版入口：强制本次链路为个人定价）；审核模式下跳转 test-select
  startCamera() {
    try { getApp().globalData.appScope = 'personal' } catch (e) {}
    if (this.data.maintenanceMode) {
      wx.navigateTo({ url: '/pages/test-select/index' })
      return
    }
    wx.switchTab({
      url: '/pages/index/camera'
    })
  },

  // 上传照片（个人版入口：强制本次链路为个人定价）
  uploadPhoto() {
    try { getApp().globalData.appScope = 'personal' } catch (e) {}
    // 这里仅负责跳转到全新的「拍摄或上传照片」页面，具体拍摄/上传逻辑在新页面实现
    wx.navigateTo({
      url: '/pages/index/upload'
    })
  },

  // 切换到企业版（仅已绑定企业的用户可进入，优先用登录返回的 hasEnterprise，避免多请求）
  switchToEnterprise() {
    const app = getApp()
    const userInfo = app.globalData.userInfo || wx.getStorageSync('userInfo') || {}
    if (userInfo.hasEnterprise === true) {
      wx.navigateTo({ url: '/pages/enterprise/index' })
      return
    }
    if (userInfo.hasEnterprise === false) {
      wx.showToast({ title: '您尚未绑定任何企业，无法使用企业版', icon: 'none', duration: 2500 })
      return
    }
    // 缓存里没有 hasEnterprise 时：临时用 enterprise scope 获取配置判定
    try { app.globalData.appScope = 'enterprise' } catch (e) {}
    app.ensureLogin().then(() => app.getRuntimeConfig()).then((cfg) => {
      if ((cfg && cfg.pricingType) === 'enterprise') {
        wx.navigateTo({ url: '/pages/enterprise/index' })
      } else {
        wx.showToast({ title: '您尚未绑定任何企业，无法使用企业版', icon: 'none', duration: 2500 })
      }
    }).catch(() => {
      wx.showToast({ title: '无法获取配置，请稍后重试', icon: 'none' })
    })
  },

  onShareAppMessage() {
    const { getSharePathByScope } = require('../../utils/share')
    return {
      title: 'AI人脸性格分析 - 看看你的面相透露了什么性格密码',
      path: getSharePathByScope('/pages/index/index')
    }
  },

  onShareTimeline() {
    const { buildShareQuery } = require('../../utils/share')
    return {
      title: 'AI人脸性格分析 - 看看你的面相透露了什么性格密码',
      query: buildShareQuery()
    }
  }
})
