// pages/test-select/index.js
const app = getApp()

Page({
  data: {
    permFace: true,
    permMbti: true,
    permSbti: true,
    permPdp: true,
    permDisc: true
  },

  onLoad(options) {
    try {
      require('../../utils/thirdPartyContext.js').ingestThirdPartyOnPageLoad(options || {}, app)
    } catch (e) {}
    this._syncPerms()
  },

  onShow() {
    this._syncPerms()
  },

  _syncPerms() {
    const p = app.globalData.enterprisePermissions
    this.setData({
      permFace: !p || p.face !== false,
      permMbti: !p || p.mbti !== false,
      permSbti: !p || p.sbti !== false,
      permPdp:  !p || p.pdp  !== false,
      permDisc: !p || p.disc !== false
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
  }
})
