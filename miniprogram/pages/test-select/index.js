// pages/test-select/index.js
const app = getApp()
const { isAuditHideAiMode } = require('../../utils/miniprogramAuditGate.js')

Page({
  data: {
    permFace: true,
    permMbti: true,
    permSbti: true,
    permPdp: true,
    permDisc: true,
    /** 高考志愿任务中心；企业关闭 gaokao 时隐藏 */
    permGaokao: true,
    /** AI 对话（神仙 AI）；企业关闭 aiHub 时隐藏 */
    permAiHub: true,
    /** 四类问卷入口均被企业权限关闭时提示 */
    allTestsDisabled: false
  },

  onLoad(options) {
    try {
      require('../../utils/thirdPartyContext.js').ingestThirdPartyOnPageLoad(options || {}, app)
    } catch (e) {}
    try {
      wx.showShareMenu({ withShareTicket: true, menus: ['shareAppMessage', 'shareTimeline'] })
    } catch (e) {}
    // 分享直达时尽早完成静默登录，避免用户点进子页时仍无 token
    app.ensureLogin().catch(() => {})
    this._syncPerms()
  },

  onShow() {
    if (app.getRuntimeConfig) {
      app.getRuntimeConfig().finally(() => this._syncPerms())
    } else {
      this._syncPerms()
    }
  },

  _syncPerms() {
    const p = app.globalData.enterprisePermissions
    const hideAi = isAuditHideAiMode(app.globalData)
    const permFace = !p || p.face !== false
    const permMbti = !p || p.mbti !== false
    const permSbti = !p || p.sbti !== false
    const permPdp = !p || p.pdp !== false
    const permDisc = !p || p.disc !== false
    const permGaokao = !p || p.gaokao !== false
    const permAiHub = (!p || p.aiHub !== false) && !hideAi
    this.setData({
      permFace,
      permMbti,
      permSbti,
      permPdp,
      permDisc,
      permGaokao,
      permAiHub,
      allTestsDisabled: p && !permMbti && !permSbti && !permPdp && !permDisc && !permGaokao
    })
  },

  _trackSelect(type) {
    try { require('../../utils/analytics').track('tap_test_select', { type }) } catch (e) {}
  },

  goMBTI() {
    this._trackSelect('mbti')
    wx.navigateTo({ url: '/pages/test/mbti' })
  },

  goSBTI() {
    this._trackSelect('sbti')
    wx.navigateTo({ url: '/pages/test/sbti' })
  },

  goPDP() {
    this._trackSelect('pdp')
    wx.navigateTo({ url: '/pages/test/pdp' })
  },

  goDISC() {
    this._trackSelect('disc')
    wx.navigateTo({ url: '/pages/test/disc' })
  },

  goGaokaoHub() {
    if (!this.data.permGaokao) {
      wx.showToast({ title: '当前企业未开放高考志愿功能', icon: 'none' })
      return
    }
    try {
      require('../../utils/analytics').track('tap_test_select_gaokao', {})
    } catch (e) {}
    wx.navigateTo({ url: '/pages/gaokao/index' })
  },

  goAIChatInterpretation() {
    if (isAuditHideAiMode(getApp().globalData)) {
      wx.showToast({ title: '功能升级中', icon: 'none' })
      return
    }
    this._trackSelect('ai_chat')
    wx.navigateTo({ url: '/pages/ai-chat/index?src=test_select' })
  },

  goAIFaceAnalysis() {
    this._trackSelect('ai_face')
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
      title: '问卷 + AI 测评，发现你的性格类型',
      path: getSharePath('/pages/test-select/index')
    }
  },

  onShareTimeline() {
    const { buildShareQuery } = require('../../utils/share')
    return {
      title: '问卷 + AI 测评，发现你的性格类型',
      query: buildShareQuery()
    }
  }
})
