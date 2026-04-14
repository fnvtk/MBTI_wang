// pages/result/pdp.js - PDP结果页（支持付费墙 + 历史详情拉取）
const app = getApp()
const payment = require('../../utils/payment')
const { getTypeOnly } = require('../../utils/resultFormat')
const { isReportProfileComplete } = require('../../utils/phoneAuth.js')

function toProfileLockedPdp(full) {
  if (!full) return full
  const desc = full.description || {}
  return {
    description: { type: desc.type || '', emoji: desc.emoji || '' },
    locked: true
  }
}

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
    typeSummaryLine: '',
    typeList: [
      { type: 'Tiger', emoji: '🐅', label: '老虎型', colorClass: 'fill-tiger' },
      { type: 'Peacock', emoji: '🦚', label: '孔雀型', colorClass: 'fill-peacock' },
      { type: 'Koala', emoji: '🐨', label: '无尾熊型', colorClass: 'fill-koala' },
      { type: 'Owl', emoji: '🦉', label: '猫头鹰型', colorClass: 'fill-owl' },
      { type: 'Chameleon', emoji: '🦎', label: '变色龙型', colorClass: 'fill-chameleon' }
    ],
    payInfo: { requiresPayment: false, isPaid: false, amountYuan: 0 },
    testResultId: null,
    shareToken: '',
    hasReloadedAfterPay: false,
    fromShare: false
  },

  onLoad(options) {
    const fromShareFs =
      options && (String(options.fs) === '1' || options.from === 'share')
    const id = options && options.id != null && options.id !== '' ? String(options.id) : ''
    const st = options && options.st ? String(options.st).trim() : ''
    const type = options && options.type ? String(options.type).toLowerCase() : ''

    if (id && type === 'pdp') {
      this.setData({ testResultId: id, fromShare: !!fromShareFs })
      this.loadShareDetail(id, st, 'pdp')
      return
    }
    if (id && st) {
      this.setData({ testResultId: id, fromShare: true })
      this.loadShareDetail(id, st, '')
      return
    }
    const result = wx.getStorageSync('pdpResult')
    if (result) {
      this.setData({
        result: withPercentagesInt(result),
        typeSummaryLine: getTypeOnly(result, 'pdp')
      })
      this.initPayInfoFromRuntime('pdp')
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
    this.setData({
      result: withPercentagesInt(data),
      typeSummaryLine: getTypeOnly(data, 'pdp'),
      shareToken: payload.shareToken || ''
    })
    const payInfo = {
      requiresPayment: needPaymentToUnlock,
      isPaid,
      amountYuan: needPaymentToUnlock ? amountYuan : 0
    }
    const patch = { payInfo }
    if (payload.id != null && payload.id !== '') {
      patch.testResultId = String(payload.id)
    }
    this.setData(patch)
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

  loadShareDetail(id, st, testType) {
    const apiBase = app.globalData?.apiBase || ''
    if (!apiBase) { wx.showToast({ title: '配置异常', icon: 'none' }); return }
    const data = { id: String(id) }
    if (st) data.st = st
    if (testType) data.type = testType
    wx.showLoading({ title: '加载中...' })
    wx.request({
      url: `${apiBase}/api/test/share-detail`,
      method: 'GET',
      data,
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
    const raw = wx.getStorageSync('pdpResult')
    if (!raw) return
    const gated = isReportProfileComplete() ? raw : toProfileLockedPdp(raw)
    this.setData({
      result: withPercentagesInt(gated),
      typeSummaryLine: getTypeOnly(gated, 'pdp')
    })
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
      payment.purchasePdpTest({
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
      wx.removeStorageSync('pdpResult')
    }
    wx.navigateTo({ url: '/pages/test/pdp' })
  },

  goWantTest() {
    wx.navigateTo({ url: '/pages/test/pdp' })
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
    const line = this.data.typeSummaryLine || this.data.result?.description?.type || ''
    const { getResultSharePath } = require('../../utils/share')
    return {
      title: `我的PDP类型是${line}，来测测你的吧！`,
      path: getResultSharePath('/pages/result/pdp', {
        id: this.data.testResultId,
        type: 'pdp'
      })
    }
  },

  onShareTimeline() {
    const line = this.data.typeSummaryLine || this.data.result?.description?.type || ''
    const { getResultShareTimelineQuery } = require('../../utils/share')
    return {
      title: `我的PDP类型是${line}，来测测你的吧！`,
      query: getResultShareTimelineQuery({
        id: this.data.testResultId,
        type: 'pdp'
      })
    }
  }
})
