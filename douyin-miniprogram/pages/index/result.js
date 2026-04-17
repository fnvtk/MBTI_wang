// pages/index/result.js - 分析结果页
const app = getApp()
const payment = require('../../utils/payment')
const { hasPhone, bindPhoneByCode, isProfileComplete } = require('../../utils/phoneAuth.js')
const { mbtiDescriptions } = require('../../utils/descriptions')
const { triggerTestResultCompleted } = require('../../utils/pushHook')

function buildSceneFallback(baseResult) {
  const mbti = baseResult.mbti || ''
  const d = mbtiDescriptions[mbti] || {}
  const jobs = (d.careers || []).slice(0, 2).join('、') || '专业岗位'
  const name = d.name || baseResult.title || ''
  return {
    careerDevelopment: `结合 ${mbti}${name ? '（' + name + '）' : ''} 特质，${jobs} 等与系统性、长期主义更契合的路径往往更容易做出成绩；建议前 1～3 年夯实基本功与协作习惯，再向骨干或专家角色过渡。`,
    familyParenting: '家庭互动中可减少「对错评判」、增加情感确认；给孩子清晰边界的同时也留出讨论与试错空间，更利于信任感与自驱力。',
    partnerCofounder: '寻找合伙人时建议重点考察责任感与信息透明度，角色分工、决策机制与退出规则尽量书面化；互补型搭档通常比同特质堆叠更有效。'
  }
}

function mergeSceneBlocks(apiData, baseResult) {
  const trim = (s) => (s && String(s).trim()) || ''
  const apiC = trim(apiData.careerDevelopment)
  const apiF = trim(apiData.familyParenting)
  const apiP = trim(apiData.partnerCofounder)
  const fa = apiData.faceAnalysis
  const bone = apiData.boneAnalysis
  const rel = typeof apiData.relationship === 'string' ? apiData.relationship.trim() : ''
  const hasUnlockedReport =
    rel.length > 8 ||
    (typeof fa === 'string' && fa.length > 40) ||
    (fa && typeof fa === 'object' && !Array.isArray(fa)) ||
    (typeof apiData.faceAnalysisText === 'string' && apiData.faceAnalysisText.length > 40) ||
    (typeof bone === 'string' && bone.length > 40) ||
    (bone && typeof bone === 'object' && !Array.isArray(bone)) ||
    (typeof apiData.boneAnalysisText === 'string' && apiData.boneAnalysisText.length > 40)
  if (!hasUnlockedReport) {
    return { careerDevelopment: '', familyParenting: '', partnerCofounder: '' }
  }
  const fb = buildSceneFallback(baseResult)
  return {
    careerDevelopment: apiC || fb.careerDevelopment,
    familyParenting: apiF || fb.familyParenting,
    partnerCofounder: apiP || fb.partnerCofounder
  }
}

