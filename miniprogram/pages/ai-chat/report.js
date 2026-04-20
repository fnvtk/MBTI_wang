// 神仙 AI · 深度画像报告页
const { request } = require('../../utils/request.js')
const analytics = require('../../utils/analytics.js')
const { wxPay } = require('../../utils/payment.js')
const { getEnterpriseIdForApiPayload } = require('../../utils/enterpriseContext.js')

Page({
  data: {
    cid: 0,           // 来源对话 id
    reportId: 0,      // 当前报告 id
    report: {},       // 报告对象
    paying: false,
    pollTimer: null
  },

  onLoad(options) {
    const { ensureRuntimeThenGate } = require('../../utils/miniprogramAuditGate.js')
    ensureRuntimeThenGate(() => {
      const cid = parseInt(options && options.cid, 10) || 0
      const rid = parseInt(options && options.rid, 10) || 0
      this.setData({ cid, reportId: rid })
      analytics.track('page_view', { pagePath: 'pages/ai-chat/report' })
      this.initReport()
    })
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

  /** 付费解锁：与全站 payment.js 一致（orderId + openId + 支付后轮询查单触发 markPaid） */
  onTapPay() {
    if (this.data.paying) return
    const report = this.data.report || {}
    const orderSn = String(report.orderSn || '').trim()
    if (!orderSn) {
      wx.showToast({ title: '订单初始化中，请稍后再试', icon: 'none' })
      return
    }

    const runPay = () => {
      const appInst = getApp()
      const oid =
        (appInst && appInst.globalData && appInst.globalData.openId) ||
        (() => {
          try {
            const u = wx.getStorageSync('userInfo')
            return u ? (u.openid || u.openId || '') : ''
          } catch (e) {
            return ''
          }
        })()
      if (!oid) {
        wx.showToast({ title: '缺少微信支付授权，请重新登录后再试', icon: 'none' })
        return
      }

      this.setData({ paying: true })
      analytics.track('ai_report_pay_tap', { reportId: this.data.reportId })

      const eidRaw = getEnterpriseIdForApiPayload()
      const enterpriseId = eidRaw != null && Number(eidRaw) > 0 ? Number(eidRaw) : 0
      const amountFen = Number(report.priceFen) > 0 ? Number(report.priceFen) : 990

      wxPay({
        orderId: orderSn,
        amount: amountFen,
        description: '神仙 AI 深度画像报告',
        productType: 'ai_deep_report',
        enterpriseId,
        success: () => {
          this.setData({ paying: false })
          analytics.track('ai_report_pay_success', { reportId: this.data.reportId })
          setTimeout(() => this.loadReport(this.data.reportId), 800)
        },
        fail: () => {
          this.setData({ paying: false })
        }
      })
    }

    const app = getApp()
    if (app && typeof app.ensureLogin === 'function') {
      app.ensureLogin().then((ok) => {
        if (ok) runPay()
        else wx.showToast({ title: '请先登录后再购买', icon: 'none' })
      })
      return
    }
    runPay()
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
