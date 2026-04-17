// pages/result/disc.js - DISC结果页（支持付费墙 + 历史详情拉取）
const app = getApp()
const payment = require('../../utils/payment')
const { getTypeOnly } = require('../../utils/resultFormat')
const { isReportProfileComplete } = require('../../utils/phoneAuth.js')
const {
  slicePreviewText,
  slicePreviewList,
  openTimelineShareHint
} = require('../../utils/resultProfileGate.js')
const {
  getDiscInsight,
  getDiscTags,
  buildDiscDimensions
} = require('../../utils/discInsights.js')
const { decorateCareers } = require('../../utils/mbtiInsights.js')
const {
  computeJourney,
  markShared,
  markCamera
} = require('../../utils/resultJourneyState.js')
const resultScrollSync = require('../../utils/resultSectionScrollSync.js')

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
    fromShare: false,
    profileGate: false,
    previewDiscDescription: '',
    previewDiscStrengths: [],
    discDimensions: [],
    discInsight: null,
    discTags: [],
    discCareerItems: [],
    journey: { step1Unlocked: false, step2Unlocked: false, step3Unlocked: false, activeStep: 1 },
    sectionNav: [
      { id: 'sec-hero', label: 'DISC 画像', emoji: '🎯' },
      { id: 'sec-insight', label: '深度洞察', emoji: '🧠' },
      { id: 'sec-dim', label: '四维得分', emoji: '📊' },
      { id: 'sec-trait', label: '优势与注意', emoji: '✨' },
      { id: 'sec-career', label: '职业匹配', emoji: '💼' },
      { id: 'sec-cta', label: '深度方案', emoji: '💎' }
    ],
    scrollTarget: '',
    activeSection: ''
  },

  onTapSectionNav(e) {
    const id = e && e.detail && e.detail.id
    if (!id) return
    this.setData({ scrollTarget: '', activeSection: id }, () => {
      this.setData({ scrollTarget: id })
    })
  },

  onSectionScroll(e) {
    resultScrollSync.onScroll(this, e)
  },

  _syncJourney() {
    this.setData({
      journey: computeJourney({
        profileGate: !!this.data.profileGate,
        payRequired: !!(this.data.payInfo && this.data.payInfo.requiresPayment),
        isPaid: !!(this.data.payInfo && this.data.payInfo.isPaid)
      })
    })
  },

  onLoad(options) {
    try {
      wx.showShareMenu({ withShareTicket: true, menus: ['shareAppMessage', 'shareTimeline'] })
    } catch (e) {}
    const fromShareFs =
      options && (String(options.fs) === '1' || options.from === 'share')
    const id = options && options.id != null && options.id !== '' ? String(options.id) : ''
    const st = options && options.st ? String(options.st).trim() : ''
    const type = options && options.type ? String(options.type).toLowerCase() : ''

    if (id && type === 'disc') {
      this.setData({ testResultId: id, fromShare: !!fromShareFs })
      this.loadShareDetail(id, st, 'disc')
      return
    }
    if (id && st) {
      this.setData({ testResultId: id, fromShare: true })
      this.loadShareDetail(id, st, '')
      return
    }
    const raw = wx.getStorageSync('discResult')
    if (raw) {
      const r = withPercentagesInt(raw)
      this.setData({ result: r, typeSummaryLine: getTypeOnly(raw, 'disc') })
      this._syncDiscGate()
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
    this._syncDiscGate()
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
    this._reportPaywallOnce('disc', payInfo)
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
    this._syncJourney()
    if (this.data.testResultId) return
    const raw = wx.getStorageSync('discResult')
    if (!raw) return
    const r = withPercentagesInt(raw)
    this.setData({ result: r, typeSummaryLine: getTypeOnly(raw, 'disc') })
    this._syncDiscGate()
  },

  _syncDiscGate() {
    const r = this.data.result
    const fromShare = !!this.data.fromShare
    const profileGate = !fromShare && !isReportProfileComplete()
    const desc = (r && r.description) || {}
    const code = (r && (r.dominantType || r.disc)) || ''
    this.setData({
      profileGate,
      previewDiscDescription: slicePreviewText(desc.description || '', 0.3),
      previewDiscStrengths: slicePreviewList(desc.strengths || [], 0.3),
      discDimensions: r ? buildDiscDimensions(r) : [],
      discInsight: getDiscInsight(code),
      discTags: getDiscTags(code),
      discCareerItems: decorateCareers(desc.careers || [])
    })
    this._syncJourney()
  },

  onTapDeepService() {
    try { require('../../utils/analytics').track('tap_deep_service_from_disc', { disc: (this.data.result && (this.data.result.dominantType || this.data.result.disc)) }) } catch (e) {}
    wx.navigateTo({ url: '/pages/purchase/index' })
  },

  onTapPromoCenter() {
    try { require('../../utils/analytics').track('tap_promo_from_disc', {}) } catch (e) {}
    wx.navigateTo({ url: '/pages/promo/index' })
  },

  onTapReadFull() {
    try { require('../../utils/analytics').track('tap_read_full', { type: 'disc' }) } catch (e) {}
    if (this.data.profileGate) {
      this.goCompleteProfile()
      return
    }
    if (this.data.payInfo.requiresPayment && !this.data.payInfo.isPaid) {
      this.unlockFullReport()
      return
    }
    wx.showToast({ title: '当前已是完整报告', icon: 'none' })
  },

  onTapShareMoment() {
    try { require('../../utils/analytics').track('tap_share_moment', { type: 'disc' }) } catch (e) {}
    if (!this.data.journey.step1Unlocked) {
      wx.showToast({ title: '请先解锁全文', icon: 'none' })
      this.onTapReadFull()
      return
    }
    markShared()
    this._syncJourney()
    openTimelineShareHint()
  },

  onTapFaceCamera() {
    try { require('../../utils/analytics').track('tap_face_camera', { from: 'disc' }) } catch (e) {}
    if (!this.data.journey.step2Unlocked) {
      wx.showToast({ title: '请先分享朋友圈', icon: 'none' })
      return
    }
    markCamera()
    this._syncJourney()
    wx.switchTab({ url: '/pages/index/camera' })
  },

  goReadFullFromShare() {
    try { require('../../utils/analytics').track('tap_read_full', { type: 'disc', from: 'share' }) } catch (e) {}
    wx.switchTab({ url: '/pages/profile/index' })
  },

  goCompleteProfile() {
    try { require('../../utils/analytics').track('tap_complete_profile', { from: 'disc' }) } catch (e) {}
    wx.navigateTo({ url: '/pages/user-profile/index' })
  },

  _reportPaywallOnce(testType, payInfo) {
    if (!payInfo || !payInfo.requiresPayment || payInfo.isPaid) return
    if (this._paywallReported) return
    this._paywallReported = true
    try {
      require('../../utils/analytics').track('paywall_view', { type: testType, amountYuan: payInfo.amountYuan })
    } catch (e) {}
  },

  initPayInfoFromRuntime(testType) {
    app.getRuntimeConfig()
      .then((cfg) => {
        const reportRequires = cfg.reportRequiresPayment || {}
        const pricing = cfg.pricing || {}
        const requiresPayment = !!(reportRequires && reportRequires[testType])
        const amountYuan = Number(pricing[testType]) || (requiresPayment ? 1.98 : 0)
        const payInfo = { requiresPayment, isPaid: false, amountYuan }
        this.setData({ payInfo })
        this._reportPaywallOnce(testType, payInfo)
        this._syncJourney()
      })
      .catch(() => this.setData({ payInfo: { requiresPayment: false, isPaid: false, amountYuan: 0 } }))
  },

  unlockFullReport() {
    const { payInfo, testResultId, hasReloadedAfterPay } = this.data
    if (!payInfo.requiresPayment || payInfo.isPaid) return
    try { require('../../utils/analytics').track('tap_unlock_full', { type: 'disc', amountYuan: payInfo.amountYuan }) } catch (e) {}
    app.ensureLogin && app.ensureLogin().then((logged) => {
      if (!logged) { wx.showToast({ title: '请先登录', icon: 'none' }); return }
      payment.purchaseDiscTest({
        testResultId: testResultId || undefined,
        success: () => {
          wx.showToast({ title: '已解锁完整报告', icon: 'success' })
          this.setData({ 'payInfo.isPaid': true })
          this._syncJourney()
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

  goWantTest() {
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
        type: 'disc'
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
        type: 'disc'
      })
    }
  }
})
