// pages/enterprise/index.js - 企业版首页
const app = getApp()
const { request } = require('../../utils/request')

Page({
  data: {
    statusBarHeight: 0,
    navbarHeight: 88,
    siteTitle: '神仙团队性格测试',
    startButtonEnterprise: '开始性格测试',
    aiAnalysisText: '分析',
    reviewMode: true
  },

  onLoad(options) {
    // 企业版首页：固定 scope=enterprise
    const app = getApp()
    try { app.globalData.appScope = 'enterprise' } catch (e) {}

    // ── 解析入参（兼容两种来源：扫码 scene / 分享链接 options）──
    const rawScene = (options && options.scene) ? decodeURIComponent(options.scene) : ''
    // 解析 scene 中的 key=value 对（如 uid=1&eid=6）
    const sceneParams = {}
    if (rawScene) {
      rawScene.split('&').forEach(pair => {
        const [k, v] = pair.split('=')
        if (k) sceneParams[k] = v || ''
      })
    }

    // 合并所有来源：scene > options（分享链接）
    const uid = parseInt(sceneParams.uid || options.uid || 0, 10)
    let eid   = parseInt(sceneParams.eid || options.eid  || 0, 10)

    // 兼容旧格式 scene: e_企业ID
    if (!eid && rawScene && rawScene.indexOf('e_') === 0) {
      eid = parseInt(rawScene.slice(2), 10) || 0
    }

    console.log('[enterprise/onLoad] 解码参数 =>', {
      rawScene,
      sceneParams,
      options,
      uid,
      eid
    })

    if (eid > 0) app.globalData.enterpriseIdFromScene = eid

    // 企业版分销绑定：uid > 0 且 eid > 0 时触发
    if (uid > 0 && eid > 0) {
      app.globalData._pendingInviterId    = uid
      app.globalData._pendingInviterScope = 'enterprise'
      app.globalData._pendingInviterEid   = eid
    }
    // 获取状态栏高度和屏幕信息
    const systemInfo = tt.getSystemInfoSync()
    const statusBarHeight = systemInfo.statusBarHeight || 0
    const screenWidth = systemInfo.screenWidth || 375
    const statusBarHeightRpx = (statusBarHeight * 750) / screenWidth
    const navbarHeightRpx = statusBarHeightRpx + 88
    const gd = app.globalData
    const rm = !!gd.reviewMode
    this.setData({
      statusBarHeight: statusBarHeightRpx,
      navbarHeight: navbarHeightRpx,
      siteTitle: rm ? (gd.siteTitle || '神仙团队性格测试').replace(/AI/gi, '') : (gd.siteTitle || '神仙团队性格测试'),
      startButtonEnterprise: rm ? '开始性格测试' : ((gd.textConfig && gd.textConfig.startButtonEnterprise) || '开始性格测试'),
      aiAnalysisText: rm ? '分析' : ((gd.textConfig && gd.textConfig.aiAnalysisText) || '分析'),
      reviewMode: rm
    })

    // 未绑定企业的用户：若从邀请码扫码进入（有 enterpriseIdFromScene）也允许使用企业版
    const userInfo = app.globalData.userInfo || tt.getStorageSync('userInfo') || {}
    const fromInvite = !!app.globalData.enterpriseIdFromScene
    const redirectBack = () => {
      tt.showToast({ title: '您尚未绑定任何企业，无法使用企业版', icon: 'none', duration: 2500 })
      setTimeout(() => tt.switchTab({ url: '/pages/index/index' }), 600)
    }
    // 如果不是通过企业邀请码进入：沿用原有 hasEnterprise 判定
    if (!fromInvite) {
      if (userInfo.hasEnterprise === true) {
        app.getRuntimeConfig().then((cfg) => {
          if (cfg && cfg.siteTitle) {
            app.globalData.siteTitle = cfg.siteTitle
            this.setData({ siteTitle: cfg.siteTitle })
          }
        }).catch(() => {})
        return
      }
      if (userInfo.hasEnterprise === false) {
        redirectBack()
        return
      }
    }
    app.ensureLogin()
      .then(() => app.getRuntimeConfig())
      .then((cfg) => {
        if (cfg) {
          if (cfg.siteTitle) {
            app.globalData.siteTitle = cfg.siteTitle
            this.setData({ siteTitle: cfg.siteTitle })
          }
          if (cfg.textConfig) {
            app.globalData.textConfig = cfg.textConfig
            const rm2 = !!app.globalData.reviewMode
            this.setData({
              startButtonEnterprise: rm2 ? '开始性格测试' : (cfg.textConfig.startButtonEnterprise || '开始性格测试'),
              aiAnalysisText: rm2 ? '分析' : (cfg.textConfig.aiAnalysisText || '分析')
            })
          }
        }
        if ((cfg && cfg.pricingType) !== 'enterprise' && !app.globalData.enterpriseIdFromScene) {
          redirectBack()
          return
        }
        // 若通过企业邀请码进入（带 enterpriseIdFromScene），登录后绑定到 wechat_users.enterpriseId
        const eid = app.globalData.enterpriseIdFromScene
        if (eid) {
          request({
            url: '/api/enterprise/bind',
            method: 'POST',
            data: { enterpriseId: eid },
            success(res) {
              if (res.statusCode === 200 && res.data && res.data.code === 200) {
                const data = res.data.data || {}
                const merged = { ...(app.globalData.userInfo || {}), ...data }
                app.globalData.userInfo = merged
                tt.setStorageSync('userInfo', merged)
              }
            }
          })
        }
      })
      .catch(() => {
        tt.showToast({ title: '请先登录', icon: 'none' })
        setTimeout(() => tt.switchTab({ url: '/pages/index/index' }), 600)
      })
  },

  onShow() {
    if (typeof this.getTabBar === 'function' && this.getTabBar()) {
      this.getTabBar().setData({ selected: 0 })
    }
    try { getApp().globalData.appScope = 'enterprise' } catch (e) {}
    const gd = getApp().globalData
    const rm = !!gd.reviewMode
    this.setData({
      siteTitle: rm ? (gd.siteTitle || '神仙团队性格测试').replace(/AI/gi, '') : (gd.siteTitle || '神仙团队性格测试'),
      startButtonEnterprise: rm ? '开始性格测试' : ((gd.textConfig && gd.textConfig.startButtonEnterprise) || '开始性格测试'),
      aiAnalysisText: rm ? '分析' : ((gd.textConfig && gd.textConfig.aiAnalysisText) || '分析'),
      reviewMode: rm
    })
  },

  // 切换到个人版
  switchToPersonal() {
    tt.switchTab({
      url: '/pages/index/index'
    })
  },

  startAITest() {
    if (this.data.reviewMode) {
      tt.navigateTo({ url: '/pages/test-select/index' })
      return
    }
    const eid = (app.globalData && app.globalData.enterpriseIdFromScene) || (app.globalData && app.globalData.userInfo && app.globalData.userInfo.enterpriseId) || (tt.getStorageSync('userInfo') || {}).enterpriseId || null
    const query = eid ? `?enterpriseId=${eid}&pageSize=1` : '?pageSize=1'
    request({
      url: '/api/enterprise/resume-uploads' + query,
      method: 'GET',
      needAuth: true,
      success: (res) => {
        const list = (res.data && res.data.code === 200 && res.data.data && res.data.data.list) ? res.data.data.list : []
        if (!list.length) {
          tt.showModal({
            title: '提示',
          content: '需要先上传简历后再开始测试，请到「我的」-「我的简历」中上传',
          showCancel: true,
          confirmText: '去上传',
          success: (r) => {
            if (r.confirm) {
              tt.navigateTo({ url: '/pages/enterprise/resume-history' })
            }
          }
        })
        return
      }
      tt.switchTab({ url: '/pages/index/camera' })
    },
    fail: () => {
      tt.showModal({
        title: '提示',
        content: '需要先上传简历后再开始测试，请到「我的」-「我的简历」中上传',
          showCancel: true,
          confirmText: '去上传',
          success: (r) => {
            if (r.confirm) {
              tt.navigateTo({ url: '/pages/enterprise/resume-history' })
            }
          }
        })
      }
    })
  },

  onShareAppMessage() {
    const { getSharePath } = require('../../utils/share')
    const rm = this.data.reviewMode
    return {
      title: rm ? '神仙团队性格测试 (企业版) - 团队分析与优化' : '神仙团队性格测试 (企业版) - 团队分析与优化',
      path: getSharePath('/pages/enterprise/index')
    }
  },

  onShareTimeline() {
    const { buildShareQuery } = require('../../utils/share')
    const rm = this.data.reviewMode
    return {
      title: rm ? '神仙团队性格测试 (企业版) - 团队分析与优化' : '神仙团队性格测试 (企业版) - 团队分析与优化',
      query: buildShareQuery()
    }
  }
})
