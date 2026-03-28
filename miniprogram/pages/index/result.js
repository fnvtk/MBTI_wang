// pages/index/result.js - AI分析结果页（按旧版模板重构）
const app = getApp()
const payment = require('../../utils/payment')
const { hasPhone, bindPhoneByCode, isProfileComplete } = require('../../utils/phoneAuth.js')
const { mbtiDescriptions } = require('../../utils/descriptions')

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

/** 仅在后端已下发完整面相等字段时合并；预览脱敏接口不下发，避免未付费用户看到正文 */
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
const { getEnterpriseIdForApiPayload } = require('../../utils/enterpriseContext.js')

Page({
  data: {
    isAnalyzing: true,
    showResult: false,
    hasError: false,
    errorMessage: '',
    noFaceError: false,
    noFaceMessage: '',
    progress: 0,
    analyzingTip: '正在识别面部特征...',
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
    analyzingTitle: '正在分析中',
    reportTitle: '分析报告',
    aiAnalysisText: '智能分析'
  },

  onLoad(options) {
    this._payInfoSetByDetail = false
    const id = options && options.id
    const type = options && options.type

    // 加载文案配置（分析中提示、报告标题等）
    const tc = app.globalData.textConfig
    if (tc) {
      this.setData({
        analyzingTitle: tc.analyzingTitle || '正在分析中',
        reportTitle: tc.reportTitle || '分析报告',
        aiAnalysisText: tc.aiAnalysisText || '智能分析'
      })
      if (tc.reportTitle) wx.setNavigationBarTitle({ title: tc.reportTitle })
    } else {
      app.getRuntimeConfig().then((cfg) => {
        if (cfg && cfg.textConfig) {
          app.globalData.textConfig = cfg.textConfig
          const t = cfg.textConfig
          this.setData({
            analyzingTitle: t.analyzingTitle || '正在分析中',
            reportTitle: t.reportTitle || '分析报告',
            aiAnalysisText: t.aiAnalysisText || '智能分析'
          })
          if (t.reportTitle) wx.setNavigationBarTitle({ title: t.reportTitle })
        }
      }).catch(() => {})
    }

    // 从历史记录进入：根据ID从后端读取数据库中的结果
    if (id && type === 'ai') {
      const token = app.globalData.token || wx.getStorageSync('token')
      const apiBase = app.globalData.apiBase
      if (!token || !apiBase) {
        wx.showToast({ title: '未登录，无法读取历史记录', icon: 'none' })
        setTimeout(() => wx.navigateBack(), 1500)
        return
      }

      wx.showLoading({ title: '加载历史记录...' })
      wx.request({
        url: `${apiBase}/api/test/detail`,
        method: 'GET',
        header: {
          'Authorization': `Bearer ${token}`
        },
        data: { id },
        success: (res) => {
          wx.hideLoading()
          if (res.statusCode === 200 && res.data && res.data.data) {
            const payload = res.data.data
            const apiData = payload.data || payload
            // 历史详情场景下，记录当前测试记录ID
            this.setData({ testResultId: id })
            // 先确定付费状态（设置 _payInfoSetByDetail 标记），再渲染结果
            // 避免 processResult 内部异步拉全局配置覆盖掉数据库级别的付费判定
            this.initPayInfoFromRuntime(
              !!payload.requiresPayment,
              !!payload.isPaid,
              payload
            )
            this.processResult(apiData)
          } else {
            wx.showToast({ title: res.data?.message || '加载失败', icon: 'none' })
            setTimeout(() => wx.navigateBack(), 1500)
          }
        },
        fail: () => {
          wx.hideLoading()
          wx.showToast({ title: '网络错误，加载失败', icon: 'none' })
          setTimeout(() => wx.navigateBack(), 1500)
        }
      })
      return
    }

    // 正常从拍照流程进入：调用 /api/analyze
    this.startAnalysis()
  },

  onShow() {
    this.setData({ hasPhone: hasPhone(), isProfileComplete: isProfileComplete() })
    const tc = app.globalData.textConfig
    if (tc) {
      this.setData({
        analyzingTitle: tc.analyzingTitle || '正在分析中',
        reportTitle: tc.reportTitle || '分析报告',
        aiAnalysisText: tc.aiAnalysisText || '智能分析'
      })
    }
  },

  // 面相/骨相 Tab 切换
  switchAnalysisTab(e) {
    this.setData({ activeTab: e.currentTarget.dataset.tab })
  },

  // 调用后端AI分析API（aiPhotos 应为上传后的 URL 数组，由拍照页上传后写入）
  startAnalysis() {
    let photos = wx.getStorageSync('aiPhotos') || []
    if (!Array.isArray(photos)) photos = []
    const isUrls = photos.length > 0 && photos.every(p => typeof p === 'string' && (p.startsWith('http://') || p.startsWith('https://')))
    if (!isUrls || photos.length === 0) {
      this.setData({ progress: 100 })
      setTimeout(() => {
        wx.showToast({ title: '请先完成拍照并上传', icon: 'none' })
        wx.navigateBack({ delta: 1 })
      }, 300)
      return
    }
    const tips = [
      '正在识别面部特征...',
      '分析眉眼特征...',
      '结合《冰鉴》分析骨形...',
      '匹配MBTI/PDP/DISC...',
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

    // 个人版入口不带企业参数；企业版才回落绑定/默认企业
    const enterpriseId = getEnterpriseIdForApiPayload()
    wx.request({
      url: `${app.globalData.apiBase}/api/analyze`,
      method: 'POST',
      header: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${wx.getStorageSync('token') || ''}`
      },
      data: {
        photoUrls: photos,
        userId: app.globalData.openId || '',
        ...(enterpriseId != null ? { enterpriseId: Number(enterpriseId) } : {})
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
    const base = {
      mbti:             apiData.mbti?.type || '',
      title:            apiData.mbti?.title || '',
      summary:          apiData.personalitySummary || apiData.overview || '',
      pdp:              apiData.pdp?.primary || '',
      pdpAux:           apiData.pdp?.secondary || '',
      pdpEmoji:         this.getPDPEmoji(apiData.pdp?.primary),
      disc:             apiData.disc?.primary || '',
      discAux:          apiData.disc?.secondary || '',
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

    wx.setStorageSync('aiResult', result)
    if (app.globalData) app.globalData.aiResult = result
    // 测试记录已由 /api/analyze 顺带写入，无需再调 /api/test/submit

    const updates = {
      isAnalyzing: false,
      showResult: true,
      hasError: false,
      result
    }

    // 记录本次测试记录ID（由 /api/analyze 返回）
    if (apiData && apiData._testResultId) {
      updates.testResultId = apiData._testResultId
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
      // 拍照直连分析未带 _payment 时，拉 runtime 定价
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
        // 后台开启人脸报告付费但未配有效单价时，按 ¥1 展示并走支付
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
    wx.navigateTo({ url: '/pages/user-profile/index' })
  },

  /** 付费墙主按钮：先支付，支付后再引导手机号与资料 */
  onTapUnlockPay() {
    try { require('../../utils/analytics').track('tap_unlock_pay', { amount: this.data.payInfo.amountYuan, testResultId: this.data.testResultId }) } catch (e) {}
    this.unlockFullReport()
  },

  // 解锁完整报告：发起人脸测试付费（不要求先完善资料）
  unlockFullReport() {
    const { payInfo, testResultId, hasReloadedAfterPay } = this.data
    if (!payInfo.requiresPayment || payInfo.isPaid) return

    app.ensureLogin().then((logged) => {
      if (!logged) {
        wx.showToast({ title: '请先登录后再解锁', icon: 'none' })
        return
      }
      payment.purchaseFaceTest({
        testResultId,
        success: () => {
          wx.showToast({ title: '已解锁完整报告', icon: 'success' })

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

  /** 支付成功后：仅绑定手机号，不重复发起支付 */
  onPostPayBindPhone(e) {
    const { code, errMsg } = e.detail || {}
    if (errMsg && errMsg.indexOf('getPhoneNumber:fail') === 0) {
      wx.showToast({ title: '需要手机号以便保存报告', icon: 'none' })
      return
    }
    if (!code) {
      wx.showToast({ title: '获取手机号失败', icon: 'none' })
      return
    }
    bindPhoneByCode(code)
      .then(() => {
        this.setData({ hasPhone: true, isProfileComplete: isProfileComplete() })
        wx.showToast({ title: '已绑定手机号', icon: 'success' })
      })
      .catch(() => {})
  },

  // 兼容旧版：付费前绑手机（现流程已改为先付费，此方法可不再使用）
  onGetPhoneNumberForFacePay(e) {
    const { code, errMsg } = e.detail || {}
    if (errMsg && errMsg.indexOf('getPhoneNumber:fail') === 0) {
      if (hasPhone()) {
        this.unlockFullReport()
      } else {
        wx.showToast({ title: '需要授权手机号才能继续', icon: 'none' })
      }
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

  // 解锁成功后，根据测试记录ID重新拉取完整详情
  reloadFullDetail(id) {
    if (!id) return

    const app = getApp()
    const apiBase = app.globalData?.apiBase || ''

    wx.showLoading({ title: '加载完整报告...' })
    wx.request({
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
        wx.hideLoading()
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

  shareResult() {
    wx.showShareMenu({ withShareTicket: true, menus: ['shareAppMessage', 'shareTimeline'] })
  },

  retake() {
    // camera 是 tabBar 页面，必须用 switchTab
    wx.switchTab({ url: '/pages/index/camera' })
  },

  goHome() {
    // 根据当前 scope 跳对应首页
    const scope = (getApp().globalData && getApp().globalData.appScope) || 'personal'
    if (scope === 'enterprise') {
      wx.navigateTo({ url: '/pages/enterprise/index' })
    } else {
      wx.switchTab({ url: '/pages/index/index' })
    }
  },

  onShareAppMessage() {
    const r = this.data.result
    const t = this.data.aiAnalysisText || '智能分析'
    const { getSharePathByScope } = require('../../utils/share')
    return {
      title: `${t}我是${r?.mbti} ${r?.pdpEmoji}${r?.pdp}型，来测测你的！`,
      path: getSharePathByScope('/pages/index/index')
    }
  },

  onShareTimeline() {
    const r = this.data.result
    const t = this.data.aiAnalysisText || '智能分析'
    const { buildShareQuery } = require('../../utils/share')
    return {
      title: `${t}我是${r?.mbti} ${r?.pdpEmoji}${r?.pdp}型，来测测你的！`,
      query: buildShareQuery()
    }
  }
})
