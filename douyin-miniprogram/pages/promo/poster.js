const app = getApp()
const { getApiBase } = require('../../utils/request')

Page({
  data: {
    loading: true,
    loadingText: '正在生成海报...',
    posterUrl: ''
  },

  onLoad() {
    app.ensureLogin()
      .then((ok) => {
        if (!ok) {
          this.setData({ loading: false, loadingText: '请先登录后生成海报' })
          tt.showToast({ title: '请先登录', icon: 'none' })
          return
        }
        this.loadPoster()
      })
      .catch(() => {
        this.setData({ loading: false, loadingText: '登录失败，请重试' })
      })
  },

  /** 从后端接口下载完整合成海报 */
  loadPoster() {
    const app = getApp()
    const gd = app.globalData || {}
    const storedUser = tt.getStorageSync('userInfo') || {}
    const userInfo = gd.userInfo || storedUser
    const apiBase = getApiBase()
    const token = tt.getStorageSync('token') || gd.token || ''
    const scope = gd.appScope || 'personal'
    // 企业 ID：按优先级取 scene > globalData.userInfo > storage.userInfo
    const eid = scope === 'enterprise'
      ? (gd.enterpriseIdFromScene || (gd.userInfo && gd.userInfo.enterpriseId) || storedUser.enterpriseId || null)
      : null
    let url = apiBase.replace(/\/$/, '') + '/api/distribution/poster'
    if (eid) url += `?eid=${eid}&scope=enterprise`
    else if (scope === 'enterprise') url += '?scope=enterprise'  // 企业模式但 eid 未知，后端从 DB 取
    else url += '?scope=personal'

    tt.downloadFile({
      url,
      header: token ? { Authorization: 'Bearer ' + token } : {},
      success: (res) => {
        if (res.statusCode === 200 && res.tempFilePath) {
          this.setData({ posterUrl: res.tempFilePath, loading: false })
        } else {
          this.setData({ loadingText: '海报生成失败', loading: false })
          tt.showToast({ title: '生成失败', icon: 'none' })
        }
      },
      fail: () => {
        this.setData({ loadingText: '请求失败，请重试', loading: false })
        tt.showToast({ title: '请求失败', icon: 'none' })
      }
    })
  },

  /** 保存到相册 */
  savePoster() {
    if (this.data.loading || !this.data.posterUrl) return
    tt.showLoading({ title: '正在保存...' })
    tt.saveImageToPhotosAlbum({
      filePath: this.data.posterUrl,
      success: () => {
        tt.hideLoading()
        tt.showToast({ title: '已保存到相册', icon: 'success' })
      },
      fail: (err) => {
        tt.hideLoading()
        if (err.errMsg && err.errMsg.indexOf('auth deny') >= 0) {
          tt.showModal({
            title: '提示',
            content: '需要您授权保存图片到相册',
            success: (r) => { if (r.confirm) tt.openSetting() }
          })
        } else {
          tt.showToast({ title: '保存失败', icon: 'none' })
        }
      }
    })
  }
})
