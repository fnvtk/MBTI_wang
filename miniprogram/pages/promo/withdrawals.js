// pages/promo/withdrawals.js - 提现记录列表页
const { request } = require('../../utils/request')
const { afterReviewModeChecked } = require('../../utils/reviewModePromo.js')

Page({
  data: {
    list: [],
    page: 1,
    pageSize: 10,
    total: 0,
    loading: false,
    finished: false,
    // 确认收款按钮 loading 状态（按单条）
    confirmingId: null
  },

  onLoad() {
    afterReviewModeChecked(() => this.loadData(true))
  },

  // 加载提现记录列表（reset 为 true 时重置分页）
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
              // 申请时间：用 createdAt
              applyAtStr: this._fmtTime(item.createdAt),
              // 处理时间：用 transferAt（如有）
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

  // 时间戳格式化
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

  // 将后端 status（数字 / 中文 / 英文）统一转成数字枚举：
  // 0=审核中，1=已驳回，2=待收款，3=已收款，4=已过期
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

  // 状态中文文案
  _statusTagText(code) {
    switch (code) {
      case 0:
        return '待审核'
      case 1:
        return '已驳回'
      case 2:
        return '待收款'
      case 3:
        return '已收款'
      case 4:
        return '已过期'
      default:
        return ''
    }
  },

  // 顶部右侧小标签样式
  _statusTagClass(code) {
    switch (code) {
      case 0:
        return 'tag-pending'
      case 1:
        return 'tag-rejected'
      case 2:
        return 'tag-wait'
      case 3:
        return 'tag-transferred'
      case 4:
        return 'tag-expired'
      default:
        return ''
    }
  },

  // 用户点击单条「确认收款」
  handleConfirmReceipt(e) {
    const id = e.currentTarget.dataset.id
    if (!id || this.data.confirmingId === id) return

    const item = (this.data.list || []).find(x => String(x.id) === String(id))
    if (!item) {
      wx.showToast({ title: '记录不存在', icon: 'none' })
      return
    }

    // 兼容驼峰 / 下划线字段
    const packageInfo = item.packageInfo || item.package_info
    if (!packageInfo) {
      wx.showToast({ title: '该提现单无法确认收款，请联系客服', icon: 'none' })
      return
    }

    if (!wx.canIUse || !wx.canIUse('requestMerchantTransfer')) {
      wx.showModal({
        content: '当前微信版本过低，无法使用确认收款功能，请升级微信后重试',
        showCancel: false
      })
      return
    }

    this.setData({ confirmingId: id })

    wx.showLoading({ title: '正在调起确认收款...' })

    // 获取 AppID：优先记录里的，其次运行时获取
    let appId = item.appId || item.app_id
    try {
      if (!appId && wx.getAccountInfoSync) {
        const info = wx.getAccountInfoSync()
        appId = info && info.miniProgram && info.miniProgram.appId
      }
    } catch (err) {}

    // 商户号：从记录中取（后端已写入 mch_id）
    const mchId = item.mchId || item.mch_id

    wx.requestMerchantTransfer({
      mchId,
      appId,
      // 文档里字段名是 package，对应后台返回的 package_info
      package: packageInfo,
      success: (res) => {
        console.log('requestMerchantTransfer success', res)
        wx.showToast({ title: '已调起收款确认', icon: 'none', duration: 2000 })
        // 主动查询转账状态并更新订单，实现及时刷新（参考商户单号查询：https://pay.weixin.qq.com/doc/v3/merchant/4012716437）
        request({
          url: '/api/distribution/withdrawals/query-transfer',
          method: 'POST',
          data: { id: item.id },
          success: (r) => {
            const p = r && r.data
            if (p && p.code === 200 && p.data && p.data.status === 3) {
              wx.showToast({ title: '已收款', icon: 'success' })
            }
          },
          complete: () => {
            this.loadData(true)
          }
        })
      },
      fail: (err) => {
        console.error('requestMerchantTransfer fail', err)
        wx.showToast({ title: '调起失败，请稍后重试', icon: 'none' })
      },
      complete: () => {
        wx.hideLoading()
        this.setData({ confirmingId: null })
      }
    })
  },

  onReachBottom() {
    this.loadData(false)
  }
})

