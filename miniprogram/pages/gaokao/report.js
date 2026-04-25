const app = getApp()
const gaokaoApi = require('../../utils/gaokao')
const { requestPromise } = require('../../utils/request')
const payment = require('../../utils/payment')
const {
  hasPhone,
  bindPhoneByCode,
  needsResultProfileGate,
  navigateToCompleteProfileAfterPhoneIfNeeded
} = require('../../utils/phoneAuth.js')
const unlockGate = require('../../utils/unlockGate.js')
const inviteCodeGate = require('../../utils/inviteCodeGate.js')
const { openTimelineShareHint } = require('../../utils/resultProfileGate.js')
const { computeJourney, markShared } = require('../../utils/gaokaoJourneyState.js')
const { getEnterpriseIdForApiPayload } = require('../../utils/enterpriseContext.js')

/** GET /api/test/detail | share-detail 返回体 -> mergeApiReport 入参 */
function mapTestDetailToReportPayload(detail) {
  if (!detail || typeof detail !== 'object') {
    return null
  }
  if (String(detail.testType || '').toLowerCase() !== 'gaokao') {
    return null
  }
  const d = detail.data
  if (!d || typeof d !== 'object') {
    return null
  }
  const locked = !!d.locked
  let report = d.report
  if (typeof report === 'string') {
    try {
      report = JSON.parse(report)
    } catch (e) {
      report = null
    }
  }
  const inputSnap =
    d.inputSnapshot && typeof d.inputSnapshot === 'object' && !Array.isArray(d.inputSnapshot)
      ? d.inputSnapshot
      : {}

  if (locked) {
    const ov =
      typeof d.overview === 'string' && d.overview !== ''
        ? d.overview
        : String(d.overview || '')
    report = {
      overview: ov,
      personalityReason: '',
      disclaimers: '',
      majorRecommend: [],
      schoolRecommend: {},
      inputEcho: {
        name: String(inputSnap.name || ''),
        province: String(inputSnap.province || ''),
        streamSubjects: String(inputSnap.streamSubjects || ''),
        estimatedScore: inputSnap.estimatedScore != null ? Number(inputSnap.estimatedScore) : 0,
        mbti: String(inputSnap.mbti || ''),
        pdp: String(inputSnap.pdp || ''),
        disc: String(inputSnap.disc || '')
      },
      locked: true
    }
  } else if (!report || typeof report !== 'object' || Array.isArray(report)) {
    return null
  }

  const topOv = typeof d.overview === 'string' ? d.overview : ''
  return {
    id: detail.id,
    createdAt: detail.createdAt,
    overview: topOv,
    report
  }
}

/**
 * 接口返回: { id, createdAt, overview, report: reportJson }
 * 展示用合并为一层，便于 wxml 绑定
 */
function mergeApiReport(payload) {
  if (!payload || typeof payload !== 'object') {
    return null
  }
  let br = payload.report
  if (typeof br === 'string') {
    try {
      br = JSON.parse(br)
    } catch (e) {
      br = null
    }
  }
  const block = br && typeof br === 'object' && !Array.isArray(br) ? br : {}
  const hasBlock = Object.keys(block).length > 0
  const hasOverview = typeof payload.overview === 'string' && payload.overview !== ''
  if (!hasBlock && !hasOverview && !(payload.id > 0)) {
    return null
  }
  const overview = hasOverview ? payload.overview : block.overview || ''
  return Object.assign({}, block, { overview })
}

function normalizeSchoolRow(x, band) {
  if (!x || typeof x !== 'object') {
    return null
  }
  return {
    band: band || '',
    schoolName: String(x.schoolName || x.name || '').trim() || '未命名院校',
    city: String(x.city || '').trim(),
    level: String(x.level || '').trim(),
    reason: String(x.reason || x.desc || '').trim()
  }
}

