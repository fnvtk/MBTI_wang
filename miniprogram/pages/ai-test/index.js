const app = getApp()
const { isAuditHideAiMode } = require('../../utils/miniprogramAuditGate.js')

Page({
  data: {
    permFace: true,
    showAiChat: true
  },

  onLoad(options) {
    try {
      require('../../utils/thirdPartyContext.js').ingestThirdPartyOnPageLoad(options || {}, app)
    } catch (e) {}
    try {
      wx.showShareMenu({ withShareTicket: true, menus: ['shareAppMessage', 'shareTimeline'] })
    } catch (e) {}
    app.ensureLogin().catch(() => {})
    this._syncPerms()
    try {
      require('../../utils/analytics').track('page_view', { pagePath: 'pages/ai-test/index' })
    } catch (e) {}
  },

  onShow() {
    const app = getApp()
    if (app.getRuntimeConfig) {
      app.getRuntimeConfig().finally(() => this._syncPerms())
    } else {
      this._syncPerms()
    }
  },

  _syncPerms() {
    const p = app.globalData.enterprisePermissions
    const permFace = !p || p.face !== false
    const showAiChat = !isAuditHideAiMode(getApp().globalData)
    this.setData({ permFace, showAiChat })
  },

  goAiChat() {
    if (isAuditHideAiMode(getApp().globalData)) {
      wx.showToast({ title: '功能升级中', icon: 'none' })
      return
    }
    try {
      require('../../utils/analytics').track('tap_ai_test_entry', { target: 'ai_chat' })
    } catch (e) {}
    wx.navigateTo({ url: '/pages/ai-chat/index?src=ai_test' })
  },

  goFaceCamera() {
    try {
      require('../../utils/analytics').track('tap_ai_test_entry', { target: 'face_camera' })
    } catch (e) {}
    wx.switchTab({
      url: '/pages/index/camera',
      fail: () => {
        wx.showToast({ title: '请从底部「拍摄」进入', icon: 'none' })
      }
    })
  },

  onShareAppMessage() {
    const { getSharePath } = require('../../utils/share')
    return {
      title: 'AI 性格测试 · 对话与拍照',
      path: getSharePath('/pages/ai-test/index')
    }
  },

  onShareTimeline() {
    const { buildShareQuery } = require('../../utils/share')
    return {
      title: 'AI 性格测试 · 对话与拍照',
      query: buildShareQuery()
    }
  }
})
