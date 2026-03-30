// pages/test-select/index.js
const app = getApp()

Page({
  data: {
    permFace: true,
    permMbti: true,
    permPdp: true,
    permDisc: true
  },

  onLoad() {
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
      permPdp:  !p || p.pdp  !== false,
      permDisc: !p || p.disc !== false
    })
  },

  goMBTI() {
    tt.navigateTo({ url: '/pages/test/mbti' })
  },

  goPDP() {
    tt.navigateTo({ url: '/pages/test/pdp' })
  },

  goDISC() {
    tt.navigateTo({ url: '/pages/test/disc' })
  }
})