function buildSchoolListFlat(rawSr) {
  if (Array.isArray(rawSr)) {
    return rawSr.map((x) => normalizeSchoolRow(x, '')).filter(Boolean)
  }
  if (rawSr && typeof rawSr === 'object' && !Array.isArray(rawSr)) {
    const chong = Array.isArray(rawSr.chong) ? rawSr.chong : []
    const wen = Array.isArray(rawSr.wen) ? rawSr.wen : []
    const bao = Array.isArray(rawSr.bao) ? rawSr.bao : []
    return [
      ...chong.map((x) => normalizeSchoolRow(x, '冲')),
      ...wen.map((x) => normalizeSchoolRow(x, '稳')),
      ...bao.map((x) => normalizeSchoolRow(x, '保'))
    ].filter(Boolean)
  }
  return []
}

function buildViewModel(payload) {
  const report = mergeApiReport(payload)
  if (!report) {
    return {
      report: null,
      inputEcho: {},
      majorList: [],
      schoolListFlat: [],
      schoolChongCount: 0,
      schoolWenCount: 0,
      schoolBaoCount: 0,
      hasSchoolFlat: false,
      hasNoMajors: true
    }
  }
  const rawSr = report.schoolRecommend
  const schoolListFlat = buildSchoolListFlat(rawSr)

  let schoolChongCount = 0
  let schoolWenCount = 0
  let schoolBaoCount = 0
  if (rawSr && typeof rawSr === 'object' && !Array.isArray(rawSr)) {
    schoolChongCount = Array.isArray(rawSr.chong) ? rawSr.chong.length : 0
    schoolWenCount = Array.isArray(rawSr.wen) ? rawSr.wen.length : 0
    schoolBaoCount = Array.isArray(rawSr.bao) ? rawSr.bao.length : 0
  }

  const majors = Array.isArray(report.majorRecommend) ? report.majorRecommend : []
  const inputEcho = report.inputEcho || {}

  return {
    report,
    inputEcho,
    majorList: majors.map((m) => {
      const rawName =
        m &&
        (m.majorName ||
          m.name ||
          m.title ||
          m.major ||
          m.major_name ||
          m.majorChinese ||
          m['专业'] ||
          m['专业名称'])
      const name = rawName != null && rawName !== '' ? String(rawName).trim() : ''
      const displayName = name || '未命名专业'
      const score = m && (m.fitScore != null ? m.fitScore : m.matchScore)
      const fitLabel = score != null && score !== '' ? '(' + String(score) + ')' : ''
      return { name: displayName, fitLabel }
    }),
    schoolListFlat,
    schoolChongCount,
    schoolWenCount,
    schoolBaoCount,
    hasSchoolFlat: schoolListFlat.length > 0,
    hasNoMajors: majors.length === 0
  }
}

function payInfoFromDetail(detail) {
  const isPaid = !!(detail && (detail.isPaid === 1 || detail.isPaid === true))
  const paidAmount = detail && detail.paidAmount != null ? Number(detail.paidAmount) : 0
  const amountYuan =
    detail && detail.amountYuan != null
      ? Number(detail.amountYuan)
      : paidAmount > 0
        ? paidAmount / 100
        : 0
  const needPaymentToUnlock =
    detail &&
    (detail.needPaymentToUnlock === true ||
      (!!detail.requiresPayment && !isPaid && paidAmount > 0))
  return {
    requiresPayment: needPaymentToUnlock,
    isPaid,
    amountYuan: needPaymentToUnlock ? amountYuan : 0
  }
}

