// 神仙 AI · 深度画像报告页
const { request } = require('../../utils/request.js')
const analytics = require('../../utils/analytics.js')

Page({
  data: {
    cid: 0,           // 来源对话 id
    reportId: 0,      // 当前报告 id
    report: {},       // 报告对象
    paying: false,
    pollTimer: null
  },

  onLoad(options) {
    const cid = parseInt(options && options.cid, 10) || 0
    const rid = parseInt(options && options.rid, 10) || 0
    this.setData({ cid, reportId: rid })
    analytics.track('page_view', { pagePath: 'pages/ai-chat/report' })
    this.initReport()
  },

  onUnload() {
    if (this.data.pollTimer) clearTimeout(this.data.pollTimer)
  },

  /** 初始化：有 rid 直接拉；否则先 createOrGetPending */
  initReport() {
    if (this.data.reportId > 0) {
      this.loadReport(this.data.reportId)
      return
    }
    request({
      url: '/api/ai/report/create',
      method: 'POST',
      data: { conversationId: this.data.cid },
      success: (res) => {
        const body = (res && res.data) || {}
        if (body.code !== 200) {
          wx.showToast({ title: body.message || '创建失败', icon: 'none' })
          return
        }
        const r = body.data || {}
        this.setData({ reportId: r.id, report: r }, () => {
          if (r.status === 'generating' || r.status === 'paid') this._schedulePoll()
        })
      }
    })
  },

  loadReport(id) {
    request({
      url: `/api/ai/report/${id}`,
      method: 'GET',
      success: (res) => {
        const body = (res && res.data) || {}
        if (body.code !== 200) {
          wx.showToast({ title: body.message || '加载失败', icon: 'none' })
          return
        }
        this.setData({ report: body.data || {} }, () => {
          const s = this.data.report.status
          if (s === 'generating' || s === 'paid') this._schedulePoll()
        })
      }
    })
  },

  _schedulePoll() {
    if (this.data.pollTimer) clearTimeout(this.data.pollTimer)
    const t = setTimeout(() => {
      this.loadReport(this.data.reportId)
    }, 3500)
    this.setData({ pollTimer: t })
  },

  reload() {
    this.loadReport(this.data.reportId)
  },

  /** 付费解锁：先尝试 dev 模式，生产环境走 payment/create 下单 */
  onTapPay() {
    if (this.data.paying) return
    this.setData({ paying: true })
    analytics.track('ai_report_pay_tap', { reportId: this.data.reportId })

    const report = this.data.report || {}
    const orderSn = report.orderSn || ''

    // 1) 尝试调用现有 /api/payment/create 下单
    request({
      url: '/api/payment/create',
      method: 'POST',
      data: {
        productType: 'ai_deep_report',
        productId: this.data.reportId,
        amount: report.priceFen || 990,
        orderSn
      },
      success: (res) => {
        const body = (res && res.data) || {}
        if (body.code === 200 && body.data && body.data.timeStamp) {
          const p = body.data
          wx.requestPayment({
            timeStamp: p.timeStamp,
            nonceStr: p.nonceStr,
            package: p.package,
            signType: p.signType || 'RSA',
            paySign: p.paySign,
            success: () => {
              wx.showToast({ title: '支付成功', icon: 'success' })
              analytics.track('ai_report_pay_success', { reportId: this.data.reportId })
              setTimeout(() => this.loadReport(this.data.reportId), 1500)
              this.setData({ paying: false })
            },
            fail: () => {
              wx.showToast({ title: '已取消支付', icon: 'none' })
              this.setData({ paying: false })
            }
          })
        } else {
          // 2) 下单失败（比如接口不支持该 productType）→ 兜底 dev markPaid（仅超管可用）
          this._fallbackDevMarkPaid()
        }
      },
      fail: () => this._fallbackDevMarkPaid()
    })
  },

  _fallbackDevMarkPaid() {
    // 仅在配置了 dev 模式或超管账号时生效
    request({
      url: `/api/ai/report/${this.data.reportId}/mark-paid-dev`,
      method: 'POST',
      success: (res) => {
        const body = (res && res.data) || {}
        if (body.code === 200) {
          wx.showToast({ title: '解锁成功', icon: 'success' })
          this.setData({ paying: false })
          setTimeout(() => this.loadReport(this.data.reportId), 800)
        } else {
          wx.showToast({ title: body.message || '支付接口未开通', icon: 'none' })
          this.setData({ paying: false })
        }
      },
      fail: () => {
        wx.showToast({ title: '支付通道异常，稍后再试', icon: 'none' })
        this.setData({ paying: false })
      }
    })
  },

  onRetry() {
    if (!this.data.reportId) return
    request({
      url: `/api/ai/report/${this.data.reportId}/regenerate`,
      method: 'POST',
      success: (res) => {
        const body = (res && res.data) || {}
        if (body.code === 200) {
          this.setData({ report: body.data || {} })
          const s = body.data && body.data.status
          if (s === 'generating' || s === 'paid') this._schedulePoll()
        } else {
          wx.showToast({ title: body.message || '重试失败', icon: 'none' })
        }
      }
    })
  },

  onCopy() {
    const c = (this.data.report && this.data.report.content) || ''
    if (!c) return
    wx.setClipboardData({ data: c })
  },

  onShareAppMessage() {
    const app = getApp() || {}
    const gd = app.globalData || {}
    const inviterId = (gd.userInfo && (gd.userInfo.id || gd.userInfo.user_id)) || 0
    const mbti = (this.data.report && this.data.report.mbtiType) || ''
    const title = mbti
      ? `神仙 AI 帮我看懂了 ${mbti}，推荐你也来做一份专属报告`
      : '神仙 AI · 深度画像报告'
    analytics.track('ai_report_share', { reportId: this.data.reportId, mbti })
    return {
      title,
      path: `/pages/ai-chat/index?inviterId=${inviterId}&src=ai_report_share`,
    }
  },

  onShareTimeline() {
    return { title: '神仙 AI 帮我看懂了自己的 MBTI', query: 'src=ai_report_timeline' }
  }
})
