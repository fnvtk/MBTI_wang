// pages/order/index.js - 我的订单（支付记录）
const app = getApp()
const { request } = require('../../utils/request')

Page({
  data: {
    list: [],
    total: 0,
    page: 1,
    pageSize: 20,
    hasMore: false,
    loading: false,
    empty: false
  },

  onLoad() {
    this.loadList(true)
  },

  onPullDownRefresh() {
    this.loadList(true)
      .then(() => wx.stopPullDownRefresh())
      .catch(() => wx.stopPullDownRefresh())
  },

  loadList(reset) {
    const token = app.globalData.token || wx.getStorageSync('token')
    if (!token) {
      wx.showToast({ title: '请先登录', icon: 'none' })
      return Promise.resolve()
    }

    const page = reset ? 1 : this.data.page
    if (this.data.loading) return Promise.resolve()
    this.setData({ loading: true })

    return new Promise((resolve) => {
      request({
        url: `/api/orders?page=${page}&pageSize=${this.data.pageSize}`,
        method: 'GET',
        needAuth: true,
        success: (res) => {
          const body = res.data
          if (res.statusCode === 200 && body && body.code === 200 && body.data) {
            const d = body.data
            const raw = Array.isArray(d.list) ? d.list : []
            const list = reset ? raw : this.data.list.concat(raw)
            this.setData({
              list,
              total: d.total || 0,
              page: d.page || page,
              hasMore: !!d.hasMore,
              empty: list.length === 0,
              loading: false
            })
          } else {
            this.setData({
              list: reset ? [] : this.data.list,
              empty: reset && (!this.data.list || this.data.list.length === 0),
              loading: false
            })
            if (body && body.message) wx.showToast({ title: body.message, icon: 'none' })
          }
          resolve()
        },
        fail: () => {
          this.setData({ loading: false })
          wx.showToast({ title: '加载失败', icon: 'none' })
          resolve()
        }
      })
    })
  },

  loadMore() {
    if (!this.data.hasMore || this.data.loading) return
    this.setData({ page: this.data.page + 1 }, () => {
      this.loadList(false)
    })
  }
})
