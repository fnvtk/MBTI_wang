// pages/result/sbti.js — SBTI 结果页
const app = getApp()
const payment = require('../../utils/payment')
const { hasPhone, bindPhoneByCode, isReportProfileComplete } = require('../../utils/phoneAuth.js')
const {
  slicePreviewText,
  openTimelineShareHint
} = require('../../utils/resultProfileGate.js')
const { decorateSbtiDims, groupSbtiDims, splitSbtiDesc } = require('../../utils/sbtiDisplay.js')
const { TYPE_IMAGES } = require('../../utils/sbtiEngine.js')
const {
  computeJourney,
  markShared,
  markCamera
} = require('../../utils/resultJourneyState.js')
const resultScrollSync = require('../../utils/resultSectionScrollSync.js')

/** 根据结果类型代码取展示图（与 sbtiData.TYPE_IMAGES 一致） */
/** 旧版结果仅有 badge / bestNormal，补全 matchPercent、hitDimCount */
function normalizeSbtiResultForDisplay(result) {
  if (!result || typeof result !== 'object') return result
  const out = { ...result }
  const bn = out.bestNormal
  if (out.matchPercent == null && bn != null && typeof bn.similarity === 'number') {
    out.matchPercent = bn.similarity
  }
  if (out.hitDimCount == null && bn != null && typeof bn.exact === 'number') {
    out.hitDimCount = bn.exact
  }
  if (out.special && out.sbtiType === 'DRUNK') {
    if (out.matchPercent == null) out.matchPercent = 100
    if (out.hitDimCount == null) out.hitDimCount = 15
  }
  return out
}

function resolveSbtiTypeImageUrl(result) {
  if (!result || typeof result !== 'object') return ''
  const code = result.sbtiType || (result.finalType && result.finalType.code) || ''
  if (!code || !TYPE_IMAGES) return ''
  const url = TYPE_IMAGES[code]
  return typeof url === 'string' ? url : ''
}

