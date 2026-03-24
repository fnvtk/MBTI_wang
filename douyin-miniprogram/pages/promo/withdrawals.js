// pages/promo/withdrawals.js - 提现记录列表页（抖音版）
// 抖音没有 requestMerchantTransfer，提现确认走服务端处理
const { request } = require('../../utils/request')

Page({
  data: {
    list: [],
    page: 1,
    pageSize: 10,
    total: 0,
    loading: false,
    finished: false,
    confirmingId: null
  },

  onLoad() {
    this.loadData(true)
  },

  loadData(reset = false) {
    if (this.data.loading) return
    if (!reset && this.data.finished) return

    const page = reset ? 1 : this.data.page
    this.setData({ loading: true })

    request({
      url: `/api/distribution/withdrawals?page=${page}&pageSize=${this.data.pageSize}`,
      method: 'GET',
      success: (res) => {
        const payload = res && res.data
        if (payload && payload.code === 200 && payload.data) {
          const { list = [], total = 0 } = payload.data
          const mapped = list.map(item => {
            const statusCode = this._normalizeStatusCode(item.status)
            const amountYuan = item.amountYuan != null
              ? item.amountYuan
              : (item.amountFen != null ? (item.amountFen / 100).toFixed(2) : '')

            const balanceAfterYuan = item.balanceAfterYuan != null
              ? item.balanceAfterYuan
              : (item.balanceAfterFen != null ? (item.balanceAfterFen / 100).toFixed(2) : '')

            const feeYuan = item.feeYuan != null
              ? item.feeYuan
              : (item.feeFen != null ? (item.feeFen / 100).toFixed(2) : '0.00')

            return {
              ...item,
              statusCode,
              amountYuan,
              balanceAfterYuan,
              feeYuan,
              applyAtStr: this._fmtTime(item.createdAt),
              handleAtStr: this._fmtTime(item.transferAt),
              statusTagText: this._statusTagText(statusCode),
              statusTagClass: this._statusTagClass(statusCode)
            }
          })
          const newList = reset ? mapped : [...this.data.list, ...mapped]
          this.setData({
            list: newList,
            total,
            page: page + 1,
            finished: newList.length >= total
          })
        }
      },
      complete: () => {
        this.setData({ loading: false })
      }
    })
  },

  _fmtTime(ts) {
    if (!ts) return ''
    const d = new Date(ts * 1000)
    if (isNaN(d.getTime())) return ''
    const y = d.getFullYear()
    const m = String(d.getMonth() + 1).padStart(2, '0')
    const day = String(d.getDate()).padStart(2, '0')
    const hh = String(d.getHours()).padStart(2, '0')
    const mm = String(d.getMinutes()).padStart(2, '0')
    return `${y}-${m}-${day} ${hh}:${mm}`
  },

  _normalizeStatusCode(status) {
    if (typeof status === 'number') return status
    if (typeof status === 'string') {
      const s = status.trim()
      if (/^\d+$/.test(s)) return parseInt(s, 10)
      switch (s) {
        case 'pending':
        case '审核中':
          return 0
        case 'rejected':
        case '已驳回':
          return 1
        case '待收款':
          return 2
        case 'transferred':
        case '已收款':
          return 3
        case '已过期':
          return 4
        default:
          return 0
      }
    }
    return 0
  },

  _statusTagText(code) {
    switch (code) {
      case 0: return '待审核'
      case 1: return '已驳回'
      case 2: return '待收款'
      case 3: return '已收款'
      case 4: return '已过期'
      default: return ''
    }
  },

  _statusTagClass(code) {
    switch (code) {
      case 0: return 'tag-pending'
      case 1: return 'tag-rejected'
      case 2: return 'tag-wait'
      case 3: return 'tag-transferred'
      case 4: return 'tag-expired'
      default: return ''
    }
  },

  /**
   * 确认收款（抖音版）
   * 抖音没有 requestMerchantTransfer，通过服务端直接处理转账到用户支付宝/银行卡
   * 用户点击后向后端发请求确认
   */
  handleConfirmReceipt(e) {
    const id = e.currentTarget.dataset.id
    if (!id || this.data.confirmingId === id) return

    const item = (this.data.list || []).find(x => String(x.id) === String(id))
    if (!item) {
      tt.showToast({ title: '记录不存在', icon: 'none' })
      return
    }

    this.setData({ confirmingId: id })
    tt.showLoading({ title: '确认收款中...' })

    request({
      url: '/api/distribution/withdrawals/confirm-receipt',
      method: 'POST',
      data: { id: item.id, platform: 'douyin' },
      success: (r) => {
        const p = r && r.data
        if (p && p.code === 200) {
          tt.showToast({ title: '确认成功', icon: 'success' })
          this.loadData(true)
        } else {
          tt.showToast({ title: p && p.message || '确认失败', icon: 'none' })
        }
      },
      fail: () => {
        tt.showToast({ title: '网络请求失败', icon: 'none' })
      },
      complete: () => {
        tt.hideLoading()
        this.setData({ confirmingId: null })
      }
    })
  },

  onReachBottom() {
    this.loadData(false)
  }
})
