// pages/profile/index.js - 我的页面
const app = getApp()
const { getTypeOnly } = require('../../utils/resultFormat')
const { request } = require('../../utils/request')
const { buildRecoMiniProgramPath } = require('../../utils/recoMiniProgram.js')
const { isAuditHideAiMode } = require('../../utils/miniprogramAuditGate.js')
const inviteCodeGate = require('../../utils/inviteCodeGate.js')

/** 与超管「小程序 · 推荐文章展示」中「区块标题」一致；接口无字段时的兜底 */
const PROFILE_RECO_LABEL_FALLBACK = '我的由来'

/** recent 单条：优先用 resultMeta 与结果页一致的「双项」文案 */
function summaryFromRecentRecord(rec, testType) {
  if (!rec) return ''
  const meta = rec.resultMeta
  if (meta && typeof meta === 'object') {
    const t = getTypeOnly(meta, testType)
    if (t) return t
  }
  return String(rec.resultText || '').trim()
}

Page({
  data: {
    hasLogin: false,
    userInfo: null,
    balance: 0,
    testCount: 0,
    hasResults: false,
    mbtiType: '',
    sbtiType: '',
    discType: '',
    pdpType: '',
    aiType: '',
    /** 面相记录中的盖洛普前三摘要（/api/test/recent 或本地 aiResult） */
    gallupPreview: '',
    mbtiTime: '',
    sbtiTime: '',
    discTime: '',
    pdpTime: '',
    aiTime: '',
    reviewMode: false,
    /** 超管「小程序提审模式」：与 reviewMode 一样隐藏推广/部分营销入口 */
    mpAuditMode: false,
    /** 仅 miniprogramAuditMode：隐藏「了解自己」深度套餐入口（虚拟商品合规） */
    showDeepPricingEntry: true,
    /** 审核/提审（与神仙 AI Tab 同源）：隐藏「匹配工作」入口 */
    showMatchJobEntry: true,
    /** 快捷入口网格列类名 quick-grid--2～4，按可见按钮数均分平铺（每行最多 4 个） */
    quickGridClass: 'quick-grid--4',
    /** 最近记录的数据库 ID，用于跳转时传参 */
    mbtiResultId: null,
    sbtiResultId: null,
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
    // 企业功能权限：默认全开（个人版 / 未配置时）
    permFace: true,
    permMbti: true,
    permSbti: true,
    permPdp: true,
    permDisc: true,
    permDistribution: true,
    /** 最新测试横滑区：有问卷/面相权限即显示；无记录时卡片灰阶占位，不隐藏 */
    showLatestTestRow: false,
    /** 用户卡片下性格标签：在「当前权限下无任何问卷结果」时显示灰色提示 */
    showEmptyPersonalityTags: true,
    profileRecoShow: false,
    profileRecoSectionLabel: '',
    profileRecoArticle: null,
    /** 与神仙 AI 精选推荐同源：配置了 AppID 时优先跳转目标小程序 */
    profileRecoJump: null,
    /** 拉取自 /api/distribution/my-invite-code，与推广中心一致 */
    myInviteCode: '',
    myInviteCodeLoadState: 'idle',
    myInviteCodeErrMsg: '',
    myInviteCodeHint: '',
    showInviteCodeDialog: false
  },

  _computeShowLatestTestRow(d) {
    const hideFace = !!(d.reviewMode || d.mpAuditMode)
    if (hideFace) {
      return !!(d.permMbti || d.permSbti || d.permPdp || d.permDisc)
    }
    return !!(d.permMbti || d.permSbti || d.permPdp || d.permDisc || d.permFace)
  },

  _computeShowEmptyPersonalityTags(d) {
    return !(
      (d.mbtiType && d.permMbti) ||
      (d.sbtiType && d.permSbti) ||
      (d.discType && d.permDisc) ||
      (d.pdpType && d.permPdp)
    )
  },

  /** 性格测试 + 我的订单 + 可选「了解自己」「匹配工作」，按可见数 2～4 列均分 */
  _computeQuickGridCols(d) {
    let n = 2
    if (d.showDeepPricingEntry) n++
    if (d.showMatchJobEntry) n++
    return Math.min(Math.max(n, 1), 4)
  },

  onLoad() {
    // 仅在 onLoad 执行一次，onShow 里的 runLoginThenLoad 会导致重复请求
    this.runLoginThenLoad()
  },
  _syncPerms(overrides = {}) {
    const gd = app.globalData || {}
    const p = gd.enterprisePermissions
    const next = {
      permFace: !p || p.face !== false,
      permMbti: !p || p.mbti !== false,
      permSbti: !p || p.sbti !== false,
      permPdp:  !p || p.pdp  !== false,
      permDisc: !p || p.disc !== false,
      permDistribution: !p || p.distribution !== false,
      showDeepPricingEntry: !gd.miniprogramAuditMode,
      showMatchJobEntry: !isAuditHideAiMode(gd),
      ...overrides
    }
    const d = { ...this.data, ...next }
    const qCols = this._computeQuickGridCols(d)
    this.setData({
      ...next,
      quickGridClass: `quick-grid--${qCols}`,
      showLatestTestRow: this._computeShowLatestTestRow(d),
      showEmptyPersonalityTags: this._computeShowEmptyPersonalityTags(d)
    })
  },

  onShow() {
    const gd = app.globalData
    this._syncPerms({
      reviewMode: !!(gd.reviewMode || gd.maintenanceMode),
      mpAuditMode: isAuditHideAiMode(gd)
    })
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
    const stored = wx.getStorageSync(storageKey)
    if (stored && typeof stored === 'object' && stored[uid]) return stored[uid]
    const suffix = this._randomWechatUserSuffix(4)
    const next = { ...(stored && typeof stored === 'object' ? stored : {}), [uid]: suffix }
    wx.setStorageSync(storageKey, next)
    return suffix
  },

  loadData() {
    const gd = app.globalData
    this._syncPerms({
      reviewMode: !!(gd.reviewMode || gd.maintenanceMode),
      mpAuditMode: isAuditHideAiMode(gd)
    })

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
      if (token && this.data.permDistribution) {
        this.setData({ myInviteCodeLoadState: 'loading', myInviteCodeErrMsg: '' })
      }
      this._loadRecentFromAPI()
      this._loadPromoStats()
      this._loadMyInviteCode()
      this._loadProfileRecoTeaser()
    } else {
      this.setData({
        profileRecoShow: false,
        profileRecoArticle: null,
        profileRecoSectionLabel: '',
        profileRecoJump: null,
        myInviteCode: '',
        myInviteCodeLoadState: 'idle',
        myInviteCodeErrMsg: '',
        myInviteCodeHint: '',
        showInviteCodeDialog: false
      })
    }
  },

  openInviteCodeFill() {
    app.ensureLogin().then((ok) => {
      if (!ok) {
        wx.showToast({ title: '请先登录', icon: 'none' })
        return
      }
      inviteCodeGate.openInviteCodeDialog(this)
    })
  },

  onInviteCodeSkip() {
    inviteCodeGate.finishInviteCodeGate(this, true)
  },

  onInviteCodeSuccess() {
    inviteCodeGate.finishInviteCodeGate(this, true)
  },

  _loadMyInviteCode() {
    const token = app.globalData.token || wx.getStorageSync('token')
    if (!token || !this.data.permDistribution) {
      this.setData({
        myInviteCode: '',
        myInviteCodeLoadState: 'idle',
        myInviteCodeErrMsg: '',
        myInviteCodeHint: ''
      })
      return
    }
    this.setData({
      myInviteCodeLoadState: 'loading',
      myInviteCodeErrMsg: ''
    })
    request({
      url: '/api/distribution/my-invite-code',
      method: 'GET',
      success: (res) => {
        const payload = res && res.data
        const httpOk = res.statusCode >= 200 && res.statusCode < 300
        const bizOk = payload && Number(payload.code) === 200
        if (!httpOk || !bizOk) {
          const msg =
            (payload && payload.message) ||
            (typeof res.statusCode === 'number' ? `请求失败 (${res.statusCode})` : '加载失败')
          this.setData({
            myInviteCode: '',
            myInviteCodeLoadState: 'error',
            myInviteCodeErrMsg: msg,
            myInviteCodeHint: ''
          })
          return
        }
        const data = payload.data || {}
        const raw = data.code != null ? String(data.code).trim() : ''
        const code = raw ? raw.toUpperCase() : ''
        const hint = (data.hint && String(data.hint).trim()) || ''
        if (code) {
          this.setData({
            myInviteCode: code,
            myInviteCodeLoadState: 'ready',
            myInviteCodeErrMsg: '',
            myInviteCodeHint: ''
          })
        } else {
          this.setData({
            myInviteCode: '',
            myInviteCodeLoadState: 'nodata',
            myInviteCodeErrMsg: '',
            myInviteCodeHint: hint || '暂无可用邀请码'
          })
        }
      },
      fail: () =>
        this.setData({
          myInviteCode: '',
          myInviteCodeLoadState: 'error',
          myInviteCodeErrMsg: '网络异常',
          myInviteCodeHint: ''
        })
    })
  },

  onInviteStripTap() {
    if (this.data.myInviteCodeLoadState === 'error') {
      this._loadMyInviteCode()
      return
    }
    this.goToPromo()
  },

  copyMyInviteCode() {
    const code = (this.data.myInviteCode || '').trim()
    if (!code) return
    wx.setClipboardData({
      data: code,
      success: () => wx.showToast({ title: '已复制邀请码', icon: 'success' })
    })
  },

  _parseRecoJumpFromDisplay(d) {
    if (!d || typeof d !== 'object') return null
    const appId = ((d.recoJumpMiniAppId || '') + '').trim()
    if (!appId || !/^wx[0-9a-f]{16}$/i.test(appId)) return null
    const envRaw = ((d.recoJumpMiniEnvVersion || 'release') + '').trim().toLowerCase()
    const env = envRaw === 'trial' || envRaw === 'develop' ? envRaw : 'release'
    let path = ((d.recoJumpMiniPath || 'pages/index/index') + '').trim().replace(/^\//, '')
    if (!path) path = 'pages/index/index'
    return { appId: appId.toLowerCase(), path, envVersion: env }
  },

  _applyProfileTeaserPayload(d) {
    const art = d.article && d.article.url ? d.article : null
    const show = !!(d.enabled && art)
    this.setData({
      profileRecoShow: show,
      profileRecoSectionLabel: (d.sectionLabel && String(d.sectionLabel).trim()) || PROFILE_RECO_LABEL_FALLBACK,
      profileRecoArticle: show ? art : null,
      profileRecoJump: show ? this._parseRecoJumpFromDisplay(d) : null
    })
  },

  _loadProfileRecoTeaser() {
    const t = Date.now()
    const finishFail = () =>
      this.setData({
        profileRecoShow: false,
        profileRecoArticle: null,
        profileRecoSectionLabel: '',
        profileRecoJump: null
      })

    const tryLegacyPath = () => {
      request({
        url: `/api/ai/articles/profile-teaser?_t=${Date.now()}`,
        method: 'GET',
        needAuth: false,
        success: (res) => {
          const payload = res && res.data
          if (!payload || payload.code !== 200 || !payload.data) {
            finishFail()
            return
          }
          this._applyProfileTeaserPayload(payload.data)
        },
        fail: finishFail
      })
    }

    // 优先走 recommended?usage=profile（与已放行路径一致）；部署新 API 后与 profile-teaser 结构相同
    request({
      url: `/api/ai/articles/recommended?usage=profile&_t=${t}`,
      method: 'GET',
      needAuth: false,
      success: (res) => {
        const payload = res && res.data
        if (!payload || payload.code !== 200 || !payload.data) {
          tryLegacyPath()
          return
        }
        const d = payload.data
        if (Object.prototype.hasOwnProperty.call(d, 'article')) {
          this._applyProfileTeaserPayload(d)
          return
        }
        if (Array.isArray(d.list) && d.list.length) {
          const disp = d.display || {}
          const first = d.list[0]
          if (disp.enabled !== false && first && first.url) {
            this.setData({
              profileRecoShow: true,
              profileRecoSectionLabel: (disp.profileSectionLabel && String(disp.profileSectionLabel).trim()) || PROFILE_RECO_LABEL_FALLBACK,
              profileRecoArticle: first,
              profileRecoJump: this._parseRecoJumpFromDisplay(disp)
            })
            return
          }
        }
        tryLegacyPath()
      },
      fail: tryLegacyPath
    })
  },

  onTapProfileReco(e) {
    const ds = (e && e.currentTarget && e.currentTarget.dataset) || {}
    const id = ds.id
    const url = ds.url
    const title = ds.title
    const sourceId = ds.sourceId
    try {
      require('../../utils/analytics').track('tap_ai_article', {
        articleId: id,
        url,
        title,
        from: 'profile_teaser'
      })
    } catch (err) {}
    if (id) {
      request({
        url: `/api/ai/articles/${id}/click`,
        method: 'POST',
        needAuth: false,
        success() {},
        fail() {}
      })
    }
    const jump = this.data.profileRecoJump
    if (jump && jump.appId) {
      const path = buildRecoMiniProgramPath(jump.path, {
        id,
        title,
        sourceId,
        fromTag: 'mbti_profile_reco'
      })
      const env = jump.envVersion === 'trial' || jump.envVersion === 'develop' ? jump.envVersion : 'release'
      wx.navigateToMiniProgram({
        appId: jump.appId,
        path,
        envVersion: env,
        fail: (err) => {
          if (url) {
            const enc = encodeURIComponent(url)
            wx.navigateTo({
              url: `/pages/webview/index?url=${enc}`,
              fail: () => {
                wx.setClipboardData({
                  data: url,
                  success: () => wx.showToast({ title: '已复制链接', icon: 'none' })
                })
              }
            })
          } else {
            wx.showToast({ title: (err && err.errMsg) || '无法打开目标小程序', icon: 'none' })
          }
        }
      })
      return
    }
    if (!url) return
    const enc = encodeURIComponent(url)
    wx.navigateTo({
      url: `/pages/webview/index?url=${enc}`,
      fail: () => {
        wx.setClipboardData({
          data: url,
          success: () => wx.showToast({ title: '已复制链接', icon: 'none' })
        })
      }
    })
  },

  /** 从 /api/test/recent 拉取各类型最新记录 */
  _loadRecentFromAPI() {
    // 固定 scope=all：与 appScope 无关。个人/企业 scope 会按 enterpriseId 过滤，若提交时写过
    // enterpriseId 而此处仍用 personal，会导致库里有记录却显示「未测评」。
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

        const discType = summaryFromRecentRecord(r.disc, 'disc')
        const sbtiType = summaryFromRecentRecord(r.sbti, 'sbti')

        const gallupPreview = (r.ai && r.ai.gallupPreview) ? String(r.ai.gallupPreview) : ''
        const patch = {
          testCount:    totalCount,
          hasResults:   !!(r.mbti || r.sbti || r.disc || r.pdp || r.ai),
          mbtiType:     r.mbti ? r.mbti.resultText : '',
          sbtiType,
          discType,
          pdpType:      summaryFromRecentRecord(r.pdp, 'pdp'),
          aiType:       r.ai   ? r.ai.resultText   : '',
          gallupPreview,
          mbtiTime:     r.mbti ? r.mbti.testTime : '',
          sbtiTime:     r.sbti ? r.sbti.testTime : '',
          discTime:     r.disc ? r.disc.testTime : '',
          pdpTime:      r.pdp  ? r.pdp.testTime  : '',
          aiTime:       r.ai   ? r.ai.testTime   : '',
          mbtiResultId: r.mbti ? r.mbti.id : null,
          sbtiResultId: r.sbti ? r.sbti.id : null,
          discResultId: r.disc ? r.disc.id : null,
          pdpResultId:  r.pdp  ? r.pdp.id  : null,
          aiResultId:   r.ai   ? r.ai.id   : null,
        }
        const d = { ...this.data, ...patch }
        this.setData({
          ...patch,
          showLatestTestRow: this._computeShowLatestTestRow(d),
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
    const mbtiResult = wx.getStorageSync('mbtiResult')
    const sbtiResult = wx.getStorageSync('sbtiResult')
    const discResult = wx.getStorageSync('discResult')
    const pdpResult  = wx.getStorageSync('pdpResult')
    const aiResult   = wx.getStorageSync('aiResult')

    let gallupPreview = ''
    if (aiResult && Array.isArray(aiResult.gallupTop3) && aiResult.gallupTop3.length) {
      gallupPreview = aiResult.gallupTop3.slice(0, 3).map(String).join('、')
    }

    let testCount = 0
    if (mbtiResult) testCount++
    if (sbtiResult) testCount++
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
      sbtiType: sbtiResult ? getTypeOnly(sbtiResult, 'sbti') : '',
      discType: discResult ? getTypeOnly(discResult, 'disc') : '',
      pdpType:  pdpResult  ? getTypeOnly(pdpResult,  'pdp')  : '',
      aiType:   aiResult   ? (aiResult.mbti || aiResult.mbtiType || aiResult.type || '') : '',
      gallupPreview,
      mbtiTime: _fmt(mbtiResult && (mbtiResult.createdAt || mbtiResult.timestamp || mbtiResult.testTime)),
      sbtiTime: _fmt(sbtiResult && (sbtiResult.createdAt || sbtiResult.timestamp || sbtiResult.completedAt || sbtiResult.testTime)),
      discTime: _fmt(discResult && (discResult.createdAt || discResult.timestamp || discResult.testTime)),
      pdpTime:  _fmt(pdpResult  && (pdpResult.createdAt  || pdpResult.timestamp  || pdpResult.testTime)),
      aiTime:   _fmt(aiResult   && (aiResult.createdAt   || aiResult.timestamp   || aiResult.testTime)),
      mbtiResultId: null,
      sbtiResultId: null,
      discResultId: null,
      pdpResultId:  null,
      aiResultId:   null,
    }
    const d = { ...this.data, ...patch }
    this.setData({
      ...patch,
      showLatestTestRow: this._computeShowLatestTestRow(d),
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
        wx.showToast({ title: '登录失败，请检查网络或稍后重试', icon: 'none' })
        this.setData({ loginFailed: true })
      }
    }).catch(() => {
      this.setData({ loginLoading: false, loginFailed: true })
      wx.showToast({ title: '登录失败', icon: 'none' })
    })
  },

  goToIndex() { wx.switchTab({ url: '/pages/index/index' }) },
  goToHistory() {
    try { require('../../utils/analytics').track('tap_test_history', {}) } catch (e) {}
    wx.navigateTo({ url: '/pages/history/index' })
  },
  goToUserProfile() { wx.navigateTo({ url: '/pages/user-profile/index' }) },
  /** 合并后的用户卡片点击：按登录态分发 */
  onUserCardTap() {
    if (this.data.hasLogin) {
      this.goToUserProfile()
    } else {
      this.doLogin()
    }
  },
  /** 深度服务统一入口（页内 Tab：个人 / 团队与企业） */
  goToDeepService() {
    if (app.globalData && app.globalData.miniprogramAuditMode) {
      wx.showToast({ title: '版本审核中暂不可用', icon: 'none' })
      return
    }
    try { require('../../utils/analytics').track('tap_deep_service', {}) } catch (e) {}
    wx.navigateTo({ url: '/pages/purchase/index' })
  },
  goToOrders() {
    if (!this.data.hasLogin) {
      wx.showToast({ title: '请先登录', icon: 'none' })
      return
    }
    try { require('../../utils/analytics').track('tap_my_orders', {}) } catch (e) {}
    wx.navigateTo({ url: '/pages/order/index' })
  },
  goToPurchase() {
    if (app.globalData && app.globalData.miniprogramAuditMode) {
      wx.showToast({ title: '版本审核中暂不可用', icon: 'none' })
      return
    }
    wx.navigateTo({ url: '/pages/purchase/index' })
  },
  goToPurchasePersonal() {
    if (app.globalData && app.globalData.miniprogramAuditMode) {
      wx.showToast({ title: '版本审核中暂不可用', icon: 'none' })
      return
    }
    wx.navigateTo({ url: '/pages/purchase/index?tab=personal' })
  },
  goToPurchaseEnterprise() {
    if (app.globalData && app.globalData.miniprogramAuditMode) {
      wx.showToast({ title: '版本审核中暂不可用', icon: 'none' })
      return
    }
    wx.navigateTo({ url: '/pages/purchase/index?tab=enterprise' })
  },
  goToEnterprise() { wx.navigateTo({ url: '/pages/enterprise/index' }) },
  goToPromoWithdrawals() {
    try { require('../../utils/analytics').track('tap_promo_withdrawals', { from: 'profile' }) } catch (e) {}
    wx.navigateTo({ url: '/pages/promo/withdrawals' })
  },
  goToPromo() {
    try { require('../../utils/analytics').track('tap_promo_center', { from: 'profile' }) } catch (e) {}
    wx.navigateTo({ url: '/pages/promo/index' })
  },
  goToMyResume() {
    try { require('../../utils/analytics').track('tap_my_resume', {}) } catch (e) {}
    wx.navigateTo({ url: '/pages/enterprise/resume-history' })
  },
  /** 匹配工作：新入口，跳转匹配工作中间页 */
  goToMatchJob() {
    const gd = app.globalData || {}
    if (isAuditHideAiMode(gd)) {
      wx.showToast({ title: '功能升级中', icon: 'none' })
      return
    }
    try { require('../../utils/analytics').track('tap_match_job', {}) } catch (e) {}
    wx.navigateTo({ url: '/pages/match-job/index' })
  },
  goToSettings() {
    wx.showToast({ title: '开发中', icon: 'none' })
  },
  shareApp() {
    // 触发分享
  },
  viewMBTI() {
    const id = this.data.mbtiResultId
    if (id) wx.navigateTo({ url: `/pages/result/mbti?id=${id}&type=mbti` })
    else wx.navigateTo({ url: '/pages/test/mbti' })
  },
  viewSBTI() {
    const id = this.data.sbtiResultId
    if (id) wx.navigateTo({ url: `/pages/result/sbti?id=${id}&type=sbti` })
    else wx.navigateTo({ url: '/pages/test/sbti' })
  },
  viewDISC() {
    const id = this.data.discResultId
    if (id) wx.navigateTo({ url: `/pages/result/disc?id=${id}&type=disc` })
    else wx.navigateTo({ url: '/pages/test/disc' })
  },
  viewPDP() {
    const id = this.data.pdpResultId
    if (id) wx.navigateTo({ url: `/pages/result/pdp?id=${id}&type=pdp` })
    else wx.navigateTo({ url: '/pages/test/pdp' })
  },
  /** 盖洛普随面相报告：有记录看报告，否则去拍摄 */
  viewGallup() {
    const id = this.data.aiResultId
    if (id) wx.navigateTo({ url: `/pages/index/result?id=${id}&type=ai` })
    else wx.switchTab({ url: '/pages/index/camera' })
  },
  viewAI() {
    const id = this.data.aiResultId
    if (id) wx.navigateTo({ url: `/pages/index/result?id=${id}&type=ai` })
    else wx.switchTab({ url: '/pages/index/camera' })
  },
  /** 详细性格测试：MBTI / PDP / DISC 选择页 */
  goToTestSelect() {
    try { require('../../utils/analytics').track('tap_test_select_from_profile', {}) } catch (e) {}
    wx.navigateTo({ url: '/pages/test-select/index' })
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
    const gd = app.globalData || {}
    if (gd.miniprogramAuditMode || gd.reviewMode || gd.maintenanceMode) {
      return { title: '性格测试', path: '/pages/index/index' }
    }
    return {
      title: '神仙团队性格测试 - 发现你的MBTI类型',
      path: getSharePathByScope('/pages/index/index')
    }
  },

  onShareTimeline() {
    const { buildShareQuery } = require('../../utils/share')
    const gd = app.globalData || {}
    if (gd.miniprogramAuditMode || gd.reviewMode || gd.maintenanceMode) {
      return { title: '性格测试', query: '' }
    }
    return {
      title: '神仙团队性格测试 - 发现你的MBTI类型',
      query: buildShareQuery()
    }
  }
})
