// pages/result/pdp.js - PDP结果页（支持付费墙 + 历史详情拉取）
const app = getApp()
const payment = require('../../utils/payment')

const PDP_KEYS = ['Tiger', 'Peacock', 'Koala', 'Owl', 'Chameleon']

function toIntPercent(v) {
  if (v == null) return 0
  const n = typeof v === 'number' ? v : Number(v)
  return Number.isFinite(n) ? Math.round(n) : 0
}

function withPercentagesInt(data) {
  if (!data) return data
  const p = data.percentages || {}
  const out = { ...data }
  const ints = {}
  PDP_KEYS.forEach((k) => { ints[k] = toIntPercent(p[k] ?? p[k.toLowerCase()]) })
  out.percentagesInt = ints
  return out
}

Page({
  data: {
    result: null,
    typeList: [
      { type: 'Tiger', emoji: '🐅', label: '老虎型', colorClass: 'fill-tiger' },
      { type: 'Peacock', emoji: '🦚', label: '孔雀型', colorClass: 'fill-peacock' },
      { type: 'Koala', emoji: '🐨', label: '考拉型', colorClass: 'fill-koala' },
      { type: 'Owl', emoji: '🦉', label: '猫头鹰型', colorClass: 'fill-owl' },
      { type: 'Chameleon', emoji: '🦎', label: '变色龙型', colorClass: 'fill-chameleon' }
    ],
    payInfo: { requiresPayment: false, isPaid: false, amountYuan: 0 },
    testResultId: null,
    hasReloadedAfterPay: false
  },

  onLoad(options) {
    const id = options && options.id
    const type = options && options.type
    if (id && type === 'pdp') {
      this.setData({ testResultId: id })
      this.loadDetail(id)
      return
    }
    const result = tt.getStorageSync('pdpResult')
    if (result) {
      this.setData({ result: withPercentagesInt(result) })
      this.initPayInfoFromRuntime('pdp')
    } else {
      tt.showToast({ title: '暂无测试结果', icon: 'none' })
      setTimeout(() => tt.navigateBack(), 1500)
    }
  },

  loadDetail(id) {
    const apiBase = app.globalData?.apiBase || ''
    const token = app.globalData?.token || tt.getStorageSync('token') || ''
    if (!apiBase) { tt.showToast({ title: '配置异常', icon: 'none' }); return }
    tt.showLoading({ title: '加载中...' })
    tt.request({
      url: `${apiBase}/api/test/detail`,
      method: 'GET',
      header: token ? { Authorization: `Bearer ${token}` } : {},
      data: { id },
      success: (res) => {
        if (res.statusCode === 200 && res.data && res.data.code === 200) {
          const payload = res.data.data || {}
          const data = payload.data || payload
          const isPaid = !!payload.isPaid
          const paidAmount = payload.paidAmount != null ? Number(payload.paidAmount) : 0
          const amountYuan = payload.amountYuan != null ? Number(payload.amountYuan) : (paidAmount > 0 ? paidAmount / 100 : 0)
          const needPaymentToUnlock = payload.needPaymentToUnlock === true || (!!payload.requiresPayment && !isPaid && paidAmount > 0)
          this.setData({ result: withPercentagesInt(data) })
          const payInfo = {
            requiresPayment: needPaymentToUnlock,
            isPaid,
            amountYuan: needPaymentToUnlock ? amountYuan : 0
          }
          this.setData({ payInfo })
        } else {
          tt.showToast({ title: res.data?.message || '加载失败', icon: 'none' })
        }
      },
      fail: () => tt.showToast({ title: '网络错误', icon: 'none' }),
      complete: () => tt.hideLoading()
    })
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
      if (!logged) { tt.showToast({ title: '请先登录', icon: 'none' }); return }
      payment.purchasePdpTest({
        testResultId: testResultId || undefined,
        success: () => {
          tt.showToast({ title: '已解锁完整报告', icon: 'success' })
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
      tt.removeStorageSync('pdpResult')
    }
    tt.navigateTo({ url: '/pages/test/pdp' })
  },

  goHome() {
    const scope = (getApp().globalData && getApp().globalData.appScope) || 'personal'
    if (scope === 'enterprise') {
      tt.navigateTo({ url: '/pages/enterprise/index' })
    } else {
      tt.switchTab({ url: '/pages/index/index' })
    }
  },

  onShareAppMessage() {
    const result = this.data.result
    const { getSharePathByScope } = require('../../utils/share')
    return {
      title: `我的PDP类型是${result?.description?.type}${result?.description?.emoji}，来测测你的吧！`,
      path: getSharePathByScope('/pages/index/index')
    }
  },

  onShareTimeline() {
    const result = this.data.result
    const { buildShareQuery } = require('../../utils/share')
    return {
      title: `我的PDP类型是${result?.description?.type}${result?.description?.emoji}，来测测你的吧！`,
      query: buildShareQuery()
    }
  }
})
