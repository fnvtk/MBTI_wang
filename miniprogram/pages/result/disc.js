// pages/result/disc.js - DISC结果页（支持付费墙 + 历史详情拉取）
const app = getApp()
const payment = require('../../utils/payment')
const { getTypeOnly } = require('../../utils/resultFormat')
const { isReportProfileComplete } = require('../../utils/phoneAuth.js')

function toProfileLockedDisc(full) {
  if (!full) return full
  return { dominantType: full.dominantType || full.disc || '', locked: true }
}

function toIntPercent(v) {
  if (v == null) return 0
  const n = typeof v === 'number' ? v : Number(v)
  return Number.isFinite(n) ? Math.round(n) : 0
}

function withPercentagesInt(data) {
  if (!data) return data
  const p = data.percentages || {}
  const out = { ...data }
  out.percentagesInt = {
    D: toIntPercent(p.D ?? p.d),
    I: toIntPercent(p.I ?? p.i),
    S: toIntPercent(p.S ?? p.s),
    C: toIntPercent(p.C ?? p.c)
  }
  return out
}

Page({
  data: {
    result: null,
    /** 主+次高权重展示（如 S+I型），与接口摘要一致 */
    typeSummaryLine: '',
    typeList: [
      { type: 'D', label: 'D型 - 力量', colorClass: 'fill-d' },
      { type: 'I', label: 'I型 - 活跃', colorClass: 'fill-i' },
      { type: 'S', label: 'S型 - 和平', colorClass: 'fill-s' },
      { type: 'C', label: 'C型 - 完美', colorClass: 'fill-c' }
    ],
    payInfo: { requiresPayment: false, isPaid: false, amountYuan: 0 },
    testResultId: null,
    shareToken: '',
    hasReloadedAfterPay: false,
  },

  onLoad(options) {
    const id = options && options.id
    const type = options && options.type
    if (id && type === 'disc') {
      this.setData({ testResultId: id })
      if (options.st) {
        this.loadShareDetail(id, options.st)
      } else {
        this.loadDetail(id)
      }
      return
    }
    const raw = wx.getStorageSync('discResult')
    if (raw) {
      const gated = isReportProfileComplete() ? raw : toProfileLockedDisc(raw)
      const r = withPercentagesInt(gated)
      this.setData({ result: r, typeSummaryLine: getTypeOnly(gated, 'disc') })
      this.initPayInfoFromRuntime('disc')
    } else {
      wx.showToast({ title: '暂无测试结果', icon: 'none' })
      setTimeout(() => wx.navigateBack(), 1500)
    }
  },

  applyDetailPayload(payload) {
    const data = payload.data || payload
    const isPaid = !!payload.isPaid
    const paidAmount = payload.paidAmount != null ? Number(payload.paidAmount) : 0
    const amountYuan = payload.amountYuan != null ? Number(payload.amountYuan) : (paidAmount > 0 ? paidAmount / 100 : 0)
    const needPaymentToUnlock = payload.needPaymentToUnlock === true || (!!payload.requiresPayment && !isPaid && paidAmount > 0)
    const r = withPercentagesInt(data)
    this.setData({
      result: r,
      typeSummaryLine: getTypeOnly(data, 'disc'),
      shareToken: payload.shareToken || ''
    })
    const payInfo = {
      requiresPayment: needPaymentToUnlock,
      isPaid,
      amountYuan: needPaymentToUnlock ? amountYuan : 0
    }
    this.setData({ payInfo })
  },

  loadDetail(id) {
    const apiBase = app.globalData?.apiBase || ''
    const token = app.globalData?.token || wx.getStorageSync('token') || ''
    if (!apiBase) { wx.showToast({ title: '配置异常', icon: 'none' }); return }
    wx.showLoading({ title: '加载中...' })
    wx.request({
      url: `${apiBase}/api/test/detail`,
      method: 'GET',
      header: token ? { Authorization: `Bearer ${token}` } : {},
      data: { id },
      success: (res) => {
        if (res.statusCode === 200 && res.data && res.data.code === 200) {
          this.applyDetailPayload(res.data.data || {})
        } else {
          wx.showToast({ title: res.data?.message || '加载失败', icon: 'none' })
        }
      },
      fail: () => wx.showToast({ title: '网络错误', icon: 'none' }),
      complete: () => wx.hideLoading()
    })
  },

  loadShareDetail(id, st) {
    const apiBase = app.globalData?.apiBase || ''
    if (!apiBase) { wx.showToast({ title: '配置异常', icon: 'none' }); return }
    wx.showLoading({ title: '加载中...' })
    wx.request({
      url: `${apiBase}/api/test/share-detail`,
      method: 'GET',
      data: { id, st },
      success: (res) => {
        if (res.statusCode === 200 && res.data && res.data.code === 200) {
          this.applyDetailPayload(res.data.data || {})
        } else {
          wx.showToast({ title: res.data?.message || '分享链接无效或已失效', icon: 'none' })
        }
      },
      fail: () => wx.showToast({ title: '网络错误', icon: 'none' }),
      complete: () => wx.hideLoading()
    })
  },

  onShow() {
    if (this.data.testResultId) return
    const raw = wx.getStorageSync('discResult')
    if (!raw) return
    const gated = isReportProfileComplete() ? raw : toProfileLockedDisc(raw)
    const r = withPercentagesInt(gated)
    this.setData({ result: r, typeSummaryLine: getTypeOnly(gated, 'disc') })
  },

  goCompleteProfile() {
    wx.navigateTo({ url: '/pages/user-profile/index' })
  },

  initPayInfoFromRuntime(testType) {
    app.getRuntimeConfig()
      .then((cfg) => {
        const reportRequires = cfg.reportRequiresPayment || {}
        const pricing = cfg.pricing || {}
        const requiresPayment = !!(reportRequires && reportRequires[testType])
        const amountYuan = Number(pricing[testType]) || (requiresPayment ? 1.98 : 0)
        this.setData({
          payInfo: { requiresPayment, isPaid: false, amountYuan }
        })
      })
      .catch(() => this.setData({ payInfo: { requiresPayment: false, isPaid: false, amountYuan: 0 } }))
  },

  unlockFullReport() {
    const { payInfo, testResultId, hasReloadedAfterPay } = this.data
    if (!payInfo.requiresPayment || payInfo.isPaid) return
    app.ensureLogin && app.ensureLogin().then((logged) => {
      if (!logged) { wx.showToast({ title: '请先登录', icon: 'none' }); return }
      payment.purchaseDiscTest({
        testResultId: testResultId || undefined,
        success: () => {
          wx.showToast({ title: '已解锁完整报告', icon: 'success' })
          this.setData({ 'payInfo.isPaid': true })
          if (testResultId && !hasReloadedAfterPay) {
            this.setData({ hasReloadedAfterPay: true })
            setTimeout(() => this.loadDetail(testResultId), 500)
          }
        },
        fail: () => {}
      })
    })
  },

  retakeTest() {
    if (!this.data.testResultId) {
      wx.removeStorageSync('discResult')
    }
    wx.navigateTo({ url: '/pages/test/disc' })
  },

  goHome() {
    const scope = (getApp().globalData && getApp().globalData.appScope) || 'personal'
    if (scope === 'enterprise') {
      wx.navigateTo({ url: '/pages/enterprise/index' })
    } else {
      wx.switchTab({ url: '/pages/index/index' })
    }
  },

  onShareAppMessage() {
    const result = this.data.result
    const { getResultSharePath } = require('../../utils/share')
    return {
      title: `我的DISC类型是${result?.dominantType}型（${result?.description?.title}），来测测你的吧！`,
      path: getResultSharePath('/pages/result/disc', {
        id: this.data.testResultId,
        type: 'disc',
        shareToken: this.data.shareToken
      })
    }
  },

  onShareTimeline() {
    const result = this.data.result
    const { getResultShareTimelineQuery } = require('../../utils/share')
    return {
      title: `我的DISC类型是${result?.dominantType}型（${result?.description?.title}），来测测你的吧！`,
      query: getResultShareTimelineQuery({
        id: this.data.testResultId,
        type: 'disc',
        shareToken: this.data.shareToken
      })
    }
  }
})
