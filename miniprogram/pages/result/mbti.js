// pages/result/mbti.js - MBTI结果页（支持付费墙 + 历史详情拉取）
const app = getApp()
const payment = require('../../utils/payment')
const { hasPhone, bindPhoneByCode, isReportProfileComplete } = require('../../utils/phoneAuth.js')
const {
  slicePreviewText,
  slicePreviewList,
  openTimelineShareHint
} = require('../../utils/resultProfileGate.js')
const {
  getMbtiInsight,
  getMbtiCategoryTags,
  getMbtiFields,
  getMbtiGrowth,
  decorateCareers
} = require('../../utils/mbtiInsights.js')
const {
  computeJourney,
  markShared,
  markCamera
} = require('../../utils/resultJourneyState.js')
const resultScrollSync = require('../../utils/resultSectionScrollSync.js')

Page({
  data: {
    result: null,
    dimensions: [],
    mbtiDesc: {
      title: '',
      category: '',
      description: '',
      strengths: [],
      weaknesses: [],
      careers: [],
      relationships: ''
    },
    mbtiInsight: null,
    mbtiTags: [],
    mbtiCareerItems: [],
    mbtiFields: [],
    mbtiGrowth: [],
    journey: { step1Unlocked: false, step2Unlocked: false, step3Unlocked: false, activeStep: 1 },
    payInfo: {
      requiresPayment: false,
      isPaid: false,
      amountYuan: 0
    },
    testResultId: null,
    /** 后端 HMAC，用于好友打开结果详情；来自 /api/test/detail 或 /api/test/share-detail */
    shareToken: '',
    hasReloadedAfterPay: false,
    hasPhone: false,
    fromShare: false,
    /** 未完善头像+昵称+手机且非分享落地时，展示约 30% 预览 */
    profileGate: false,
    previewDescription: '',
    previewStrengths: [],
    /** 分类锚点导航（顶部 chip 条） */
    sectionNav: [
      { id: 'sec-hero', label: '性格画像', emoji: '🪐' },
      { id: 'sec-insight', label: '深度洞察', emoji: '🧠' },
      { id: 'sec-dim', label: '四维得分', emoji: '📊' },
      { id: 'sec-trait', label: '优势与注意', emoji: '✨' },
      { id: 'sec-career', label: '职业匹配', emoji: '💼' },
      { id: 'sec-rel', label: '人际关系', emoji: '💞' },
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

  onLoad(options) {
    try {
      wx.showShareMenu({ withShareTicket: true, menus: ['shareAppMessage', 'shareTimeline'] })
    } catch (e) {}
    const fromShareFs =
      options && (String(options.fs) === '1' || options.from === 'share')
    const id = options && options.id != null && options.id !== '' ? String(options.id) : ''
    const st = options && options.st ? String(options.st).trim() : ''
    const type = options && options.type ? String(options.type).toLowerCase() : ''

    if (id && type === 'mbti') {
      this.setData({ testResultId: id, fromShare: !!fromShareFs })
      this.loadShareDetail(id, st, 'mbti')
      return
    }
    if (id && st) {
      this.setData({ testResultId: id, fromShare: true })
      this.loadShareDetail(id, st, '')
      return
    }

    const raw = wx.getStorageSync('mbtiResult')
    if (raw) {
      this.applyResult(raw)
      this.initPayInfoFromRuntime('mbti')
    } else {
      wx.showToast({ title: '暂无测试结果', icon: 'none' })
      setTimeout(() => wx.navigateBack(), 1500)
    }
  },

  onShow() {
    this.setData({ hasPhone: hasPhone() })
    this._syncJourney()
    if (this.data.testResultId) return
    const raw = wx.getStorageSync('mbtiResult')
    if (raw) {
      this.applyResult(raw)
    }
  },

  goCompleteProfile() {
    try { require('../../utils/analytics').track('tap_complete_profile', { from: 'mbti' }) } catch (e) {}
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
    this._reportPaywallOnce('mbti', payInfo)
    this._syncJourney()
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
    const desc = result.description || {}
    const fromShare = !!this.data.fromShare
    const profileGate = !fromShare && !isReportProfileComplete()
    const dimOk =
      result.dimensionScores &&
      (!result.locked || profileGate)
    const dimExplainMap = {
      EI: { left: '外向 E', right: '内向 I', desc: '能量来源：社交场合或独处沉思' },
      SN: { left: '感觉 S', right: '直觉 N', desc: '信息处理：关注事实或挖掘联系' },
      TF: { left: '思考 T', right: '情感 F', desc: '决策偏好：逻辑推理或价值共情' },
      JP: { left: '判断 J', right: '知觉 P', desc: '生活节奏：规划先行或灵活应对' }
    }
    const dashClass = {
      EI: 'dash-fill--ei', SN: 'dash-fill--sn', TF: 'dash-fill--tf', JP: 'dash-fill--jp'
    }
    const dimensions = dimOk
      ? ['EI', 'SN', 'TF', 'JP'].map((k) => {
          const src = result.dimensionScores[k] || {}
          const meta = dimExplainMap[k]
          return {
            key: k,
            left: meta.left,
            right: meta.right,
            desc: meta.desc,
            dashClass: dashClass[k],
            dominant: src.dominant || '',
            percentage: src.percentage != null ? src.percentage : 0
          }
        })
      : []
    const mbtiCode = result.mbtiType || result.mbti || ''
    const category = desc.category || ''
    this.setData({
      result,
      dimensions,
      profileGate,
      previewDescription: slicePreviewText(desc.description || '', 0.3),
      previewStrengths: slicePreviewList(desc.strengths || [], 0.3),
      mbtiDesc: {
        title: desc.name || '',
        category,
        description: desc.description || '',
        strengths: desc.strengths || [],
        weaknesses: desc.weaknesses || [],
        careers: desc.careers || [],
        relationships: desc.relationships || ''
      },
      mbtiInsight: getMbtiInsight(mbtiCode),
      mbtiTags: getMbtiCategoryTags(category),
      mbtiCareerItems: decorateCareers(desc.careers || []),
      mbtiFields: getMbtiFields(mbtiCode),
      mbtiGrowth: getMbtiGrowth(mbtiCode)
    })
    this._syncJourney()
  },

  onTapDeepService() {
    try {
      require('../../utils/analytics').track('tap_deep_service_from_mbti', {
        mbti: this.data.result && this.data.result.mbtiType
      })
    } catch (e) {}
    wx.navigateTo({ url: '/pages/purchase/index' })
  },

  onTapPromoCenter() {
    try {
      require('../../utils/analytics').track('tap_promo_from_mbti', {
        mbti: this.data.result && this.data.result.mbtiType
      })
    } catch (e) {}
    wx.navigateTo({ url: '/pages/promo/index' })
  },

  _syncJourney() {
    const j = computeJourney({
      profileGate: !!this.data.profileGate,
      payRequired: !!(this.data.payInfo && this.data.payInfo.requiresPayment),
      isPaid: !!(this.data.payInfo && this.data.payInfo.isPaid)
    })
    this.setData({ journey: j })
  },

  onTapReadFull() {
    try { require('../../utils/analytics').track('tap_read_full', { type: 'mbti' }) } catch (e) {}
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
    try { require('../../utils/analytics').track('tap_share_moment', { type: 'mbti' }) } catch (e) {}
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
    try { require('../../utils/analytics').track('tap_face_camera', { from: 'mbti' }) } catch (e) {}
    if (!this.data.journey.step2Unlocked) {
      wx.showToast({ title: '请先分享朋友圈', icon: 'none' })
      return
    }
    markCamera()
    this._syncJourney()
    wx.switchTab({ url: '/pages/index/camera' })
  },

  goReadFullFromShare() {
    try { require('../../utils/analytics').track('tap_read_full', { type: 'mbti', from: 'share' }) } catch (e) {}
    wx.switchTab({ url: '/pages/profile/index' })
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
    try { require('../../utils/analytics').track('tap_unlock_full', { type: 'mbti', amountYuan: payInfo.amountYuan }) } catch (e) {}
    app.ensureLogin && app.ensureLogin().then((logged) => {
      if (!logged) {
        wx.showToast({ title: '请先登录', icon: 'none' })
        return
      }
      payment.purchaseMbtiTest({
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

  // 付费解锁按钮：就地触发微信手机号授权，然后调用 unlockFullReport
  onGetPhoneNumberForMbtiPay(e) {
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
      .catch(() => {
        // 保持在当前页，等待用户重新点击
      })
  },

  retakeTest() {
    if (!this.data.testResultId) {
      wx.removeStorageSync('mbtiResult')
    }
    wx.navigateTo({ url: '/pages/test/mbti' })
  },

  goWantTest() {
    wx.navigateTo({ url: '/pages/test/mbti' })
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
      title: `我的MBTI类型是${result?.mbtiType}（${result?.description?.name}），来测测你的吧！`,
      path: getResultSharePath('/pages/result/mbti', {
        id: this.data.testResultId,
        type: 'mbti'
      }),
      imageUrl: '/images/share-mbti.png'
    }
  },

  onShareTimeline() {
    const result = this.data.result
    const { getResultShareTimelineQuery } = require('../../utils/share')
    return {
      title: `我的MBTI类型是${result?.mbtiType}（${result?.description?.name}），来测测你的吧！`,
      query: getResultShareTimelineQuery({
        id: this.data.testResultId,
        type: 'mbti'
      })
    }
  }
})