Page({
  data: {
    isAnalyzing: true,
    showResult: false,
    hasError: false,
    errorMessage: '',
    noFaceError: false,
    noFaceMessage: '',
    progress: 0,
    analyzingTip: '正在分析中...',
    activeTab: 'face', // 'face' | 'bone'
    // 报告付费信息
    payInfo: {
      requiresPayment: false, // 是否需要付费才解锁完整报告
      isPaid: false,          // 当前这次是否已解锁
      amountYuan: 0           // 人脸报告价格（元）
    },
    result: {
      mbti: '',
      title: '',
      summary: '',
      pdp: '',
      pdpAux: '',
      pdpEmoji: '',
      disc: '',
      discAux: '',
      sbtiCode: '',
      sbtiCn: '',
      sbtiMainTypeDesc: '',
      traits: [],
      faceAnalysisText: '',
      boneAnalysisText: '',
      careers: [],
      relationship: '',
      gallupTop3: [],
      // 完整面相/骨相（object 才用，字符串走 *Text 字段）
      faceAnalysis: null,
      boneAnalysis: null,
      // 企业版：职业画像、HR视角、老板视角、简历亮点
      portrait: null,
      hrView: null,
      bossView: null,
      resumeHighlights: '',
      careerDevelopment: '',
      familyParenting: '',
      partnerCofounder: ''
    },
    // 当前这次AI分析对应的测试记录ID（mbti_test_results.id）
    testResultId: null,
    // 支付后是否已经触发过一次“刷新完整报告”，避免重复刷新
    hasReloadedAfterPay: false,
    // 是否已在本地拥有手机号（决定是否还需要弹出微信手机号授权）
    hasPhone: false,
    isProfileComplete: false,
    showSbtiFace: true,
    analyzingTitle: '正在分析中',
    reportTitle: '分析报告',
    aiAnalysisText: '分析',
    reviewMode: true
  },

  onLoad(options) {
    this._payInfoSetByDetail = false

    // 同步审核模式
    this.setData({ reviewMode: !!app.globalData.reviewMode })

    // 加载文案配置（分析中提示、报告标题等）
    const tc = app.globalData.textConfig
    if (tc) {
      this.setData({
        analyzingTitle: tc.analyzingTitle || '正在分析中',
        reportTitle: tc.reportTitle || '分析报告',
        aiAnalysisText: tc.aiAnalysisText || '分析'
      })
      if (tc.reportTitle) tt.setNavigationBarTitle({ title: tc.reportTitle })
    } else {
      app.getRuntimeConfig().then((cfg) => {
        if (cfg && cfg.textConfig) {
          app.globalData.textConfig = cfg.textConfig
          const t = cfg.textConfig
          this.setData({
            analyzingTitle: t.analyzingTitle || '正在分析中',
            reportTitle: t.reportTitle || '分析报告',
            aiAnalysisText: t.aiAnalysisText || '分析'
          })
          if (t.reportTitle) tt.setNavigationBarTitle({ title: t.reportTitle })
        }
      }).catch(() => {})
    }

    const idStr = options && options.id != null && options.id !== '' ? String(options.id) : ''
    const st = options && options.st ? String(options.st).trim() : ''
    const rawType = options && options.type ? String(options.type).toLowerCase() : ''
    const faceType = rawType || (idStr ? 'ai' : '')

    if (idStr && (faceType === 'ai' || faceType === 'face')) {
      this.setData({ testResultId: idStr })
      this.loadFaceRecordById(idStr, st, faceType)
      return
    }

    if (idStr) {
      tt.showToast({ title: '链接参数无效', icon: 'none' })
      setTimeout(() => tt.navigateBack(), 1500)
      return
    }

    this.startAnalysis()
  },

  loadFaceRecordById(id, st, typeParam) {
    const apiBase = app.globalData?.apiBase || ''
    if (!apiBase) {
      tt.showToast({ title: '配置异常', icon: 'none' })
      return
    }
    const token = app.globalData.token || tt.getStorageSync('token') || ''

    const applyPayload = (payload) => {
      tt.hideLoading()
      const apiData = payload.data || payload
      this.initPayInfoFromRuntime(!!payload.requiresPayment, !!payload.isPaid, payload)
      this.processResult(apiData)
    }

    const loadDetailAuthed = () => {
      if (!token) {
        tt.hideLoading()
        tt.showToast({ title: '未登录，无法查看该记录', icon: 'none' })
        setTimeout(() => tt.navigateBack(), 1500)
        return
      }
      tt.showLoading({ title: '加载中...' })
      tt.request({
        url: `${apiBase}/api/test/detail`,
        method: 'GET',
        header: { Authorization: `Bearer ${token}` },
        data: { id },
        success: (res) => {
          if (res.statusCode === 200 && res.data && res.data.code === 200 && res.data.data) {
            applyPayload(res.data.data)
          } else {
            tt.hideLoading()
            tt.showToast({ title: res.data?.message || '加载失败', icon: 'none' })
            setTimeout(() => tt.navigateBack(), 1500)
          }
        },
        fail: () => {
          tt.hideLoading()
          tt.showToast({ title: '网络错误', icon: 'none' })
          setTimeout(() => tt.navigateBack(), 1500)
        }
      })
    }

    tt.showLoading({ title: '加载中...' })
    const data = {
      id: String(id),
      type: typeParam === 'face' ? 'face' : 'ai'
    }
    if (st) data.st = st

    tt.request({
      url: `${apiBase}/api/test/share-detail`,
      method: 'GET',
      data,
      success: (res) => {
        if (res.statusCode === 200 && res.data && res.data.code === 200 && res.data.data) {
          applyPayload(res.data.data)
          return
        }
        tt.hideLoading()
        loadDetailAuthed()
      },
      fail: () => {
        tt.hideLoading()
        loadDetailAuthed()
      }
    })
  },

  onShow() {
    const ep = app.globalData.enterprisePermissions
    const showSbtiFace = !ep || ep.sbti !== false
    this.setData({
      hasPhone: hasPhone(),
      isProfileComplete: isProfileComplete(),
      showSbtiFace
    })
    const tc = app.globalData.textConfig
    if (tc) {
      this.setData({
        analyzingTitle: tc.analyzingTitle || '正在分析中',
        reportTitle: tc.reportTitle || '分析报告',
        aiAnalysisText: tc.aiAnalysisText || '分析'
      })
    }
  },

  // 面相/骨相 Tab 切换
  switchAnalysisTab(e) {
    this.setData({ activeTab: e.currentTarget.dataset.tab })
  },

  // 调用后端AI分析API（aiPhotos 应为上传后的 URL 数组，由拍照页上传后写入）
  startAnalysis() {
    let photos = tt.getStorageSync('aiPhotos') || []
    if (!Array.isArray(photos)) photos = []
    const isUrls = photos.length > 0 && photos.every(p => typeof p === 'string' && (p.startsWith('http://') || p.startsWith('https://')))
    if (!isUrls || photos.length === 0) {
      this.setData({ progress: 100 })
      setTimeout(() => {
        tt.showToast({ title: '请先完成拍照并上传', icon: 'none' })
        tt.navigateBack({ delta: 1 })
      }, 300)
      return
    }
    const tips = [
      '正在分析数据...',
      '匹配性格特征...',
      '综合评估中...',
      '匹配MBTI/PDP/DISC/SBTI...',
      '生成综合报告...'
    ]
    let progress = 0
    let tipIndex = 0

    // 进度动画
    const timer = setInterval(() => {
      progress += 3
      if (progress > 95) progress = 95
      if (progress > (tipIndex + 1) * 18 && tipIndex < tips.length - 1) tipIndex++
      this.setData({ progress: Math.floor(progress), analyzingTip: tips[tipIndex] })
    }, 200)

    // 调用后端API：appScope='enterprise' 时才传 enterpriseId，个人版不传
    const userInfo = app.globalData.userInfo || tt.getStorageSync('userInfo') || {}
    const scope = (app.globalData && app.globalData.appScope) || 'personal'
    const enterpriseId = scope === 'enterprise'
      ? (app.globalData.enterpriseIdFromScene || userInfo.enterpriseId || null)
      : null
    tt.request({
      url: `${app.globalData.apiBase}/api/analyze`,
      method: 'POST',
      header: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${tt.getStorageSync('token') || ''}`
      },
      data: {
        photoUrls: photos,
        userId: app.globalData.openId || '',
        ...(enterpriseId ? { enterpriseId: Number(enterpriseId) } : {})
      },
      success: (res) => {
        clearInterval(timer)

        if (res.statusCode === 200 && res.data) {
          const bodyCode = res.data.code
          // 后端 error() 始终返回 HTTP 200，业务错误码放在 data.code 中
          if (bodyCode === 422) {
            // AI 未检测到人脸
            const msg = res.data.message || '图片中未检测到人脸，请确保拍摄时面部清晰可见'
            this.setData({ progress: 100 })
            setTimeout(() => this.showNoFaceError(msg), 300)
          } else if (bodyCode === 200) {
            const apiData = res.data.data || res.data
            try { require('../../utils/analytics').track('ai_analysis_complete', { mbti: (apiData.mbti && apiData.mbti.type) || '' }) } catch (e) {}
            this.setData({ progress: 100, analyzingTip: '分析完成！' })
            setTimeout(() => {
              this.processResult(apiData)
            }, 500)
          } else {
            console.error('API返回错误:', res)
            this.setData({ progress: 100 })
            const msg = (res.data && (res.data.message || res.data.msg)) || '分析失败，请稍后重试'
            setTimeout(() => this.showAnalyzeError(msg), 300)
          }
        } else {
          console.error('API返回错误:', res)
          this.setData({ progress: 100 })
          setTimeout(() => this.showAnalyzeError('分析失败，请稍后重试'), 300)
        }
      },
      fail: (err) => {
        clearInterval(timer)
        console.error('API调用失败:', err)
        this.setData({ progress: 100 })
        setTimeout(() => this.showAnalyzeError('网络异常，请检查网络后重试'), 300)
      }
    })
  },

  // 处理API返回结果
  processResult(apiData) {
    const ep = app.globalData.enterprisePermissions
    const showSbtiFace = !ep || ep.sbti !== false
    const sbtiCode = showSbtiFace ? (apiData.sbti?.code || apiData.sbtiType || '') : ''
    const sbtiCn = showSbtiFace ? (apiData.sbti?.cn || apiData.sbtiCn || '') : ''
    const sbtiMainTypeDesc = (showSbtiFace && (sbtiCode || sbtiCn))
      ? (apiData.sbti?.mainTypeDesc || apiData.sbtiMainTypeDesc || '维度命中度较高，当前结果可视为你的第一人格画像。')
      : ''
    const base = {
      mbti:             apiData.mbti?.type || '',
      title:            apiData.mbti?.title || '',
      summary:          apiData.personalitySummary || apiData.overview || '',
      pdp:              apiData.pdp?.primary || '',
      pdpAux:           apiData.pdp?.secondary || '',
      pdpEmoji:         this.getPDPEmoji(apiData.pdp?.primary),
      disc:             apiData.disc?.primary || '',
      discAux:          apiData.disc?.secondary || '',
      sbtiCode,
      sbtiCn,
      sbtiMainTypeDesc,
      traits:           Array.isArray(apiData.advantages) ? apiData.advantages : [],
      faceAnalysisText: typeof apiData.faceAnalysis === 'string' ? apiData.faceAnalysis : '',
      boneAnalysisText: typeof apiData.boneAnalysis === 'string' ? apiData.boneAnalysis : '',
      careers:          Array.isArray(apiData.careers) ? apiData.careers : [],
      relationship:     apiData.relationship || '',
      gallupTop3:       Array.isArray(apiData.gallupTop3) ? apiData.gallupTop3 : []
    }
    const scene = mergeSceneBlocks(apiData, base)
    const result = {
      ...base,
      ...scene,
      faceAnalysis:     null,
      boneAnalysis:     null,
      portrait:         apiData.portrait || null,
      hrView:           apiData.hrView || null,
      bossView:         apiData.bossView || null,
      resumeHighlights: apiData.resumeHighlights || '',
      timestamp:        Date.now()
    }

    tt.setStorageSync('aiResult', result)
    if (app.globalData) app.globalData.aiResult = result
    // 测试记录已由 /api/analyze 顺带写入，无需再调 /api/test/submit

    const updates = {
      isAnalyzing: false,
      showResult: true,
      hasError: false,
      result,
      showSbtiFace
    }

    // 记录本次测试记录ID（由 /api/analyze 返回）
    if (apiData && apiData._testResultId) {
      updates.testResultId = apiData._testResultId
      triggerTestResultCompleted(apiData._testResultId)
    }

    this.setData(updates)

    // 优先使用后端 /api/analyze 返回的价格信息，避免二次请求
    if (apiData._payment) {
      const p = apiData._payment || {}
      let amountYuan = typeof p.amountYuan === 'number'
        ? p.amountYuan
        : (p.amountFen ? (p.amountFen / 100) : 0)
      if (p.requiresPayment && amountYuan <= 0) amountYuan = 1
      this.setData({
        payInfo: {
          requiresPayment: !!p.requiresPayment,
          isPaid: false,
          amountYuan
        }
      })
    } else if (!this._payInfoSetByDetail) {
      // 历史详情入口已由 initPayInfoFromRuntime(detailPayload) 处理过，不再覆盖
      this.initPayInfoFromRuntime()
    }
  },


  // PDP类型对应emoji
  getPDPEmoji(type) {
    const map = {
      '老虎': '🐅', '孔雀': '🦚', '无尾熊': '🐨', '考拉': '🐨',
      '猫头鹰': '🦉', '变色龙': '🦎'
    }
    return map[type] || '🐅'
  },

  /**
   * 初始化付费信息：结合运行配置 + 可选的数据库记录状态
   * @param {boolean|null} recordRequires 是否从记录中读到需要付费（可为空）
   * @param {boolean} recordIsPaid 记录是否已付费
   */
  initPayInfoFromRuntime(recordRequires = null, recordIsPaid = false, detailPayload = null) {
    // 从历史/详情进入：优先用 test_results 的 paidAmount，需付款但金额为0 则直接可查看
    if (detailPayload && (detailPayload.paidAmount != null || detailPayload.amountYuan != null || detailPayload.needPaymentToUnlock != null)) {
      const paidAmount = Number(detailPayload.paidAmount ?? 0)
      const amountYuan = detailPayload.amountYuan != null ? Number(detailPayload.amountYuan) : (paidAmount > 0 ? paidAmount / 100 : 0)
      const needPay = detailPayload.needPaymentToUnlock === true || (!!detailPayload.requiresPayment && !detailPayload.isPaid && paidAmount > 0)
      // 标记：历史详情已确定付费状态，processResult 中不再用全局配置覆盖
      this._payInfoSetByDetail = true
      this.setData({
        payInfo: {
          requiresPayment: needPay,
          isPaid: !!detailPayload.isPaid,
          amountYuan: needPay ? amountYuan : 0
        },
        hasPhone: hasPhone(),
        isProfileComplete: isProfileComplete()
      })
      return
    }
    app.getRuntimeConfig()
      .then((cfg) => {
        const pricing = cfg.pricing || {}
        const reportRequires = cfg.reportRequiresPayment || {}
        const facePriceRaw = pricing.face
        const facePrice = typeof facePriceRaw === 'number'
          ? facePriceRaw
          : Number(facePriceRaw || 0)

        const requiresByConfig = !!(reportRequires && reportRequires.face)
        let requiresPayment =
          typeof recordRequires === 'boolean' ? recordRequires : requiresByConfig
        let amountYuan = facePrice > 0 ? facePrice : 0
        let needPay = requiresPayment && amountYuan > 0
        if (requiresByConfig && !needPay) {
          amountYuan = 1
          needPay = true
          requiresPayment = true
        }

        this.setData({
          payInfo: {
            requiresPayment: needPay,
            isPaid: !!recordIsPaid,
            amountYuan: needPay ? amountYuan : 0
          },
          hasPhone: hasPhone(),
          isProfileComplete: isProfileComplete()
        })
      })
      .catch(() => {
        this.setData({
          payInfo: {
            requiresPayment: false,
            isPaid: !!recordIsPaid,
            amountYuan: 0
          },
          hasPhone: hasPhone(),
          isProfileComplete: isProfileComplete()
        })
      })
  },

  goToCompleteProfile() {
    tt.navigateTo({ url: '/pages/user-profile/index' })
  },

  onTapUnlockPay() {
    try { require('../../utils/analytics').track('tap_unlock_pay', { amount: this.data.payInfo.amountYuan, testResultId: this.data.testResultId }) } catch (e) {}
    this.unlockFullReport()
  },

  // 解锁完整报告：发起人脸测试付费
  unlockFullReport() {
    const { payInfo, testResultId, hasReloadedAfterPay } = this.data
    if (!payInfo.requiresPayment || payInfo.isPaid) {
      return
    }

    app.ensureLogin().then((logged) => {
      if (!logged) {
        tt.showToast({ title: '请先登录后再解锁', icon: 'none' })
        return
      }
      payment.purchaseFaceTest({
        testResultId,
        success: () => {
          tt.showToast({ title: '已解锁完整报告', icon: 'success' })

          this.setData({
            'payInfo.isPaid': true,
            hasPhone: hasPhone(),
            isProfileComplete: isProfileComplete()
          })

          if (testResultId && !hasReloadedAfterPay) {
            this.setData({ hasReloadedAfterPay: true })
            setTimeout(() => {
              this.reloadFullDetail(testResultId)
            }, 500)
          }
        },
        fail: () => {}
      })
    })
  },

  onPostPayBindPhone(e) {
    const { code, errMsg } = e.detail || {}
    if (errMsg && errMsg.indexOf('getPhoneNumber:fail') === 0) {
      tt.showToast({ title: '需要手机号以便保存报告', icon: 'none' })
      return
    }
    if (!code) {
      tt.showToast({ title: '获取手机号失败', icon: 'none' })
      return
    }
    bindPhoneByCode(code)
      .then(() => {
        this.setData({ hasPhone: true, isProfileComplete: isProfileComplete() })
        tt.showToast({ title: '已绑定手机号', icon: 'success' })
      })
      .catch(() => {})
  },

  // 解锁成功后，根据测试记录ID重新拉取完整详情
  reloadFullDetail(id) {
    if (!id) return

    const app = getApp()
    const apiBase = app.globalData?.apiBase || ''

    tt.showLoading({ title: '加载完整报告...' })
    tt.request({
      url: `${apiBase}/api/test/detail`,
      method: 'GET',
      header: {
        'Authorization': app.globalData?.token ? `Bearer ${app.globalData.token}` : ''
      },
      data: { id },
      success: (res) => {
        if (res.statusCode === 200 && res.data && res.data.data) {
          const payload = res.data.data
          const apiData = payload.data || payload

          // 先确定付费状态，再渲染结果（避免 processResult 异步覆盖）
          const recordRequires =
            typeof payload.requiresPayment === 'boolean' || typeof payload.requiresPayment === 'number'
              ? !!payload.requiresPayment
              : null
          const recordIsPaid = !!payload.isPaid
          this.initPayInfoFromRuntime(recordRequires, recordIsPaid, payload)
          this.processResult(apiData)
        } else {
          console.error('刷新完整报告失败', res)
        }
      },
      fail: (err) => {
        console.error('请求完整报告失败', err)
      },
      complete: () => {
        tt.hideLoading()
      }
    })
  },

  // 无人脸错误：显示提示并提供重新拍摄入口
  showNoFaceError(message) {
    this.setData({
      isAnalyzing: false,
      showResult: false,
      noFaceError: true,
      noFaceMessage: message || '图片中未检测到人脸，请重新拍摄清晰的正面照片'
    })
  },

  // 分析失败：不使用任何本地模拟数据
  showAnalyzeError(message) {
    this.setData({
      isAnalyzing: false,
      showResult: true,
      hasError: true,
      errorMessage: message || '分析失败，请稍后重试'
    })
  },

  // 跳转到详情性格测试选择页（MBTI / PDP / DISC 三选一）
  shareResult() {
    tt.showShareMenu({ withShareTicket: true, menus: ['shareAppMessage'] })
  },

  goToQuestionnaireTest() {
    tt.navigateTo({ url: '/pages/test-select/index' })
  },

  goToQuestionnaireFromFace() {
    tt.navigateTo({ url: '/pages/test-select/index' })
  },

  goToDeepServiceFromFace() {
    tt.navigateTo({ url: '/pages/purchase/index' })
  },

  goToPromoFromFace() {
    tt.showShareMenu && tt.showShareMenu({ withShareTicket: true, menus: ['shareAppMessage'] })
    tt.navigateTo({ url: '/pages/promo/index' })
  },

  retake() {
    // camera 是 tabBar 页面，必须用 switchTab
    tt.switchTab({ url: '/pages/index/camera' })
  },

  goHome() {
    // 根据当前 scope 跳对应首页
    const scope = (getApp().globalData && getApp().globalData.appScope) || 'personal'
    if (scope === 'enterprise') {
      tt.navigateTo({ url: '/pages/enterprise/index' })
    } else {
      tt.switchTab({ url: '/pages/index/index' })
    }
  },

  onShareAppMessage() {
    const r = this.data.result
    const rm = this.data.reviewMode
    const t = rm ? '测试结果' : (this.data.aiAnalysisText || '分析')
    const { getResultSharePath, getSharePathByScope } = require('../../utils/share')
    const tid = this.data.testResultId
    if (tid) {
      return {
        title: `${t}：我是${r?.mbti} ${r?.pdpEmoji}${r?.pdp}型，来测测你的！`,
        path: getResultSharePath('/pages/index/result', { id: tid, type: 'ai' })
      }
    }
    return {
      title: `${t}：我是${r?.mbti} ${r?.pdpEmoji}${r?.pdp}型，来测测你的！`,
      path: getSharePathByScope('/pages/index/index')
    }
  },

  onShareTimeline() {
    const r = this.data.result
    const rm = this.data.reviewMode
    const t = rm ? '测试结果' : (this.data.aiAnalysisText || '分析')
    const { getResultShareTimelineQuery, buildShareQuery } = require('../../utils/share')
    const tid = this.data.testResultId
    return {
      title: `${t}：我是${r?.mbti} ${r?.pdpEmoji}${r?.pdp}型，来测测你的！`,
      query: tid ? getResultShareTimelineQuery({ id: tid, type: 'ai' }) : buildShareQuery()
    }
  }
})
