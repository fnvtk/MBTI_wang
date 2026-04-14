// pages/test-select/index.js
const app = getApp()

Page({
  data: {
    permFace: true,
    permMbti: true,
    permPdp: true,
    permDisc: true,
    allTestsDisabled: false
  },

  onLoad() {
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
    const permPdp = !p || p.pdp !== false
    const permDisc = !p || p.disc !== false
    this.setData({
      permFace,
      permMbti,
      permPdp,
      permDisc,
      allTestsDisabled: p && !permMbti && !permPdp && !permDisc
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
