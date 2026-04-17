// pages/ai-test/index.js — AI 测评方式选择
const app = getApp()

Page({
  data: {
    permFace: true
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
    try { require('../../utils/analytics').track('page_view', { pagePath: 'pages/ai-test/index' }) } catch (e) {}
  },

  onShow() {
    this._syncPerms()
  },

  _syncPerms() {
    const p = app.globalData.enterprisePermissions
    const permFace = !p || p.face !== false
    this.setData({ permFace })
  },

  goAiChat() {
    try { require('../../utils/analytics').track('tap_ai_test_entry', { target: 'ai_chat' }) } catch (e) {}
    wx.navigateTo({ url: '/pages/ai-chat/index?src=ai_test' })
  },

  goFaceCamera() {
    try { require('../../utils/analytics').track('tap_ai_test_entry', { target: 'face_camera' }) } catch (e) {}
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
      title: 'AI 性格测试 · 对话与拍照解读',
      path: getSharePath('/pages/ai-test/index')
    }
  },

  onShareTimeline() {
    const { buildShareQuery } = require('../../utils/share')
    return {
      title: 'AI 性格测试 · 对话与拍照解读',
      query: buildShareQuery()
    }
  }
})
