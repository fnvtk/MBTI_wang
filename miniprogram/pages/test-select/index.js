// pages/test-select/index.js
const app = getApp()

Page({
  data: {
    permFace: true,
    permMbti: true,
    permSbti: true,
    permPdp: true,
    permDisc: true,
    /** 四类入口均被企业权限关闭时提示，避免误以为白屏 */
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
    this._syncPerms()
  },

  _syncPerms() {
    const p = app.globalData.enterprisePermissions
    const permFace = !p || p.face !== false
    const permMbti = !p || p.mbti !== false
    const permSbti = !p || p.sbti !== false
    const permPdp = !p || p.pdp !== false
    const permDisc = !p || p.disc !== false
    this.setData({
      permFace,
      permMbti,
      permSbti,
      permPdp,
      permDisc,
      allTestsDisabled: p && !permMbti && !permSbti && !permPdp && !permDisc
    })
  },

  goMBTI() {
    wx.navigateTo({ url: '/pages/test/mbti' })
  },

  goSBTI() {
    wx.navigateTo({ url: '/pages/test/sbti' })
  },

  goPDP() {
    wx.navigateTo({ url: '/pages/test/pdp' })
  },

  goDISC() {
    wx.navigateTo({ url: '/pages/test/disc' })
  },

  onShareAppMessage() {
    const { getSharePath } = require('../../utils/share')
    return {
      title: '4 大详细性格测试，来测测你的性格类型',
      path: getSharePath('/pages/test-select/index')
    }
  },

  onShareTimeline() {
    const { buildShareQuery } = require('../../utils/share')
    return {
      title: '4 大详细性格测试，来测测你的性格类型',
      query: buildShareQuery()
    }
  }
})
