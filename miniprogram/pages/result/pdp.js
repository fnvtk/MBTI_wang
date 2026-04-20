// pages/result/pdp.js - PDP结果页（支持付费墙 + 历史详情拉取）
const app = getApp()
const payment = require('../../utils/payment')
const { getTypeOnly } = require('../../utils/resultFormat')
const {
  hasPhone,
  bindPhoneByCode,
  needsResultProfileGate,
  navigateToCompleteProfileAfterPhoneIfNeeded
} = require('../../utils/phoneAuth.js')
const {
  slicePreviewText,
  slicePreviewList,
  openTimelineShareHint
} = require('../../utils/resultProfileGate.js')
const {
  getPdpInsight,
  getPdpTags,
  buildPdpDimensions
} = require('../../utils/pdpInsights.js')
const { decorateCareers } = require('../../utils/mbtiInsights.js')
const {
  computeJourney,
  markShared,
  markCamera
} = require('../../utils/resultJourneyState.js')
const resultScrollSync = require('../../utils/resultSectionScrollSync.js')

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
    fromShare: false,
    hasPhone: false,
    profileGate: false,
    previewPdpDescription: '',
    previewPdpStrengths: [],
    pdpDimensions: [],
    pdpInsight: null,
    pdpTags: [],
    pdpCareerItems: [],
    journey: { step1Unlocked: false, step2Unlocked: false, step3Unlocked: false, activeStep: 1 },
    sectionNav: [
      { id: 'sec-hero', label: 'PDP 画像', emoji: '🦁' },
      { id: 'sec-insight', label: '深度洞察', emoji: '🧠' },
      { id: 'sec-dim', label: '五维得分', emoji: '📊' },
      { id: 'sec-trait', label: '优势与注意', emoji: '✨' },
      { id: 'sec-team', label: '团队角色', emoji: '🤝' },
      { id: 'sec-career', label: '推荐职业', emoji: '💼' },
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
      this._syncPdpGate()
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
    this._syncPdpGate()
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
    this._reportPaywallOnce('pdp', payInfo)
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
    this.setData({ hasPhone: hasPhone() })
    if (this.data.testResultId && this.data.result) {
      this._syncPdpGate()
    } else if (!this.data.testResultId) {
      const raw = wx.getStorageSync('pdpResult')
      if (!raw) return
      this.setData({
        result: withPercentagesInt(raw),
        typeSummaryLine: getTypeOnly(raw, 'pdp')
      })
      this._syncPdpGate()
    }
    this._syncJourney()
  },

  _syncPdpGate() {
    const r = this.data.result
    const fromShare = !!this.data.fromShare
    const profileGate = needsResultProfileGate(fromShare)
    const desc = (r && r.description) || {}
    const code = (r && (r.dominantType || (desc && desc.type))) || ''
    this.setData({
      profileGate,
      previewPdpDescription: slicePreviewText(desc.description || '', 0.3),
      previewPdpStrengths: slicePreviewList(desc.strengths || [], 0.3),
      pdpDimensions: r ? buildPdpDimensions(r) : [],
      pdpInsight: getPdpInsight(code),
      pdpTags: getPdpTags(code),
      pdpCareerItems: decorateCareers(desc.careers || [])
    })
    this._syncJourney()
  },

  onTapDeepService() {
    try { require('../../utils/analytics').track('tap_deep_service_from_pdp', {}) } catch (e) {}
    wx.navigateTo({ url: '/pages/purchase/index' })
  },

  onTapPromoCenter() {
    try { require('../../utils/analytics').track('tap_promo_from_pdp', {}) } catch (e) {}
    wx.navigateTo({ url: '/pages/promo/index' })
  },

  onPhoneLoginForResultGate(e) {
    const { code, errMsg } = e.detail || {}
    if (errMsg && errMsg.indexOf('getPhoneNumber:fail') === 0) {
      wx.showToast({ title: '需要授权手机号才能查看完整报告', icon: 'none' })
      return
    }
    if (!code) {
      wx.showToast({ title: '获取手机号失败', icon: 'none' })
      return
    }
    bindPhoneByCode(code)
      .then(() => {
        this.setData({ hasPhone: hasPhone() })
        if (this.data.testResultId) {
          this._syncPdpGate()
        } else {
          const raw = wx.getStorageSync('pdpResult')
          if (raw) {
            this.setData({
              result: withPercentagesInt(raw),
              typeSummaryLine: getTypeOnly(raw, 'pdp')
            })
            this._syncPdpGate()
          }
        }
        navigateToCompleteProfileAfterPhoneIfNeeded()
      })
      .catch(() => {})
  },

  onTapReadFull() {
    try { require('../../utils/analytics').track('tap_read_full', { type: 'pdp' }) } catch (e) {}
    if (this.data.profileGate) {
      wx.showToast({
        title: this.data.hasPhone ? '请先完善头像与昵称' : '请先点击下方「登录解锁全文」',
        icon: 'none'
      })
      return
    }
    if (this.data.payInfo.requiresPayment && !this.data.payInfo.isPaid) {
      this.unlockFullReport()
      return
    }
    wx.showToast({ title: '当前已是完整报告', icon: 'none' })
  },

  onTapShareMoment() {
    try { require('../../utils/analytics').track('tap_share_moment', { type: 'pdp' }) } catch (e) {}
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
    try { require('../../utils/analytics').track('tap_face_camera', { from: 'pdp' }) } catch (e) {}
    if (!this.data.journey.step2Unlocked) {
      wx.showToast({ title: '请先分享朋友圈', icon: 'none' })
      return
    }
    markCamera()
    this._syncJourney()
    wx.switchTab({ url: '/pages/index/camera' })
  },

  goReadFullFromShare() {
    try { require('../../utils/analytics').track('tap_read_full', { type: 'pdp', from: 'share' }) } catch (e) {}
    wx.switchTab({ url: '/pages/profile/index' })
  },

  goCompleteProfile() {
    try { require('../../utils/analytics').track('tap_complete_profile', { from: 'pdp' }) } catch (e) {}
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
    try { require('../../utils/analytics').track('tap_unlock_full', { type: 'pdp', amountYuan: payInfo.amountYuan }) } catch (e) {}
    app.ensureLogin && app.ensureLogin().then((logged) => {
      if (!logged) { wx.showToast({ title: '请先登录', icon: 'none' }); return }
      payment.purchasePdpTest({
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