Page({
  data: {
    result: null,
    typeImageUrl: '',
    /** 为 true 时展示顶部文字备用（无图地址或图片加载失败） */
    typeImageLoadFailed: false,
    /** 类型图是否已成功加载（有 URL 且 bindload 触发后为 true，用于隐藏与图重复的顶部三行字） */
    typeImageLoaded: false,
    dimExplainList: [],
    payInfo: {
      requiresPayment: false,
      isPaid: false,
      amountYuan: 0
    },
    testResultId: null,
    shareToken: '',
    hasReloadedAfterPay: false,
    hasPhone: false,
    /** 分享落地（path 带 fs=1 或旧版仅 id+st），用于隐藏「去完善资料」、展示底部「我也要测试」 */
    fromShare: false,
    profileGate: false,
    previewSbtiIntro: '',
    previewSbtiDesc: '',
    previewSbtiDescParts: [],
    descParts: [],
    dimGroups: [],
    openGroup: '',
    journey: { step1Unlocked: false, step2Unlocked: false, step3Unlocked: false, activeStep: 1 },
    sectionNav: [
      { id: 'sec-hero', label: 'SBTI 画像', emoji: '🎭' },
      { id: 'sec-desc', label: '人格描述', emoji: '📝' },
      { id: 'sec-dim', label: '十五维度', emoji: '📊' },
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

    // id + type：免登录拉取（/api/test/share-detail）
    if (id && type === 'sbti') {
      this.setData({ testResultId: id, fromShare: !!fromShareFs })
      this.loadShareDetail(id, st, 'sbti')
      return
    }
    // 旧版分享仅 id + st
    if (id && st) {
      this.setData({ testResultId: id, fromShare: true })
      this.loadShareDetail(id, st, '')
      return
    }

    const raw = wx.getStorageSync('sbtiResult')
    if (raw) {
      this.applyResult(raw)
      this.initPayInfoFromRuntime('sbti')
    } else {
      wx.showToast({ title: '暂无测试结果', icon: 'none' })
      setTimeout(() => wx.navigateBack(), 1500)
    }
  },

  onShow() {
    this.setData({ hasPhone: hasPhone() })
    this._syncJourney()
    if (this.data.testResultId) return
    const raw = wx.getStorageSync('sbtiResult')
    if (raw) {
      this.applyResult(raw)
    }
  },

  goCompleteProfile() {
    try { require('../../utils/analytics').track('tap_complete_profile', { from: 'sbti' }) } catch (e) {}
    wx.navigateTo({ url: '/pages/user-profile/index' })
  },

  applyDetailPayload(payload) {
    const data = payload.data || payload
    const isPaid = !!payload.isPaid
    const paidAmount = payload.paidAmount != null ? Number(payload.paidAmount) : 0
    const amountYuan = payload.amountYuan != null ? Number(payload.amountYuan) : (paidAmount > 0 ? paidAmount / 100 : 0)
    const needPaymentToUnlock = payload.needPaymentToUnlock === true || (!!payload.requiresPayment && !isPaid && paidAmount > 0)
    this.applyResult(data)
    const payInfo = {
      requiresPayment: needPaymentToUnlock,
      isPaid,
      amountYuan: needPaymentToUnlock ? amountYuan : 0
    }
    const patch = {
      payInfo,
      shareToken: payload.shareToken || ''
    }
    if (payload.id != null && payload.id !== '') {
      patch.testResultId = String(payload.id)
    }
    this.setData(patch)
    this._reportPaywallOnce('sbti', payInfo)
  },

  loadDetail(id) {
    const apiBase = app.globalData?.apiBase || ''
    const token = app.globalData?.token || wx.getStorageSync('token') || ''
    if (!apiBase) {
      wx.showToast({ title: '配置异常', icon: 'none' })
      return
    }
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
    if (!apiBase) {
      wx.showToast({ title: '配置异常', icon: 'none' })
      return
    }
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

  applyResult(result) {
    if (!result) return
    const normalized = normalizeSbtiResultForDisplay(result)
    const fromShare = !!this.data.fromShare
    const profileGate = !fromShare && !isReportProfileComplete()
    const src = Array.isArray(normalized.dimExplainList) ? normalized.dimExplainList : []
    const allowDims = profileGate || !normalized.locked
    let dimExplainList = []
    if (src.length && allowDims) {
      dimExplainList = profileGate
        ? src.slice(0, Math.max(1, Math.ceil(src.length * 0.3)))
        : src
    }
    dimExplainList = decorateSbtiDims(dimExplainList)
    const dimGroups = groupSbtiDims(dimExplainList)
    const descParts = profileGate ? [] : splitSbtiDesc(normalized.desc || '', 12)
    const previewSbtiDescParts = profileGate ? splitSbtiDesc(slicePreviewText(normalized.desc || '', 0.3), 4) : []
    const typeImageUrl = resolveSbtiTypeImageUrl(normalized)
    this.setData({
      result: normalized,
      profileGate,
      previewSbtiIntro: slicePreviewText(normalized.intro || '', 0.3),
      previewSbtiDesc: slicePreviewText(normalized.desc || '', 0.3),
      previewSbtiDescParts,
      descParts,
      dimExplainList,
      dimGroups,
      openGroup: dimGroups[0] ? dimGroups[0].key : '',
      typeImageUrl,
      typeImageLoadFailed: false,
      typeImageLoaded: false
    })
    this._syncJourney()
  },

  toggleGroup(e) {
    const key = e.currentTarget.dataset.key
    if (!key) return
    this.setData({ openGroup: this.data.openGroup === key ? '' : key })
  },

  onTapDeepService() {
    try { require('../../utils/analytics').track('tap_deep_service_from_sbti', {}) } catch (e) {}
    wx.navigateTo({ url: '/pages/purchase/index' })
  },

  onTapPromoCenter() {
    try { require('../../utils/analytics').track('tap_promo_from_sbti', {}) } catch (e) {}
    wx.navigateTo({ url: '/pages/promo/index' })
  },

  onTapReadFull() {
    try { require('../../utils/analytics').track('tap_read_full', { type: 'sbti' }) } catch (e) {}
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
    try { require('../../utils/analytics').track('tap_share_moment', { type: 'sbti' }) } catch (e) {}
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
    try { require('../../utils/analytics').track('tap_face_camera', { from: 'sbti' }) } catch (e) {}
    if (!this.data.journey.step2Unlocked) {
      wx.showToast({ title: '请先分享朋友圈', icon: 'none' })
      return
    }
    markCamera()
    this._syncJourney()
    wx.switchTab({ url: '/pages/index/camera' })
  },

  goReadFullFromShare() {
    try { require('../../utils/analytics').track('tap_read_full', { type: 'sbti', from: 'share' }) } catch (e) {}
    wx.switchTab({ url: '/pages/profile/index' })
  },

  onTypeImageLoad() {
    this.setData({ typeImageLoadFailed: false, typeImageLoaded: true })
  },

  onTypeImageError() {
    this.setData({ typeImageLoadFailed: true, typeImageLoaded: false })
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
        const pricing = cfg.pricing || {}
        const reportRequires = cfg.reportRequiresPayment || {}
        const requiresPayment = !!(reportRequires && reportRequires[testType])
        const amountYuan = Number(pricing[testType]) || (requiresPayment ? 1.98 : 0)
        const payInfo = { requiresPayment, isPaid: false, amountYuan }
        this.setData({ payInfo })
        this._reportPaywallOnce(testType, payInfo)
        this._syncJourney()
      })
      .catch(() => {
        this.setData({
          payInfo: { requiresPayment: false, isPaid: false, amountYuan: 0 }
        })
      })
  },

  unlockFullReport() {
    const { payInfo, testResultId, hasReloadedAfterPay } = this.data
    if (!payInfo.requiresPayment || payInfo.isPaid) return
    try { require('../../utils/analytics').track('tap_unlock_full', { type: 'sbti', amountYuan: payInfo.amountYuan }) } catch (e) {}
    app.ensureLogin && app.ensureLogin().then((logged) => {
      if (!logged) {
        wx.showToast({ title: '请先登录', icon: 'none' })
        return
      }
      payment.purchaseSbtiTest({
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

  onGetPhoneNumberForSbtiPay(e) {
    const { code, errMsg } = e.detail || {}
    if (errMsg && errMsg.indexOf('getPhoneNumber:fail') === 0) {
      if (!hasPhone()) {
        wx.showToast({ title: '需要授权手机号才能继续', icon: 'none' })
        return
      }
      this.unlockFullReport()
      return
    }
    if (!code) {
      if (hasPhone()) {
        this.unlockFullReport()
      } else {
        wx.showToast({ title: '获取手机号失败', icon: 'none' })
      }
      return
    }
    bindPhoneByCode(code)
      .then(() => {
        this.setData({ hasPhone: true })
        this.unlockFullReport()
      })
      .catch(() => {})
  },

  retakeTest() {
    if (!this.data.testResultId) {
      wx.removeStorageSync('sbtiResult')
    }
    wx.navigateTo({ url: '/pages/test/sbti' })
  },

  /** 分享落地页：引导好友自己做测试 */
  goWantTest() {
    wx.navigateTo({ url: '/pages/test/sbti' })
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
    const label = result?.sbtiCn || result?.finalType?.cn || 'SBTI'
    const code = result?.sbtiType || result?.finalType?.code || ''
    const img = this.data.typeImageUrl || '/images/share-mbti.png'
    return {
      title: `我的 SBTI 类型是 ${code}（${label}），来测测你的吧！`,
      path: getResultSharePath('/pages/result/sbti', {
        id: this.data.testResultId,
        type: 'sbti'
      }),
      imageUrl: img
    }
  },

  onShareTimeline() {
    const result = this.data.result
    const { getResultShareTimelineQuery } = require('../../utils/share')
    const label = result?.sbtiCn || result?.finalType?.cn || 'SBTI'
    const code = result?.sbtiType || result?.finalType?.code || ''
    return {
      title: `我的 SBTI 类型是 ${code}（${label}），来测测你的吧！`,
      query: getResultShareTimelineQuery({
        id: this.data.testResultId,
        type: 'sbti'
      })
    }
  }
})
