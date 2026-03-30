// pages/profile/index.js - 我的页面
const app = getApp()
const { getTypeOnly } = require('../../utils/resultFormat')
const { request } = require('../../utils/request')

Page({
  data: {
    hasLogin: false,
    userInfo: null,
    balance: 0,
    testCount: 0,
    hasResults: false,
    mbtiType: '',
    discType: '',
    pdpType: '',
    aiType: '',
    gallupPreview: '',
    mbtiTime: '',
    discTime: '',
    pdpTime: '',
    aiTime: '',
    reviewMode: false,
    /** 最近记录的数据库 ID，用于跳转时传参 */
    mbtiResultId: null,
    discResultId: null,
    pdpResultId: null,
    aiResultId: null,
    loginLoading: false,
    loginFailed: false,
    nicknameDisplay: '',
    displayNickname: '',
    /** 默认头像（根据昵称）：无头像时显示首字+背景色 */
    avatarLetter: '登',
    avatarBgColor: '#6366f1',
    // 推广中心统计与配置（来自 /api/distribution/stats）
    promoDistributionEnabled: true,
    promoCenterTitle: '推广中心',
    promoTotalInvite: 0,
    promoTotalEarned: '0.00',
    promoWithdrawable: '0.00',
    /** 是否有企业权限（绑定企业）：有则显示「我的简历」 */
    hasEnterprise: false,
    permFace: true,
    permMbti: true,
    permPdp: true,
    permDisc: true,
    permDistribution: true,
    showLatestTestCards: false,
    showEmptyPersonalityTags: true
  },

  _computeShowLatestTestCards(d) {
    const rm = !!(d.reviewMode)
    if (d.permMbti && d.mbtiType) return true
    if (d.permPdp && d.pdpType) return true
    if (d.permDisc && d.discType) return true
    if (!rm && d.permFace && (d.gallupPreview || d.aiType)) return true
    return false
  },

  _computeShowEmptyPersonalityTags(d) {
    return !((d.mbtiType && d.permMbti) || (d.discType && d.permDisc) || (d.pdpType && d.permPdp))
  },

  onLoad() {
    // 仅在 onLoad 执行一次，onShow 里的 runLoginThenLoad 会导致重复请求
    this.runLoginThenLoad()
  },
  _syncPerms(overrides = {}) {
    const p = app.globalData.enterprisePermissions
    const next = {
      permFace: !p || p.face !== false,
      permMbti: !p || p.mbti !== false,
      permPdp:  !p || p.pdp  !== false,
      permDisc: !p || p.disc !== false,
      permDistribution: !p || p.distribution !== false,
      ...overrides
    }
    const d = { ...this.data, ...next }
    this.setData({
      ...next,
      showLatestTestCards: this._computeShowLatestTestCards(d),
      showEmptyPersonalityTags: this._computeShowEmptyPersonalityTags(d)
    })
  },

  onShow() {
    const gd = app.globalData
    this._syncPerms({ reviewMode: !!(gd.reviewMode || gd.maintenanceMode) })
    // 如果是从其他页面返回，且已经登录，则只刷新数据而不重新执行登录流程
    if (this.data.hasLogin) {
      this.loadData()
    }
    
    if (typeof this.getTabBar === 'function' && this.getTabBar()) {
      const tb = this.getTabBar()
      if (typeof tb.updateSelected === 'function') tb.updateSelected()
    }
  },

  /** 先确保登录完成（静默登录），再刷新页面数据 */
  runLoginThenLoad() {
    this.setData({ loginLoading: true, loginFailed: false })
    app.ensureLogin().then((ok) => {
      this.setData({ loginLoading: false, loginFailed: !ok })
      this.loadData()
    }).catch(() => {
      this.setData({ loginLoading: false, loginFailed: true })
      this.loadData()
    })
  },

  /** 生成随机后缀：仅 26 英文字母（大小写）与数字，不含特殊字符，默认 4 位 */
  _randomWechatUserSuffix(len) {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'
    let s = ''
    for (let i = 0; i < (len || 4); i++) {
      s += chars[Math.floor(Math.random() * chars.length)]
    }
    return s
  },

  /** 根据昵称生成默认头像的首字与背景色（同一昵称同色） */
  _avatarFromNickname(name) {
    const str = (name && String(name).trim()) || '登'
    const letter = str.charAt(0).toUpperCase() || '登'
    const palette = ['#6366f1', '#8b5cf6', '#ec4899', '#f43f5e', '#14b8a6', '#0ea5e9', '#3b82f6', '#eab308']
    let hash = 0
    for (let i = 0; i < str.length; i++) hash += str.charCodeAt(i)
    const bgColor = palette[Math.abs(hash) % palette.length]
    return { avatarLetter: letter, avatarBgColor: bgColor }
  },

  /** 获取或生成当前用户的「微信用户XXXX」后缀（同一用户固定，仅 26 字母+数字） */
  _getOrCreateWechatUserSuffix(userInfo) {
    const uid = (userInfo && (userInfo.id || userInfo.userId)) ? String(userInfo.id || userInfo.userId) : '_default'
    const storageKey = 'wechat_user_suffix'
    const stored = tt.getStorageSync(storageKey)
    if (stored && typeof stored === 'object' && stored[uid]) return stored[uid]
    const suffix = this._randomWechatUserSuffix(4)
    const next = { ...(stored && typeof stored === 'object' ? stored : {}), [uid]: suffix }
    tt.setStorageSync(storageKey, next)
    return suffix
  },

  loadData() {
    const gd = app.globalData
    this._syncPerms({ reviewMode: !!(gd.reviewMode || gd.maintenanceMode) })

    const userInfo = app.globalData.userInfo || tt.getStorageSync('userInfo')
    const token = app.globalData.token || tt.getStorageSync('token')

    // 1. 先同步渲染用户基础信息（昵称/头像）
    const nickname = (userInfo && (userInfo.nickname || userInfo.nickName))
      ? String(userInfo.nickname || userInfo.nickName).trim() : ''
    let nicknameDisplay = nickname
    let displayNickname = ''
    if (!nickname && userInfo) {
      const suffix = this._getOrCreateWechatUserSuffix(userInfo)
      displayNickname = '微信用户' + suffix
      nicknameDisplay = displayNickname
    }
    const avatarFromName = userInfo
      ? this._avatarFromNickname(nicknameDisplay)
      : this._avatarFromNickname('点击登录')

    const hasEnterprise = !!(userInfo && (userInfo.hasEnterprise === true || (userInfo.enterpriseId && Number(userInfo.enterpriseId) > 0)))
    this.setData({
      hasLogin: !!token || !!userInfo,
      userInfo: userInfo || null,
      hasEnterprise,
      nicknameDisplay,
      displayNickname,
      avatarLetter: avatarFromName.avatarLetter,
      avatarBgColor: avatarFromName.avatarBgColor
    })

    // 2. 已登录则从服务端拉取最近记录；失败降级读 localStorage
    if (token || userInfo) {
      this._loadRecentFromAPI()
      this._loadPromoStats()
    }
  },

  /** 从 /api/test/recent 拉取各类型最新记录 */
  _loadRecentFromAPI() {
    request({
      url: '/api/test/recent?scope=all',
      method: 'GET',
      success: (res) => {
        // res 是 wx.request 原始响应：{ statusCode, data: { code, data, message } }
        const payload = res && res.data
        if (!payload || payload.code !== 200 || !payload.data) {
          this._loadRecentFromStorage()
          return
        }
        const { records = {}, totalCount = 0 } = payload.data
        const r = records

        // DISC resultText 后端已含「型」，type badge 只显示字母，去掉「型」
        const discType = r.disc ? r.disc.resultText.replace(/型$/, '') : ''

        const gallupPreview = (r.ai && r.ai.gallupPreview) ? String(r.ai.gallupPreview) : ''
        const patch = {
          testCount:    totalCount,
          hasResults:   !!(r.mbti || r.disc || r.pdp || r.ai),
          mbtiType:     r.mbti ? r.mbti.resultText : '',
          discType,
          pdpType:      r.pdp  ? r.pdp.resultText  : '',
          aiType:       r.ai   ? r.ai.resultText   : '',
          gallupPreview,
          mbtiTime:     r.mbti ? r.mbti.testTime : '',
          discTime:     r.disc ? r.disc.testTime : '',
          pdpTime:      r.pdp  ? r.pdp.testTime  : '',
          aiTime:       r.ai   ? r.ai.testTime   : '',
          mbtiResultId: r.mbti ? r.mbti.id : null,
          discResultId: r.disc ? r.disc.id : null,
          pdpResultId:  r.pdp  ? r.pdp.id  : null,
          aiResultId:   r.ai   ? r.ai.id   : null,
        }
        const d = { ...this.data, ...patch }
        this.setData({
          ...patch,
          showLatestTestCards: this._computeShowLatestTestCards(d),
          showEmptyPersonalityTags: this._computeShowEmptyPersonalityTags(d)
        })
      },
      fail: () => {
        this._loadRecentFromStorage()
      }
    })
  },

  /** 从 /api/distribution/stats 拉取推广中心统计 */
  _loadPromoStats() {
    request({
      url: '/api/distribution/stats',
      method: 'GET',
      success: (res) => {
        const payload = res && res.data
        if (payload && payload.code === 200 && payload.data) {
          const d = payload.data
          this.setData({
            promoDistributionEnabled: d.distributionEnabled !== false,
            promoCenterTitle: d.promoCenterTitle || '推广中心',
            promoTotalInvite: d.totalInvite || 0,
            promoTotalEarned: d.totalEarned || '0.00',
            promoWithdrawable: d.walletBalance || '0.00'
          })
        }
      }
    })
  },

  /** 降级：从 localStorage 读最近记录（兼容离线或 API 失败） */
  _loadRecentFromStorage() {
    const mbtiResult = tt.getStorageSync('mbtiResult')
    const discResult = tt.getStorageSync('discResult')
    const pdpResult  = tt.getStorageSync('pdpResult')
    const aiResult   = tt.getStorageSync('aiResult')

    let gallupPreview = ''
    if (aiResult && Array.isArray(aiResult.gallupTop3) && aiResult.gallupTop3.length) {
      gallupPreview = aiResult.gallupTop3.slice(0, 3).map(String).join('、')
    }

    let testCount = 0
    if (mbtiResult) testCount++
    if (discResult) testCount++
    if (pdpResult)  testCount++
    if (aiResult)   testCount++

    const _fmt = (ts) => {
      if (!ts) return ''
      const d = new Date(typeof ts === 'number' && ts < 1e12 ? ts * 1000 : ts)
      if (isNaN(d.getTime())) return ''
      return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
    }

    const patch = {
      testCount,
      hasResults: testCount > 0,
      mbtiType: mbtiResult ? getTypeOnly(mbtiResult, 'mbti') : '',
      discType: discResult ? getTypeOnly(discResult, 'disc') : '',
      pdpType:  pdpResult  ? getTypeOnly(pdpResult,  'pdp')  : '',
      aiType:   aiResult   ? (aiResult.mbti || aiResult.mbtiType || aiResult.type || '') : '',
      gallupPreview,
      mbtiTime: _fmt(mbtiResult && (mbtiResult.createdAt || mbtiResult.timestamp || mbtiResult.testTime)),
      discTime: _fmt(discResult && (discResult.createdAt || discResult.timestamp || discResult.testTime)),
      pdpTime:  _fmt(pdpResult  && (pdpResult.createdAt  || pdpResult.timestamp  || pdpResult.testTime)),
      aiTime:   _fmt(aiResult   && (aiResult.createdAt   || aiResult.timestamp   || aiResult.testTime)),
      mbtiResultId: null,
      discResultId: null,
      pdpResultId:  null,
      aiResultId:   null,
    }
    const d = { ...this.data, ...patch }
    this.setData({
      ...patch,
      showLatestTestCards: this._computeShowLatestTestCards(d),
      showEmptyPersonalityTags: this._computeShowEmptyPersonalityTags(d)
    })
  },

  /** 点击登录：先静默登录拿到 token */
  doLogin() {
    this.setData({ loginLoading: true, loginFailed: false })
    app.ensureLogin().then((ok) => {
      this.setData({ loginLoading: false })
      if (ok) {
        this.loadData()
      } else {
        tt.showToast({ title: '登录失败，请检查网络或稍后重试', icon: 'none' })
        this.setData({ loginFailed: true })
      }
    }).catch(() => {
      this.setData({ loginLoading: false, loginFailed: true })
      tt.showToast({ title: '登录失败', icon: 'none' })
    })
  },

  goToIndex() { tt.switchTab({ url: '/pages/index/index' }) },
  goToHistory() {
    try { require('../../utils/analytics').track('tap_test_history', {}) } catch (e) {}
    tt.navigateTo({ url: '/pages/history/index' })
  },
  goToUserProfile() { tt.navigateTo({ url: '/pages/user-profile/index' }) },
  goToDeepService() {
    try { require('../../utils/analytics').track('tap_deep_service', {}) } catch (e) {}
    tt.navigateTo({ url: '/pages/purchase/index' })
  },
  goToOrders() {
    if (!this.data.hasLogin) {
      tt.showToast({ title: '请先登录', icon: 'none' })
      return
    }
    try { require('../../utils/analytics').track('tap_my_orders', {}) } catch (e) {}
    tt.navigateTo({ url: '/pages/order/index' })
  },
  goToPurchase() { tt.navigateTo({ url: '/pages/purchase/index' }) },
  goToPurchasePersonal() { tt.navigateTo({ url: '/pages/purchase/index?tab=personal' }) },
  goToPurchaseEnterprise() { tt.navigateTo({ url: '/pages/purchase/index?tab=enterprise' }) },
  goToEnterprise() { tt.navigateTo({ url: '/pages/enterprise/index' }) },
  goToPromo() { tt.navigateTo({ url: '/pages/promo/index' }) },
  goToMyResume() { tt.navigateTo({ url: '/pages/enterprise/resume-history' }) },
  goToSettings() {
    tt.showToast({ title: '开发中', icon: 'none' })
  },
  shareApp() {
    // 触发分享
  },
  viewMBTI() {
    const id = this.data.mbtiResultId
    if (id) tt.navigateTo({ url: `/pages/result/mbti?id=${id}&type=mbti` })
    else tt.navigateTo({ url: '/pages/test/mbti' })
  },
  viewDISC() {
    const id = this.data.discResultId
    if (id) tt.navigateTo({ url: `/pages/result/disc?id=${id}&type=disc` })
    else tt.navigateTo({ url: '/pages/test/disc' })
  },
  viewPDP() {
    const id = this.data.pdpResultId
    if (id) tt.navigateTo({ url: `/pages/result/pdp?id=${id}&type=pdp` })
    else tt.navigateTo({ url: '/pages/test/pdp' })
  },
  viewGallup() {
    const id = this.data.aiResultId
    if (id) tt.navigateTo({ url: `/pages/index/result?id=${id}&type=ai` })
    else tt.switchTab({ url: '/pages/index/camera' })
  },
  viewAI() {
    const id = this.data.aiResultId
    if (id) tt.navigateTo({ url: `/pages/index/result?id=${id}&type=ai` })
    else tt.switchTab({ url: '/pages/index/camera' })
  },
  goToTestSelect() {
    try { require('../../utils/analytics').track('tap_test_select_from_profile', {}) } catch (e) {}
    tt.navigateTo({ url: '/pages/test-select/index' })
  },

  logout() {
    tt.showModal({
      title: '确认退出',
      content: '退出后测试记录仍会保留',
      success: (res) => {
        if (res.confirm) {
          app.logout()
          this.setData({ hasLogin: false, userInfo: null, loginFailed: false })
        }
      }
    })
  },

  onShareAppMessage() {
    const { getSharePathByScope } = require('../../utils/share')
    const rm = !!app.globalData.reviewMode
    return {
      title: '神仙团队性格测试 - 发现你的MBTI类型',
      path: getSharePathByScope('/pages/index/index')
    }
  },

  onShareTimeline() {
    const { buildShareQuery } = require('../../utils/share')
    const rm = !!app.globalData.reviewMode
    return {
      title: '神仙团队性格测试 - 发现你的MBTI类型',
      query: buildShareQuery()
    }
  }
})
