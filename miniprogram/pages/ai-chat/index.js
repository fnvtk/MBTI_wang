// 神仙 AI · 对话页
const { request } = require('../../utils/request.js')
const analytics = require('../../utils/analytics.js')
const { buildRecoMiniProgramPath } = require('../../utils/recoMiniProgram.js')
const aiChatIntent = require('../../utils/aiChatIntent.js')

/** 功能类推荐卡「阅读数」按发布日起算时的锚点日期 */
const FEATURE_RECO_PUBLISHED_AT = '2026-01-01'

/** 底部「深度画像报告」条：用户发言超过该条数才可能展示 */
const REPORT_CTA_MIN_USER_TURNS = 10
/** 报告条仅展示一次（点过或离开带条页面后不再出现） */
const STORAGE_REPORT_CTA_DISMISSED = 'ai_chat_deep_report_cta_dismissed'

// 兜底封面（base64 SVG，紫色渐变 + sparkle）
const DEFAULT_COVER = 'data:image/svg+xml;utf8,' + encodeURIComponent(
  '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 440 220">' +
  '<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">' +
  '<stop offset="0%" stop-color="#a78bfa"/><stop offset="100%" stop-color="#7c3aed"/>' +
  '</linearGradient></defs>' +
  '<rect width="440" height="220" fill="url(%23g)"/>' +
  '<text x="50%" y="55%" font-size="42" fill="white" text-anchor="middle" font-family="PingFang SC" font-weight="700">MBTI</text>' +
  '<text x="50%" y="80%" font-size="20" fill="rgba(255,255,255,0.9)" text-anchor="middle">精选推荐阅读</text>' +
  '</svg>'
)

/** Fisher–Yates：生成 0..n-1 的随机排列，用于多篇文章轮播时均衡曝光 */
function shuffleIndexOrder(n) {
  const arr = []
  for (let i = 0; i < n; i++) arr.push(i)
  for (let i = n - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1))
    const t = arr[i]
    arr[i] = arr[j]
    arr[j] = t
  }
  return arr
}

