const analytics = require('../../utils/analytics.js')

Page({
  data: { url: '', loadedAt: 0 },
  onLoad(options) {
    let url = decodeURIComponent(options && options.url || '')
    if (!url) {
      wx.showToast({ title: '链接为空', icon: 'none' })
      return
    }
    // 仅允许 http/https
    if (!/^https?:\/\//.test(url)) {
      wx.showToast({ title: '链接非法', icon: 'none' })
      return
    }
    this.setData({ url, loadedAt: Date.now() })
    if (options && options.title) {
      wx.setNavigationBarTitle({ title: decodeURIComponent(options.title) })
    }
  },
  onLoad2() {},
  onError() {
    wx.showToast({ title: '页面加载失败', icon: 'none' })
  },
  onUnload() {
    const readMs = Date.now() - (this.data.loadedAt || Date.now())
    if (readMs > 5000) {
      analytics.track('ai_article_read', { readMs })
    }
  }
})
