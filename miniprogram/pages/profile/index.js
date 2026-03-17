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
    mbtiTime: '',
    discTime: '',
    pdpTime: '',
    aiTime: '',
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
    hasEnterprise: false
  },

  onLoad() {
    // 仅在 onLoad 执行一次，onShow 里的 runLoginThenLoad 会导致重复请求
    this.runLoginThenLoad()
  },
  onShow() {
    // 如果是从其他页面返回，且已经登录，则只刷新数据而不重新执行登录流程
    if (this.data.hasLogin) {
      this.loadData()
    }
    
    if (typeof this.getTabBar === 'function' && this.getTabBar()) {
      this.getTabBar().setData({ selected: 2 })
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
    const stored = wx.getStorageSync(storageKey)
    if (stored && typeof stored === 'object' && stored[uid]) return stored[uid]
    const suffix = this._randomWechatUserSuffix(4)
    const next = { ...(stored && typeof stored === 'object' ? stored : {}), [uid]: suffix }
    wx.setStorageSync(storageKey, next)
    return suffix
  },

  loadData() {
    const userInfo = app.globalData.userInfo || wx.getStorageSync('userInfo')
    const token = app.globalData.token || wx.getStorageSync('token')

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
    const scope = app.globalData.appScope || 'personal'
    request({
      url: `/api/test/recent?scope=${scope}`,
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

        this.setData({
          testCount:    totalCount,
          hasResults:   !!(r.mbti || r.disc || r.pdp || r.ai),
          mbtiType:     r.mbti ? r.mbti.resultText : '',
          discType,
          pdpType:      r.pdp  ? r.pdp.resultText  : '',
          aiType:       r.ai   ? r.ai.resultText   : '',
          mbtiTime:     r.mbti ? r.mbti.testTime : '',
          discTime:     r.disc ? r.disc.testTime : '',
          pdpTime:      r.pdp  ? r.pdp.testTime  : '',
          aiTime:       r.ai   ? r.ai.testTime   : '',
          mbtiResultId: r.mbti ? r.mbti.id : null,
          discResultId: r.disc ? r.disc.id : null,
          pdpResultId:  r.pdp  ? r.pdp.id  : null,
          aiResultId:   r.ai   ? r.ai.id   : null,
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
    const mbtiResult = wx.getStorageSync('mbtiResult')
    const discResult = wx.getStorageSync('discResult')
    const pdpResult  = wx.getStorageSync('pdpResult')
    const aiResult   = wx.getStorageSync('aiResult')

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

    this.setData({
      testCount,
      hasResults: testCount > 0,
      mbtiType: mbtiResult ? getTypeOnly(mbtiResult, 'mbti') : '',
      discType: discResult ? getTypeOnly(discResult, 'disc') : '',
      pdpType:  pdpResult  ? getTypeOnly(pdpResult,  'pdp')  : '',
      aiType:   aiResult   ? (aiResult.mbtiType || aiResult.type || '') : '',
      mbtiTime: _fmt(mbtiResult && (mbtiResult.createdAt || mbtiResult.timestamp || mbtiResult.testTime)),
      discTime: _fmt(discResult && (discResult.createdAt || discResult.timestamp || discResult.testTime)),
      pdpTime:  _fmt(pdpResult  && (pdpResult.createdAt  || pdpResult.timestamp  || pdpResult.testTime)),
      aiTime:   _fmt(aiResult   && (aiResult.createdAt   || aiResult.timestamp   || aiResult.testTime)),
      mbtiResultId: null,
      discResultId: null,
      pdpResultId:  null,
      aiResultId:   null,
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
        wx.showToast({ title: '登录失败，请检查网络或稍后重试', icon: 'none' })
        this.setData({ loginFailed: true })
      }
    }).catch(() => {
      this.setData({ loginLoading: false, loginFailed: true })
      wx.showToast({ title: '登录失败', icon: 'none' })
    })
  },

  goToIndex() { wx.switchTab({ url: '/pages/index/index' }) },
  goToCamera() { wx.switchTab({ url: '/pages/index/camera' }) },
  goToHistory() { wx.navigateTo({ url: '/pages/history/index' }) },
  goToUserProfile() { wx.navigateTo({ url: '/pages/user-profile/index' }) },
  goToPurchase() { wx.navigateTo({ url: '/pages/purchase/index?tab=personal' }) },
  goToPurchasePersonal() { wx.navigateTo({ url: '/pages/purchase/index?tab=personal' }) },
  goToPurchaseEnterprise() { wx.navigateTo({ url: '/pages/purchase/index?tab=enterprise' }) },
  goToEnterprise() { wx.navigateTo({ url: '/pages/enterprise/index' }) },
  goToPromo() { wx.navigateTo({ url: '/pages/promo/index' }) },
  goToMyResume() { wx.navigateTo({ url: '/pages/enterprise/resume-history' }) },
  goToSettings() {
    wx.showToast({ title: '开发中', icon: 'none' })
  },
  shareApp() {
    // 触发分享
  },
  viewMBTI() {
    const id = this.data.mbtiResultId
    wx.navigateTo({ url: id ? `/pages/result/mbti?id=${id}&type=mbti` : '/pages/result/mbti' })
  },
  viewDISC() {
    const id = this.data.discResultId
    wx.navigateTo({ url: id ? `/pages/result/disc?id=${id}&type=disc` : '/pages/result/disc' })
  },
  viewPDP() {
    const id = this.data.pdpResultId
    wx.navigateTo({ url: id ? `/pages/result/pdp?id=${id}&type=pdp` : '/pages/result/pdp' })
  },
  viewAI() {
    const id = this.data.aiResultId
    wx.navigateTo({ url: id ? `/pages/index/result?id=${id}&type=ai` : '/pages/index/result' })
  },

  logout() {
    wx.showModal({
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
    return {
      title: '神仙团队AI性格测试 - 发现你的MBTI类型',
      path: getSharePathByScope('/pages/index/index')
    }
  },

  onShareTimeline() {
    const { buildShareQuery } = require('../../utils/share')
    return {
      title: '神仙团队AI性格测试 - 发现你的MBTI类型',
      query: buildShareQuery()
    }
  }
})