Page({
  data: {
    articles: [],
    /** 与 articles 等长的下标排列，第 k 次内嵌推荐取 articles[order[k % n]] */
    articleRecoPickOrder: [],
    defaultCover: DEFAULT_COVER,
    /** 后台「神仙 AI 页展示」：为 true 时允许在第 2 轮及以后助手气泡内嵌精选（GET /api/ai/articles/recommended） */
    articlesDisplayEnabled: false,
    /** 后台配置的跳转小程序（有 appId 时优先 navigateToMiniProgram） */
    articlesRecoJump: null,
    articlesLoaded: false,
    mbtiType: '',
    nickname: '',
    quickQuestions: [],
    messages: [],
    conversationId: 0,
    draft: '',
    sending: false,
    scrollTarget: '',
    showReportCta: false,
    myReportStatus: '',
    /** 对话内精选推荐：与后台 ai_chat_articles 同源（缺省 3～5 轮量级） */
    inlineRecoMinUserTurns: 2,
    inlineRecoInterval: 3,
    inlineRecoRoll: 0.5,
    inlineRecoIconCount: 3,
    inlineRecoIconsPool: ['✨', '💬', '📌']
  },

  onLoad(options) {
    const { ensureRuntimeThenGate } = require('../../utils/miniprogramAuditGate.js')
    ensureRuntimeThenGate(() => {
      const cid = parseInt(options && options.cid, 10)
      if (cid > 0) {
        this.setData({ conversationId: cid })
        this.loadHistory(cid)
      }
      this.loadArticles()
      this.tryBindReferral(options)
      analytics.track('page_view', { pagePath: 'pages/ai-chat/index' })
      const app = getApp()
      const afterRuntime = () => {
        const rt = (app.globalData && app.globalData.aiChatRuntime) || {}
        this.applyInlineRecoDisplay(rt)
        this.loadQuickQuestions()
        this.loadMyReportStatus()
      }
      const pullRuntime = () => {
        if (app && typeof app.getRuntimeConfig === 'function') {
          app.getRuntimeConfig().then(afterRuntime).catch(afterRuntime)
        } else {
          afterRuntime()
        }
      }
      // 先进 ensureLogin（含从 storage 回补 token），再拉 runtime，避免首进对话页发消息时仍无 Bearer
      if (app && typeof app.ensureLogin === 'function') {
        app.ensureLogin().finally(pullRuntime)
      } else {
        pullRuntime()
      }
    })
  },

  /** 分享链接里带 inviterId 时，绑定分销关系（复用现有分销系统） */
  tryBindReferral(options) {
    const inviterId = parseInt(options && options.inviterId, 10)
    if (!inviterId || inviterId <= 0) return
    request({
      url: '/api/distribution/bind',
      method: 'POST',
      data: { inviterId },
      success() {}, fail() {}
    })
  },

  /** 查询我最近的 AI 报告状态（决定是否展示 CTA） */
  loadMyReportStatus() {
    const token =
      (getApp().globalData && getApp().globalData.token) || wx.getStorageSync('token') || ''
    if (!token) return
    const applyOk = (res) => {
      const body = (res && res.data) || {}
      const d = body.data || {}
      this.setData({ myReportStatus: d.status || '' }, () => this._refreshReportCta())
    }
    const tryUrl = (url, next) => {
      request({
        url,
        method: 'GET',
        allow401: false,
        success: (res) => {
          const body = (res && res.data) || {}
          if (res.statusCode === 200 && body.code === 200) {
            applyOk(res)
            return
          }
          if (typeof next === 'function') next()
        },
        fail: () => {
          if (typeof next === 'function') next()
        }
      })
    }
    tryUrl('/api/ai/my-report/latest', () => tryUrl('/api/ai/report/my-latest'))
  },

  onShow() {
    const { redirectIfMiniprogramAudit } = require('../../utils/miniprogramAuditGate.js')
    if (redirectIfMiniprogramAudit()) return
    const tb = typeof this.getTabBar === 'function' ? this.getTabBar() : null
    if (tb && typeof tb.updateSelected === 'function') tb.updateSelected()
    const app = getApp()
    try {
      const st = wx.getStorageSync('token')
      if (st && app && app.globalData && !app.globalData.token) {
        app.globalData.token = st
      }
    } catch (e) {}
    this.loadQuickQuestions()
    this.loadMyReportStatus()
    this._refreshReportCta()
  },

  /** 离开对话页时若报告条正在展示，记为已曝光，避免反复出现 */
  onHide() {
    if (this.data.showReportCta) {
      this._dismissReportCtaPermanently()
    }
  },

  onInput(e) {
    this.setData({ draft: e.detail.value })
  },

  // ---------- 数据加载 ----------

  loadArticles() {
    const t = Date.now()
    request({
      url: `/api/ai/articles/recommended?_t=${t}`,
      method: 'GET',
      needAuth: false,
      success: (res) => {
        const body = (res && res.data) || {}
        if (body.code !== 200) return
        const rawList = (body.data && body.data.list) || []
        const disp = (body.data && body.data.display) || {}
        const enabled = !!disp.enabled
        const maxShow = Math.max(1, Math.min(3, parseInt(disp.maxShow, 10) || 1))
        const list = rawList.slice(0, maxShow)
        const appId = ((disp.recoJumpMiniAppId || '') + '').trim()
        const recoJump =
          appId && /^wx[0-9a-f]{16}$/i.test(appId)
            ? {
                appId: appId.toLowerCase(),
                path: ((disp.recoJumpMiniPath || 'pages/index/index') + '').trim().replace(/^\//, '') || 'pages/index/index',
                envVersion: ((disp.recoJumpMiniEnvVersion || 'release') + '').trim().toLowerCase() || 'release'
              }
            : null
        const order = list.length ? shuffleIndexOrder(list.length) : []
        this.applyInlineRecoDisplay(disp)
        this.setData({
          articles: list,
          articleRecoPickOrder: order,
          articlesDisplayEnabled: enabled,
          articlesRecoJump: recoJump,
          articlesLoaded: true
        })
      }
    })
  },

  /** 合并后台下发的内嵌推荐参数（articles/display 或 runtime.aiChat） */
  applyInlineRecoDisplay(disp) {
    if (!disp || typeof disp !== 'object') return
    const minTurns = Math.max(1, Math.min(10, parseInt(disp.inlineRecoMinUserTurns, 10) || 2))
    const interval = Math.max(2, Math.min(10, parseInt(disp.inlineRecoInterval, 10) || 3))
    let roll = parseFloat(disp.inlineRecoRoll)
    if (Number.isNaN(roll)) roll = 0.5
    roll = Math.max(0.05, Math.min(1, roll))
    const iconCount = Math.max(1, Math.min(3, parseInt(disp.inlineRecoIconCount, 10) || 3))
    let pool = ['✨', '💬', '📌']
    if (Array.isArray(disp.inlineRecoIcons) && disp.inlineRecoIcons.length) {
      pool = disp.inlineRecoIcons.map((x) => String(x || '').trim()).filter(Boolean)
      if (!pool.length) pool = ['✨', '💬', '📌']
    }
    this.setData({
      inlineRecoMinUserTurns: minTurns,
      inlineRecoInterval: interval,
      inlineRecoRoll: roll,
      inlineRecoIconCount: iconCount,
      inlineRecoIconsPool: pool
    })
  },

  /** 每条推荐卡角标 emoji，总数不超过 3；可按意图前置一个相关符号 */
  _pickIconsForReco(intent) {
    const max = Math.max(
      1,
      Math.min(3, parseInt(this.data.inlineRecoIconCount, 10) || 3)
    )
    const base =
      (this.data.inlineRecoIconsPool && this.data.inlineRecoIconsPool.length
        ? this.data.inlineRecoIconsPool.map(String)
        : ['✨', '💬', '📌'])
    const lead =
      intent === 'job' ? '💼' : intent === 'test' ? '📝' : intent === 'self' ? '💎' : ''
    const out = []
    if (lead && base.indexOf(lead) < 0) out.push(lead)
    for (let i = 0; i < base.length && out.length < max; i++) {
      if (out.indexOf(base[i]) < 0) out.push(base[i])
    }
    const pad = ['✨', '💬', '📌', '💼', '📝', '💎']
    for (let i = 0; i < pad.length && out.length < max; i++) {
      if (out.indexOf(pad[i]) < 0) out.push(pad[i])
    }
    return out.slice(0, max)
  },

  /**
   * 内嵌精选：后台 1～3 条在拉取时打乱为 articleRecoPickOrder，之后按对话轮次循环取，
   * 保证多条时曝光均衡、又不会连续两条总盯同一篇（比每轮纯 Math.random 更稳）。
   * 返回 { inlineArticle, articleRecoPickOrder? }，后者仅在补全顺序时带上，供与 messages 同次 setData。
   */
  _buildInlineRecoPayload(userTurns, showInlineReco) {
    const out = { inlineArticle: null }
    if (!showInlineReco) return out
    const list = this.data.articles || []
    if (!list.length) return out
    let order = this.data.articleRecoPickOrder || []
    if (!Array.isArray(order) || order.length !== list.length) {
      order = shuffleIndexOrder(list.length)
      out.articleRecoPickOrder = order
    }
    const n = list.length
    const recoRound = Math.max(0, (userTurns || 0) - 2)
    const listIdx = order[recoRound % n]
    const a = list[listIdx]
    if (!a || !a.id) return out
    out.inlineArticle = {
      id: a.id,
      title: a.title || '',
      url: a.url || '',
      cover: a.cover || '',
      tag: a.tag || '',
      publishedAt: a.publishedAt || '',
      sourceId: a.sourceId || '',
      readCountLabel: aiChatIntent.formatInlineReadLabel(a.publishedAt || ''),
      icons: this._pickIconsForReco('')
    }
    return out
  },

  /** 与「我的」同源的功能快捷推荐（仅展示卡片，点击再跳转） */
  _buildFeatureInlineReco(intent, hasMbti) {
    const readCountLabel = aiChatIntent.formatInlineReadLabel(FEATURE_RECO_PUBLISHED_AT)
    if (intent === 'job') {
      return {
        kind: 'feature',
        id: 'feat_match_job',
        title: '匹配工作：按性格看岗位与发展方向',
        url: '',
        cover: '',
        tag: '匹配工作',
        publishedAt: '',
        sourceId: '',
        navPath: '/pages/match-job/index',
        readCountLabel,
        icons: this._pickIconsForReco('job')
      }
    }
    if (intent === 'test') {
      return {
        kind: 'feature',
        id: 'feat_test_select',
        title: 'MBTI 性格测评 · 进入测试列表',
        url: '',
        cover: '',
        tag: '性格测试',
        publishedAt: '',
        sourceId: '',
        navPath: '/pages/test-select/index',
        readCountLabel,
        icons: this._pickIconsForReco('test')
      }
    }
    if (intent === 'self') {
      const path = aiChatIntent.pickSelfServicePath(!!hasMbti)
      const toTest = path.indexOf('test-select') >= 0
      return {
        kind: 'feature',
        id: toTest ? 'feat_self_test' : 'feat_self_know',
        title: toTest ? '性格测试 · 更系统地认识自己' : '深度了解自己 · 专属服务入口',
        url: '',
        cover: '',
        tag: toTest ? '性格测试' : '了解自己',
        publishedAt: '',
        sourceId: '',
        navPath: path,
        readCountLabel,
        icons: this._pickIconsForReco('self')
      }
    }
    return null
  },

  /** 去测 MBTI → 详细性格测试列表 */
  onGoMbtiTest() {
    try {
      analytics.track('ai_chat_go_test_select', {})
    } catch (e) {}
    wx.navigateTo({
      url: '/pages/test-select/index',
      fail: () => {
        wx.showToast({ title: '暂时无法打开测试列表', icon: 'none' })
      }
    })
  },

  loadQuickQuestions() {
    const apply = (body) => {
      if (!body || body.code !== 200) return false
      const d = body.data || {}
      this.setData({
        mbtiType: d.mbtiType || '',
        nickname: d.nickname || '',
        quickQuestions: this.buildQuickQuestions(d.questions || [])
      })
      return true
    }
    const applyDirect = (d) => {
      if (!d || !Array.isArray(d.questions) || d.questions.length === 0) return false
      this.setData({
        mbtiType: d.mbtiType || '',
        nickname: d.nickname || '',
        quickQuestions: this.buildQuickQuestions(d.questions)
      })
      return true
    }
    const fallbackLocal = () => {
      this.setData({ quickQuestions: this.buildQuickQuestions([]) })
    }
    const tryLegacy = () => {
      request({
        url: '/api/ai/quick-questions',
        method: 'GET',
        needAuth: false,
        optionalAuth: true,
        allow401: false,
        success: (res2) => {
          if (!apply((res2 && res2.data) || {})) fallbackLocal()
        },
        fail: fallbackLocal
      })
    }
    const tryConfigUrl = (path, next) => {
      request({
        url: path,
        method: 'GET',
        needAuth: false,
        optionalAuth: true,
        allow401: false,
        success: (res) => {
          const body = (res && res.data) || {}
          if (apply(body)) return
          next()
        },
        fail: next
      })
    }
    // 线上网关常只放行 /api/config/runtime，快捷问句已内嵌其中
    try {
      const run = getApp().globalData && getApp().globalData.runtimeAiQuickQuestions
      if (applyDirect(run)) return
    } catch (e) {}
    // 先试短路径 config/quick-questions，再试 ai-quick-questions，最后 /api/ai/quick-questions
    tryConfigUrl('/api/config/quick-questions', () => {
      tryConfigUrl('/api/config/ai-quick-questions', tryLegacy)
    })
  },

  buildQuickQuestions(serverList) {
    const preferred = [
      '帮我了解并分析一下我现在的状态',
      '我适合什么样的伴侣关系？',
      '我应该找什么样的工作？',
      '我下一步的职业发展方向是什么？',
      '我的性格优势该怎么变现？',
      '我现在最该改掉的一个习惯是什么？',
      '我更适合独立做事还是团队协作？',
      '给我一句今天就能做的小行动建议'
    ]
    const all = []
    ;(Array.isArray(serverList) ? serverList : []).forEach((q) => {
      if (typeof q === 'string' && q.trim()) all.push(q.trim())
    })
    preferred.forEach((q) => all.push(q))
    const uniq = []
    const seen = {}
    all.forEach((q) => {
      if (!seen[q]) {
        seen[q] = true
        uniq.push(q)
      }
    })
    return uniq.slice(0, 8)
  },

  loadHistory(cid) {
    const token =
      (getApp().globalData && getApp().globalData.token) || wx.getStorageSync('token') || ''
    if (!token) return
    request({
      url: `/api/ai/conversations/${cid}/messages`,
      method: 'GET',
      allow401: false,
      success: (res) => {
        const body = (res && res.data) || {}
        if (body.code !== 200) return
        const d = body.data || {}
        const msgs = (d.messages || []).filter(m => m.role !== 'system')
        this.setData({ messages: msgs }, () => {
          this.scrollToBottom()
          this._refreshReportCta()
        })
      }
    })
  },

  // ---------- 交互 ----------

  onTapQuick(e) {
    const q = (e.currentTarget.dataset.q || '').trim()
    if (!q || this.data.sending) return
    analytics.track('ai_quick_question_click', { question: q })
    this.setData({ draft: q }, () => this.onSend())
  },

  _openArticleWebview(url) {
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

  onTapArticle(e) {
    const ds = e.currentTarget.dataset || {}
    const navPath = String(ds.navpath || ds.navPath || '').trim()
    if (navPath) {
      try {
        analytics.track('ai_chat_feature_reco_tap', { path: navPath })
      } catch (err) {}
      const urlPath = navPath.startsWith('/') ? navPath : '/' + navPath
      wx.navigateTo({
        url: urlPath,
        fail: () => wx.showToast({ title: '暂时无法打开该功能', icon: 'none' })
      })
      return
    }
    const id = ds.id
    const url = ds.url
    const title = ds.title
    const sourceId = ds.sourceId
    analytics.track('tap_ai_article', { articleId: id, url, title, sourceId })
    // 轻量回传点击（异步）；功能卡 id 非数字，跳过
    if (id && String(id).indexOf('feat_') !== 0) {
      request({
        url: `/api/ai/articles/${id}/click`,
        method: 'POST',
        needAuth: false,
        success() {}, fail() {}
      })
    }
    const jump = this.data.articlesRecoJump
    if (jump && jump.appId) {
      const path = buildRecoMiniProgramPath(jump.path, {
        id,
        title,
        sourceId,
        fromTag: 'mbti_ai_reco'
      })
      const env = jump.envVersion === 'trial' || jump.envVersion === 'develop' ? jump.envVersion : 'release'
      wx.navigateToMiniProgram({
        appId: jump.appId,
        path,
        envVersion: env,
        fail: (err) => {
          if (url) {
            this._openArticleWebview(url)
          } else {
            wx.showToast({
              title: (err && err.errMsg) || '无法打开目标小程序',
              icon: 'none'
            })
          }
        }
      })
      return
    }
    if (!url) return
    this._openArticleWebview(url)
  },

  /**
   * @param {{ bubbleText: string, apiMessage: string, intentText: string, rollbackDraft: string, resumeFileUrl?: string, resumeFileName?: string }} ctx
   */
  _beginChatOutgoing(ctx) {
    const bubbleText = (ctx.bubbleText || '').trim()
    const apiMessage = ctx.apiMessage != null ? String(ctx.apiMessage) : ''
    const intentText = (ctx.intentText || bubbleText || '').trim()
    const rollbackDraft = ctx.rollbackDraft != null ? String(ctx.rollbackDraft) : ''
    const resumeFileUrl = (ctx.resumeFileUrl || '').trim()
    const resumeFileName = (ctx.resumeFileName || '').trim()

    if (this.data.sending) return
    if (!bubbleText && !resumeFileUrl) return

    const prevMessages = this.data.messages.slice()

    const now = Date.now()
    const userMsg = {
      id: 'u_' + now,
      role: 'user',
      content: bubbleText || '📎 已上传简历，请神仙 AI 分析',
      createdAt: Math.floor(now / 1000)
    }
    const messagesWithUser = this.data.messages.concat([userMsg])
    const clearDraft = !resumeFileUrl
    this.setData(
      {
        messages: messagesWithUser,
        draft: clearDraft ? '' : this.data.draft,
        sending: true
      },
      () => {
        this.setData({ scrollTarget: 'msg-typing' })
      }
    )

    try {
      analytics.track('ai_chat_send', {
        length: (apiMessage || bubbleText).length,
        resume: resumeFileUrl ? 1 : 0
      })
    } catch (e) {}

    const stripPending = (list) => (list || []).filter((m) => !m.isPending)

    const applyAssistantPayload = (d) => {
      clearSendWatchdog()
      const aiMsg = (d && d.message) || {}
      const base = stripPending(this.data.messages)
      const userTurns = base.filter((m) => m.role === 'user').length
      const assistantText = (aiMsg.content || '').trim()
      const isDegraded = !!aiMsg.isDegraded

      const minT = Math.max(1, Math.min(10, parseInt(this.data.inlineRecoMinUserTurns, 10) || 2))
      const recoN = Math.max(2, Math.min(10, parseInt(this.data.inlineRecoInterval, 10) || 3))
      let recoRoll = parseFloat(this.data.inlineRecoRoll)
      if (Number.isNaN(recoRoll)) recoRoll = 0.5
      recoRoll = Math.max(0.05, Math.min(1, recoRoll))
      const gateSlot =
        userTurns >= minT &&
        (userTurns - minT) % recoN === 0 &&
        Math.random() < recoRoll

      let inlineArticle = null
      let recoPackExtra = null

      if (gateSlot && !isDegraded) {
        const hasMbti = !!(this.data.mbtiType && String(this.data.mbtiType).trim())
        const featIntent = aiChatIntent.resolveChatFeatureIntent(intentText, hasMbti)
        if (featIntent && aiChatIntent.replyMatchesFeatureIntent(featIntent, assistantText)) {
          inlineArticle = this._buildFeatureInlineReco(featIntent, hasMbti)
        } else if (
          !featIntent &&
          this.data.articlesDisplayEnabled &&
          (this.data.articles || []).length > 0
        ) {
          recoPackExtra = this._buildInlineRecoPayload(userTurns, true)
          inlineArticle = recoPackExtra.inlineArticle
        }
      }

      const next = base.concat([
        {
          id: aiMsg.id || 'a_' + Date.now(),
          role: 'assistant',
          content: aiMsg.content || '',
          isDegraded: !!aiMsg.isDegraded,
          providerId: aiMsg.providerId || '',
          createdAt: aiMsg.createdAt || Math.floor(Date.now() / 1000),
          inlineArticle
        }
      ])
      const setPayload = {
        messages: next,
        conversationId: (d && d.conversationId) || this.data.conversationId,
        sending: false
      }
      if (recoPackExtra && recoPackExtra.articleRecoPickOrder) {
        setPayload.articleRecoPickOrder = recoPackExtra.articleRecoPickOrder
      }
      this.setData(
        setPayload,
        () => {
          this.scrollToBottom()
          this._refreshReportCta()
        }
      )
      analytics.track('ai_chat_receive', {
        providerId: aiMsg.providerId || '',
        length: (aiMsg.content || '').length,
        inlineReco: inlineArticle ? 1 : 0
      })
      if (inlineArticle) {
        try {
          analytics.track('ai_chat_inline_reco_show', {
            articleId: inlineArticle.id,
            kind: inlineArticle.kind || 'article',
            navPath: inlineArticle.navPath || ''
          })
        } catch (e) {}
      }
      if (aiMsg.isDegraded) {
        analytics.track('ai_chat_degrade', { providerId: aiMsg.providerId || '' })
        wx.showToast({
          title: '当前为备用说明，非模型完整回复',
          icon: 'none',
          duration: 2800
        })
      }
    }

    let sendWatchdog = null
    const clearSendWatchdog = () => {
      if (sendWatchdog) {
        clearTimeout(sendWatchdog)
        sendWatchdog = null
      }
    }

    const failSend = (toastTitle) => {
      clearSendWatchdog()
      if (toastTitle) {
        wx.showToast({ title: toastTitle, icon: 'none', duration: 2800 })
      }
      this.setData({
        messages: prevMessages,
        draft: rollbackDraft,
        sending: false
      })
    }

    const pollChatJob = (jobId) => {
      let attempts = 0
      const maxAttempts = 150
      const delayMs = 1200
      let netFailStreak = 0
      const tick = () => {
        if (attempts++ >= maxAttempts) {
          failSend('等待回复超时，请稍后在历史记录中查看或重发')
          return
        }
        request({
          url: '/api/ai/chat/job?jobId=' + encodeURIComponent(jobId),
          method: 'GET',
          timeout: 60000,
          success: (res) => {
            const sc = res && res.statusCode
            if (sc && (sc < 200 || sc >= 300)) {
              netFailStreak++
              if (netFailStreak <= 5) {
                setTimeout(tick, 500 * netFailStreak)
              } else {
                failSend('网络异常，稍后再试')
              }
              return
            }
            netFailStreak = 0
            const raw = res && res.data
            const body = raw && typeof raw === 'object' ? raw : {}
            const c = body.code
            const isBizOk = c === 200 || c === '200' || Number(c) === 200
            if (!isBizOk) {
              const msg =
                (body.message && String(body.message).trim()) || '获取回复失败'
              const codeNum = Number(body.code)
              const maybeRace =
                (codeNum === 404 || /任务不存在|已过期/i.test(String(msg))) &&
                attempts <= 15
              if (maybeRace) {
                setTimeout(tick, 400)
                return
              }
              failSend(msg.length > 40 ? msg.slice(0, 39) + '…' : msg)
              return
            }
            const d = body.data || {}
            if (d.pending) {
              setTimeout(tick, delayMs)
              return
            }
            applyAssistantPayload(d)
          },
          fail: () => {
            netFailStreak++
            if (netFailStreak <= 5) {
              setTimeout(tick, 600 * netFailStreak)
            } else {
              failSend('网络异常，稍后再试')
            }
          }
        })
      }
      tick()
    }

    const postChat = (isTimeoutRetry) => {
      request({
        url: '/api/ai/chat',
        method: 'POST',
        // 微信客户端对 wx.request 超时上限多为 60s；首包应极快（后端已改为轻量上下文）
        timeout: 60000,
        data: {
          conversationId: this.data.conversationId || 0,
          message: apiMessage,
          resumeFileUrl: resumeFileUrl || undefined,
          resumeFileName: resumeFileName || undefined
        },
        success: (res) => {
          const raw = res && res.data
          const body = raw && typeof raw === 'object' ? raw : {}
          const c = body.code
          const isBizOk = c === 200 || c === '200' || Number(c) === 200
          if (!isBizOk) {
            const bizCode = Number(body.code)
            let msg = (body.message && String(body.message).trim()) || '发送失败，请稍后再试'
            if (bizCode === 401 || /未登录|Token无效|token无效|请先登录/i.test(String(msg))) {
              msg = '需要先微信登录才能对话，请到「我的」授权后再试'
            } else {
              const legacyDaily =
                bizCode === 429 ||
                /今日对话次数已用完|今日对话次数|明天再来找我|次数已用完.*明天|对话次数已用完/i.test(
                  String(msg)
                )
              if (legacyDaily) {
                const rt = (getApp().globalData && getApp().globalData.aiChatRuntime) || {}
                const runtimeNew = rt.unlimited === true && Number(rt.dailyMessageLimit) === 0
                msg = runtimeNew
                  ? '对话接口仍是旧版，请在服务器部署最新 api/AiChat.php'
                  : '对话功能需更新后端；请部署最新代码并确认超管已启用 AI 服务商'
              }
            }
            if (msg.length > 36) msg = msg.slice(0, 35) + '…'
            failSend(msg)
            return
          }

          const d = body.data || {}
          if (d.async && d.jobId) {
            const cid = d.conversationId || this.data.conversationId || 0
            if (cid > 0 && this.data.conversationId !== cid) {
              this.setData({ conversationId: cid })
            }
            pollChatJob(String(d.jobId))
            return
          }

          // 兼容旧版同步响应（无 async）
          applyAssistantPayload(d)
        },
        fail: (err) => {
          const em = (err && err.errMsg) ? String(err.errMsg) : ''
          const isTimeout = /timeout|超时/i.test(em)
          if (isTimeout && !isTimeoutRetry) {
            postChat(true)
            return
          }
          failSend(
            isTimeout ? '提交对话超时，请检查网络与合法域名后重试' : '网络异常，稍后再试'
          )
        }
      })
    }

    const app = getApp()
    let hasToken = !!(app.globalData && app.globalData.token)
    try {
      const st = wx.getStorageSync('token')
      if (st && app && app.globalData && !app.globalData.token) {
        app.globalData.token = st
      }
      hasToken = !!(app.globalData && app.globalData.token) || !!st
    } catch (e) {
      hasToken = !!(app.globalData && app.globalData.token)
    }
    sendWatchdog = setTimeout(() => {
      sendWatchdog = null
      if (!this.data.sending) return
      failSend('对话请求过久，请检查网络、关闭代理或到开发者工具「不校验合法域名」后重试')
    }, 125000)

    if (hasToken) {
      postChat(false)
      return
    }
    app
      .ensureLogin()
      .then((ok) => {
        if (ok) {
          postChat(false)
          return
        }
        clearSendWatchdog()
        this.setData({ messages: prevMessages, draft: rollbackDraft, sending: false })
        wx.showModal({
          title: '需要登录',
          content: '神仙 AI 对话需要先完成微信登录，是否前往「我的」？',
          confirmText: '去登录',
          cancelText: '取消',
          success: (r) => {
            if (r.confirm) wx.switchTab({ url: '/pages/profile/index' })
          }
        })
      })
      .catch(() => {
        clearSendWatchdog()
        this.setData({ messages: prevMessages, draft: rollbackDraft, sending: false })
        wx.showToast({ title: '登录失败，请稍后再试', icon: 'none' })
      })
  },

  onSend() {
    const content = (this.data.draft || '').trim()
    if (!content || this.data.sending) return
    this._beginChatOutgoing({
      bubbleText: content,
      apiMessage: content,
      intentText: content,
      rollbackDraft: content
    })
  },

  scrollToBottom() {
    if (this.data.sending) {
      this.setData({ scrollTarget: 'msg-typing' })
      return
    }
    const msgs = this.data.messages
    const last = msgs.length ? msgs[msgs.length - 1] : null
    this.setData({ scrollTarget: last ? ('msg-' + last.id) : 'bottom' })
  },

  /** 对话内上传简历：直传 OSS 后走 /api/ai/chat，由神仙 AI 结合测评与正文回复（不跳转匹配工作页） */
  onTapUploadResume() {
    if (this.data.sending) return
    try {
      analytics.track('ai_chat_resume_entry_tap', {})
    } catch (e) {}
    const app = getApp()
    const goChoose = () => {
      wx.chooseMessageFile({
        count: 1,
        type: 'file',
        extension: ['pdf', 'doc', 'docx'],
        success: (fileRes) => {
          const files = fileRes.tempFiles || []
          if (!files.length) return
          const file = files[0]
          const filePath = file.path || file.tempFilePath
          const fileName = file.name || '简历.pdf'
          this._uploadResumeAndChat(filePath, fileName)
        },
        fail: (err) => {
          const msg = (err && err.errMsg) || ''
          if (!/cancel/i.test(msg)) {
            wx.showToast({ title: '请选择 pdf/doc/docx 简历', icon: 'none' })
          }
        }
      })
    }
    if (app && typeof app.ensureLogin === 'function') {
      app.ensureLogin().then((ok) => {
        if (ok) goChoose()
        else wx.showToast({ title: '请先登录后再上传简历', icon: 'none' })
      })
      return
    }
    goChoose()
  },

  _uploadResumeAndChat(filePath, fileName) {
    const app = getApp()
    wx.showLoading({ title: '上传简历…', mask: true })
    const apiBase =
      app.globalData && app.globalData.apiBase ? app.globalData.apiBase.replace(/\/$/, '') : ''
    const token = (app.globalData && app.globalData.token) || wx.getStorageSync('token') || ''
    wx.uploadFile({
      url: apiBase + '/api/upload/file',
      filePath,
      name: 'file',
      header: token ? { Authorization: 'Bearer ' + token } : {},
      success: (res) => {
        wx.hideLoading()
        let url = ''
        try {
          const data = JSON.parse(res.data || '{}')
          if (data.code === 200 && data.data && data.data.url) url = data.data.url
        } catch (e) {}
        if (!url) {
          wx.showToast({ title: '上传失败，请重试', icon: 'none' })
          return
        }
        request({
          url: '/api/enterprise/resume-uploads',
          method: 'POST',
          needAuth: true,
          data: { url, fileName },
          success: () => {},
          fail: () => {}
        })
        const bubble =
          '📎 已上传「' +
          fileName +
          '」，请结合我的性格测评，直接在这份对话里帮我分析简历与职业匹配。'
        this._beginChatOutgoing({
          bubbleText: bubble,
          apiMessage: '',
          intentText: bubble,
          rollbackDraft: '',
          resumeFileUrl: url,
          resumeFileName: fileName
        })
      },
      fail: () => {
        wx.hideLoading()
        wx.showToast({ title: '上传失败', icon: 'none' })
      }
    })
  },

  /** 点击"邀请赚佣金"快捷方式：触发原生分享 */
  onTapShare() {
    analytics.track('ai_chat_share_invite_tap', {})
    wx.showShareMenu({ withShareTicket: true, menus: ['shareAppMessage', 'shareTimeline'] })
    wx.showToast({ title: '点右上角 ··· 分享给好友', icon: 'none' })
  },

  /** 点击"生成深度报告 CTA" */
  onTapReportCta() {
    analytics.track('ai_report_cta_tap', { messageCount: this.data.messages.length })
    const cid = this.data.conversationId || 0
    const go = () => {
      wx.navigateTo({
        url: `/pages/ai-chat/report?cid=${cid}`,
        success: () => this._dismissReportCtaPermanently(),
        fail: () => {
          wx.showToast({ title: '报告页暂时无法打开', icon: 'none' })
        }
      })
    }
    const app = getApp()
    if (app && typeof app.ensureLogin === 'function') {
      app.ensureLogin().then((ok) => {
        if (ok) go()
        else wx.showToast({ title: '请先登录后再购买报告', icon: 'none' })
      })
      return
    }
    go()
  },

  _dismissReportCtaPermanently() {
    try {
      wx.setStorageSync(STORAGE_REPORT_CTA_DISMISSED, 1)
    } catch (e) {}
    if (this.data.showReportCta) {
      this.setData({ showReportCta: false })
    }
  },

  /** 根据消息数刷新 CTA：用户发言 >10、未买过、且未点过/未离开曝光过 */
  _refreshReportCta() {
    let dismissed = false
    try {
      dismissed = !!wx.getStorageSync(STORAGE_REPORT_CTA_DISMISSED)
    } catch (e) {}
    const userTurns = (this.data.messages || []).filter((m) => m.role === 'user').length
    const st = this.data.myReportStatus || ''
    const paidBlock = st === 'done' || st === 'paid'
    const show =
      userTurns > REPORT_CTA_MIN_USER_TURNS && !paidBlock && !dismissed
    if (show !== this.data.showReportCta) {
      this.setData({ showReportCta: show })
    }
  },

  onShareAppMessage() {
    const app = getApp() || {}
    const gd = app.globalData || {}
    const inviterId = (gd.userInfo && (gd.userInfo.id || gd.userInfo.user_id)) || 0
    const mbti = this.data.mbtiType || ''
    const title = mbti
      ? `神仙 AI 帮我看懂了 ${mbti}，你也来试试？`
      : '和神仙 AI 聊聊你的 MBTI，超准！'
    const path = `/pages/ai-chat/index?inviterId=${inviterId}&src=ai_chat`
    analytics.track('ai_chat_share', { mbti })
    return { title, path }
  },

  onShareTimeline() {
    return {
      title: '神仙 AI · 帮你看懂自己的 MBTI',
      query: 'src=ai_chat_timeline'
    }
  }
})