Page({
  data: {
    report: null,
    inputEcho: {},
    majorList: [],
    schoolListFlat: [],
    schoolChongCount: 0,
    schoolWenCount: 0,
    schoolBaoCount: 0,
    hasSchoolFlat: false,
    hasNoMajors: true,
    journey: { step1Unlocked: false, step2Unlocked: false, activeStep: 1 },
    payInfo: {
      requiresPayment: false,
      isPaid: false,
      amountYuan: 0
    },
    testResultId: '',
    shareToken: '',
    hasReloadedAfterPay: false,
    hasPhone: false,
    fromShare: false,
    profileGate: false,
    showInviteCodeDialog: false,
    isPendingAnalyze: false,
    analyzingTitle: '正在生成高考志愿分析报告',
    analyzingTip: '',
    analyzeProgress: 0
  },

  onLoad(options) {
    try {
      wx.showShareMenu({ withShareTicket: true, menus: ['shareAppMessage', 'shareTimeline'] })
    } catch (e) {}

    const fromShareFs = options && (String(options.fs) === '1' || options.from === 'share')
    const sid = options && options.id != null && options.id !== '' ? String(options.id) : ''
    const st = options && options.st ? String(options.st).trim() : ''

    if (sid && st) {
      this.setData({ fromShare: true })
      this.loadShareDetail(sid, st)
      return
    }

    const ec =
      typeof this.getOpenerEventChannel === 'function' ? this.getOpenerEventChannel() : null
    if (ec && typeof ec.once === 'function') {
      ec.once('gaokaoAnalyzeReport', (payload) => {
        if (payload && payload.report) {
          this.applyPayloadOnly({
            id: payload.id,
            createdAt: payload.createdAt || 0,
            overview: payload.overview || '',
            report: payload.report
          })
        }
      })
    }

    const pendingAnalyze = options && String(options.pendingAnalyze) === '1'
    if (pendingAnalyze) {
      this._pendingAnalyze = true
      return
    }

    const rid = options && options.id != null ? parseInt(String(options.id), 10) : 0
    this._detailReportId = rid > 0 && !Number.isNaN(rid) ? rid : 0
    if (fromShareFs) {
      this.setData({ fromShare: true })
    }

    const delay = options && options.fromAnalyze === '1' ? 400 : 0
    setTimeout(() => this.load(), delay)
  },

  onReady() {
    if (this._pendingAnalyze) {
      this._pendingAnalyze = false
      this.beginAnalyzeFlow()
    }
  },

  onUnload() {
    this._clearAnalyzeTimers()
  },

  _clearAnalyzeTimers() {
    if (this._analyzeProgressTimer) {
      clearInterval(this._analyzeProgressTimer)
      this._analyzeProgressTimer = null
    }
  },

  beginAnalyzeFlow() {
    this._clearAnalyzeTimers()
    const tips = [
      '正在读取您的 MBTI 与测评数据…',
      '正在匹配专业维度与性格倾向…',
      '正在根据分数与省份生成志愿建议…',
      '正在润色报告与安全合规校验…',
      '生成综合报告…'
    ]
    let progress = 0
    let tipIndex = 0
    this.setData({
      isPendingAnalyze: true,
      analyzingTitle: '正在生成高考志愿分析报告',
      analyzingTip: tips[0],
      analyzeProgress: 0
    })
    this._analyzeProgressTimer = setInterval(() => {
      progress += 3
      if (progress > 95) progress = 95
      if (progress > (tipIndex + 1) * 18 && tipIndex < tips.length - 1) tipIndex++
      this.setData({
        analyzeProgress: Math.floor(progress),
        analyzingTip: tips[tipIndex]
      })
    }, 200)

    gaokaoApi
      .analyze()
      .then((res) => {
        this._clearAnalyzeTimers()
        const rawId = res && (res.reportId != null ? res.reportId : res.id)
        const numId = parseInt(String(rawId), 10)
        if (!rawId || Number.isNaN(numId) || numId <= 0) {
          throw new Error('未返回报告')
        }
        this._detailReportId = numId
        this.setData({
          analyzeProgress: 100,
          analyzingTip: '分析完成！'
        })
        return new Promise((r) => setTimeout(r, 400)).then(() =>
          this.loadDetail(numId, { silent: true }).catch(() => {
            const rep = res && res.report
            if (rep && typeof rep === 'object') {
              this.applyPayloadOnly({
                id: numId,
                createdAt: res.createdAt || 0,
                overview:
                  (typeof res.overview === 'string' && res.overview) ||
                  (rep.overview && String(rep.overview)) ||
                  '',
                report: rep
              })
              return
            }
            return Promise.reject(new Error('报告已生成，但加载详情失败'))
          })
        )
      })
      .then(() => {
        this.setData({ isPendingAnalyze: false })
      })
      .catch((e) => {
        this._clearAnalyzeTimers()
        this.setData({ isPendingAnalyze: false, analyzeProgress: 0 })
        wx.showToast({ title: (e && e.message) || '分析失败', icon: 'none' })
        setTimeout(() => {
          wx.navigateBack({ delta: 1 })
        }, 1600)
      })
  },

  onShow() {
    this.setData({ hasPhone: hasPhone() })
    if (this.data.report && !this.data.fromShare) {
      const profileGate = needsResultProfileGate(!!this.data.fromShare)
      this.setData({ profileGate })
      this._syncJourney()
    }
    // 切换个人/企业 Tab 后回到报告页：静默重拉详情以同步 paidAmount（与当前 Tab 定价一致）
    if (!this.data.fromShare && !this.data.isPendingAnalyze) {
      const rid = parseInt(String(this.data.testResultId || ''), 10)
      if (rid > 0) {
        this.loadDetail(rid, { silent: true }).catch(() => {})
      }
    }
  },

  onShareAppMessage() {
    const id = this.data.testResultId
    const st = this.data.shareToken
    if (!id || !st) {
      return { title: '高考志愿分析报告', path: '/pages/gaokao/index' }
    }
    return {
      title: '高考志愿分析报告',
      path: `/pages/gaokao/report?id=${encodeURIComponent(id)}&st=${encodeURIComponent(st)}&fs=1`
    }
  },

  _syncJourney() {
    const j = computeJourney(
      {
        profileGate: !!this.data.profileGate,
        payRequired: !!(this.data.payInfo && this.data.payInfo.requiresPayment),
        isPaid: !!(this.data.payInfo && this.data.payInfo.isPaid)
      },
      this.data.testResultId || '0'
    )
    this.setData({ journey: j })
  },

  _reportPaywallOnce(payInfo) {
    if (!payInfo || !payInfo.requiresPayment || payInfo.isPaid) return
    if (this._paywallReported) return
    this._paywallReported = true
    try {
      require('../../utils/analytics').track('paywall_view', {
        type: 'gaokao',
        amountYuan: payInfo.amountYuan
      })
    } catch (e) {}
  },

  applyPayloadOnly(payload) {
    const vm = buildViewModel(payload)
    const profileGate = needsResultProfileGate(!!this.data.fromShare)
    const patch = Object.assign(vm, { profileGate })
    if (payload && payload.id != null) {
      patch.testResultId = String(payload.id)
    }
    this.setData(patch)
    this._syncJourney()
  },

  applyDetailPayload(detail) {
    const mapped = mapTestDetailToReportPayload(detail)
    if (!mapped) {
      wx.showToast({ title: '报告数据无效', icon: 'none' })
      this.setData(buildViewModel(null))
      return
    }
    const payInfo = payInfoFromDetail(detail)
    const profileGate = needsResultProfileGate(!!this.data.fromShare)
    const vm = buildViewModel(mapped)
    const patch = Object.assign(vm, {
      payInfo,
      profileGate,
      shareToken: (detail && detail.shareToken) || '',
      testResultId: detail.id != null ? String(detail.id) : ''
    })
    this.setData(patch)
    this._reportPaywallOnce(payInfo)
    this._syncJourney()
  },

  loadShareDetail(id, st) {
    wx.showLoading({ title: '加载中...' })
    requestPromise({
      url: `/api/test/share-detail?id=${encodeURIComponent(id)}&st=${encodeURIComponent(st)}`,
      method: 'GET'
    })
      .then((res) => {
        const body = res.data || {}
        if (body.code !== 200) {
          throw new Error(body.message || '加载失败')
        }
        this.applyDetailPayload(body.data || {})
      })
      .catch((e) => {
        wx.showToast({ title: (e && e.message) || '加载失败', icon: 'none' })
        this.setData(buildViewModel(null))
      })
      .finally(() => wx.hideLoading())
  },

  loadDetail(id, opts) {
    const silent = !!(opts && opts.silent)
    const numId = parseInt(String(id), 10)
    if (!numId || Number.isNaN(numId)) {
      return Promise.resolve()
    }
    if (!silent) wx.showLoading({ title: '加载中...' })
    const gd = app.globalData || {}
    const pricingScope = gd.appScope === 'enterprise' ? 'enterprise' : 'personal'
    let detailUrl = `/api/test/detail?id=${encodeURIComponent(numId)}&pricingScope=${encodeURIComponent(pricingScope)}`
    try {
      const eid = getEnterpriseIdForApiPayload()
      if (eid != null && Number(eid) > 0) {
        detailUrl += `&enterpriseId=${encodeURIComponent(String(eid))}`
      }
    } catch (e) {}
    return requestPromise({
      url: detailUrl,
      method: 'GET'
    })
      .then((res) => {
        const body = res.data || {}
        if (body.code !== 200) {
          throw new Error(body.message || '加载失败')
        }
        this.applyDetailPayload(body.data || {})
      })
      .catch((e) => {
        if (!silent) {
          wx.showToast({ title: (e && e.message) || '加载失败', icon: 'none' })
          this.setData(buildViewModel(null))
        }
        return Promise.reject(e)
      })
      .finally(() => {
        if (!silent) wx.hideLoading()
      })
  },

  load() {
    const rid = this._detailReportId || 0
    if (rid > 0) {
      this.loadDetail(rid)
      return
    }
    gaokaoApi
      .latestReport()
      .then((payload) => {
        const id = payload && payload.id
        if (!id) {
          throw new Error('暂无报告')
        }
        return this.loadDetail(id)
      })
      .catch((e) => {
        wx.showToast({ title: e.message || '暂无报告', icon: 'none' })
        this.setData(buildViewModel(null))
      })
  },

  goCompleteProfile() {
    try {
      require('../../utils/analytics').track('tap_complete_profile', { from: 'gaokao_report' })
    } catch (e) {}
    wx.navigateTo({ url: '/pages/user-profile/index' })
  },

  goWantTest() {
    wx.switchTab({ url: '/pages/index/index' })
  },

  goReadFullFromShare() {
    wx.switchTab({ url: '/pages/profile/index' })
  },

  onTapReadFull() {
    try {
      require('../../utils/analytics').track('tap_read_full', { type: 'gaokao' })
    } catch (e) {}
    if (this.data.profileGate) {
      unlockGate.scrollToUnlockAnchor(this)
      wx.showToast({
        title: this.data.hasPhone ? '请先完善头像与昵称' : '请在上滑区域内完成手机号授权',
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
    try {
      require('../../utils/analytics').track('tap_share_moment', { type: 'gaokao' })
    } catch (e) {}
    if (!this.data.journey.step1Unlocked) {
      wx.showToast({ title: '请先解锁全文', icon: 'none' })
      this.onTapReadFull()
      return
    }
    markShared(this.data.testResultId || '0')
    this._syncJourney()
    openTimelineShareHint()
  },

  unlockFullReport() {
    const { payInfo, testResultId, hasReloadedAfterPay } = this.data
    if (!payInfo.requiresPayment || payInfo.isPaid) return
    try {
      require('../../utils/analytics').track('tap_unlock_full', {
        type: 'gaokao',
        amountYuan: payInfo.amountYuan
      })
    } catch (e) {}
    const run =
      typeof app.ensureLogin === 'function'
        ? app.ensureLogin()
        : Promise.resolve(!!(app.globalData && app.globalData.token) || !!wx.getStorageSync('token'))
    run.then((logged) => {
      if (!logged) {
        wx.showToast({ title: '请先登录', icon: 'none' })
        return
      }
      unlockGate.ensureUnlockPrerequisitesBeforePay(this).then((ok) => {
        if (!ok) return
        inviteCodeGate.ensureInviteCodeGate(this).then((go) => {
          if (!go) return
          payment.purchaseGaokaoReport({
            testResultId: testResultId ? parseInt(String(testResultId), 10) || undefined : undefined,
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
      })
    })
  },

  onGetPhoneNumberForGaokaoPay(e) {
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
        this.setData({ hasPhone: hasPhone() })
        const profileGate = needsResultProfileGate(!!this.data.fromShare)
        this.setData({ profileGate })
        navigateToCompleteProfileAfterPhoneIfNeeded()
        this._syncJourney()
        this.unlockFullReport()
      })
      .catch(() => {})
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
        const profileGate = needsResultProfileGate(!!this.data.fromShare)
        this.setData({ profileGate })
        navigateToCompleteProfileAfterPhoneIfNeeded()
        this._syncJourney()
      })
      .catch(() => {})
  },

  onInviteCodeSkip() {
    inviteCodeGate.finishInviteCodeGate(this, true)
  },

  onInviteCodeSuccess() {
    inviteCodeGate.finishInviteCodeGate(this, true)
  }
})
