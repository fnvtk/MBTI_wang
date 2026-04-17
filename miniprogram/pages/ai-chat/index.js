// 神仙 AI · 对话页
const { request } = require('../../utils/request.js')
const analytics = require('../../utils/analytics.js')

// 兜底封面（base64 SVG，柔和渐变 + 圆角感）
const DEFAULT_COVER = 'data:image/svg+xml;utf8,' + encodeURIComponent(
  '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 120">' +
  '<defs><linearGradient id="g" x1="0%" y1="0%" x2="100%" y2="100%">' +
  '<stop offset="0%" stop-color="#c4b5fd"/><stop offset="100%" stop-color="#7c3aed"/>' +
  '</linearGradient></defs>' +
  '<rect width="200" height="120" rx="16" fill="url(%23g)"/>' +
  '<text x="100" y="56" font-size="28" fill="white" text-anchor="middle" font-family="PingFang SC,sans-serif" font-weight="700">读</text>' +
  '<text x="100" y="82" font-size="11" fill="rgba(255,255,255,0.9)" text-anchor="middle" font-family="PingFang SC,sans-serif">好文</text>' +
  '</svg>'
)

Page({
  data: {
    articles: [],
    defaultCover: DEFAULT_COVER,
    mbtiType: '',
    nickname: '',
    quickQuestions: [
      '帮我了解并分析一下我现在的状态',
      '我适合什么样的伴侣关系？',
      '我下一步的职业发展方向是什么？',
      '我最适合的工作类型是什么？'
    ],
    messages: [],
    conversationId: 0,
    draft: '',
    sending: false,
    scrollTarget: '',
    usageToday: 0,
    dailyLimit: 20,
    showReportCta: false,
    myReportStatus: '',
    /** 推荐文章区块（与超管 ai_chat_articles 同步） */
    articlesDisplayEnabled: false,
    articlesBlockExpanded: false,
    articlesLoaded: false,
  },

  onLoad(options) {
    const cid = parseInt(options && options.cid, 10)
    if (cid > 0) {
      this.setData({ conversationId: cid })
      this.loadHistory(cid)
    }
    this.loadArticles()
    this.loadQuickQuestions()
    this.tryBindReferral(options)
    this.loadMyReportStatus()
    analytics.track('page_view', { pagePath: 'pages/ai-chat/index' })
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
    request({
      url: '/api/ai/report/my-latest',
      method: 'GET',
      success: (res) => {
        const body = (res && res.data) || {}
        if (body.code !== 200) return
        const d = body.data || {}
        this.setData({ myReportStatus: d.status || '' }, () => this._refreshReportCta())
      },
      fail() {}
    })
  },

  onShow() {
    const tabBar = typeof this.getTabBar === 'function' ? this.getTabBar() : null
    if (tabBar && typeof tabBar.refreshFromConfig === 'function') {
      tabBar.refreshFromConfig()
    }
    this.loadArticles()
  },

  onInput(e) {
    this.setData({ draft: e.detail.value })
  },

  // ---------- 数据加载 ----------

  /** 与后台推荐位一致：去重、条数由 maxShow 限制 */
  normalizeRecoArticles(raw, maxShow) {
    const cap = Math.max(1, Math.min(3, parseInt(maxShow, 10) || 1))
    const list = Array.isArray(raw) ? raw : []
    const seen = new Set()
    const out = []
    for (let i = 0; i < list.length; i++) {
      const it = list[i]
      if (!it || it.id == null) continue
      const id = Number(it.id)
      if (!id || seen.has(id)) continue
      seen.add(id)
      out.push(it)
      if (out.length >= cap) break
    }
    return out
  },

  loadArticles() {
    const t = Date.now()
    request({
      url: `/api/ai/articles/recommended?_t=${t}`,
      method: 'GET',
      needAuth: false,
      success: (res) => {
        const body = (res && res.data) || {}
        if (body.code !== 200) return
        const d = body.data || {}
        const disp = d.display || {}
        const enabled = !!disp.enabled
        const maxShow = Math.max(1, Math.min(3, parseInt(disp.maxShow, 10) || 1))
        const expandedDef = !!disp.sectionExpandedDefault
        const list = enabled ? this.normalizeRecoArticles(d.list || [], maxShow) : []
        this.setData({
          articles: list,
          articlesDisplayEnabled: enabled,
          articlesBlockExpanded: expandedDef,
          articlesLoaded: true
        })
      }
    })
  },

  onToggleArticlesFold() {
    this.setData({ articlesBlockExpanded: !this.data.articlesBlockExpanded })
  },

  /** 去测 MBTI：进入「详细性格测试」列表（含 MBTI 问卷等），不直跳拍摄页 */
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
    request({
      url: '/api/ai/quick-questions',
      method: 'GET',
      // 接口公开：无 token 也可访问；有 token 时自动带上以匹配 MBTI 快捷问句
      success: (res) => {
        const body = (res && res.data) || {}
        if (body.code !== 200) {
          this.setData({ quickQuestions: this.buildQuickQuestions([]) })
          return
        }
        const d = body.data || {}
        this.setData({
          mbtiType: d.mbtiType || '',
          nickname: d.nickname || '',
          quickQuestions: this.buildQuickQuestions(d.questions || [])
        })
      },
      fail: () => {
        // 小程序未登录/接口偶发失败时，仍展示本地快捷提问，避免底部空白
        this.setData({ quickQuestions: this.buildQuickQuestions([]) })
      }
    })
  },

  buildQuickQuestions(serverList) {
    const preferred = [
      '帮我了解并分析一下我现在的状态',
      '我适合什么样的伴侣关系？',
      '我下一步的职业发展方向是什么？',
      '我最适合的工作类型是什么？',
      '我现在最该改掉的一个习惯是什么？'
    ]
    const ban = /记\s*一下\s*我的\s*MBTI/i
    const all = []
    ;(Array.isArray(serverList) ? serverList : []).forEach((q) => {
      if (typeof q === 'string' && q.trim()) {
        const t = q.trim()
        if (!ban.test(t)) all.push(t)
      }
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
    request({
      url: `/api/ai/conversations/${cid}/messages`,
      method: 'GET',
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

  onTapArticle(e) {
    const { id, url, title } = e.currentTarget.dataset
    analytics.track('tap_ai_article', { articleId: id, url, title })
    // 轻量回传点击（异步）
    if (id) {
      request({
        url: `/api/ai/articles/${id}/click`,
        method: 'POST',
        needAuth: false,
        success() {}, fail() {}
      })
    }
    if (!url) return
    // 小程序内嵌 webview 打开外链
    const enc = encodeURIComponent(url)
    wx.navigateTo({
      url: `/pages/webview/index?url=${enc}`,
      fail: () => {
        // 如无 webview 中转页，回退到复制链接
        wx.setClipboardData({
          data: url,
          success: () => wx.showToast({ title: '已复制链接', icon: 'none' })
        })
      }
    })
  },

  onSend() {
    const content = (this.data.draft || '').trim()
    if (!content || this.data.sending) return

    if (this.data.usageToday >= this.data.dailyLimit && this.data.dailyLimit > 0) {
      wx.showToast({ title: '今日对话次数已用完', icon: 'none' })
      return
    }

    const now = Date.now()
    const userMsg = {
      id: 'u_' + now,
      role: 'user',
      content,
      createdAt: Math.floor(now / 1000)
    }
    const messages = this.data.messages.concat([userMsg])
    this.setData({ messages, draft: '', sending: true }, () => this.scrollToBottom())

    analytics.track('ai_chat_send', { length: content.length })

    request({
      url: '/api/ai/chat',
      method: 'POST',
      data: {
        conversationId: this.data.conversationId || 0,
        message: content
      },
      success: (res) => {
        const body = (res && res.data) || {}
        if (res.statusCode === 429) {
          wx.showToast({ title: body.message || '今日次数用完', icon: 'none' })
          this.setData({ sending: false })
          return
        }
        if (body.code !== 200) {
          const hint = body.message || (res.statusCode ? `服务异常(${res.statusCode})` : 'AI 失联了')
          wx.showToast({ title: hint, icon: 'none', duration: 2800 })
          this.setData({ sending: false })
          return
        }

        const d = body.data || {}
        const aiMsg = d.message || {}
        const next = this.data.messages.concat([{
          id: aiMsg.id || ('a_' + Date.now()),
          role: 'assistant',
          content: aiMsg.content || '',
          isDegraded: !!aiMsg.isDegraded,
          providerId: aiMsg.providerId || '',
          createdAt: aiMsg.createdAt || Math.floor(Date.now() / 1000)
        }])

        this.setData({
          messages: next,
          conversationId: d.conversationId || this.data.conversationId,
          usageToday: d.usageToday || this.data.usageToday,
          dailyLimit: d.dailyLimit || this.data.dailyLimit,
          sending: false
        }, () => {
          this.scrollToBottom()
          this._refreshReportCta()
        })

        analytics.track('ai_chat_receive', {
          providerId: aiMsg.providerId || '',
          length: (aiMsg.content || '').length
        })

        if (aiMsg.isDegraded) {
          analytics.track('ai_chat_degrade', { providerId: aiMsg.providerId || '' })
        }
      },
      fail: () => {
        wx.showToast({ title: '网络异常，稍后再试', icon: 'none' })
        this.setData({ sending: false })
      }
    })
  },

  scrollToBottom() {
    const msgs = this.data.messages
    const last = msgs.length ? msgs[msgs.length - 1] : null
    this.setData({ scrollTarget: last ? ('msg-' + last.id) : 'bottom' })
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
    wx.navigateTo({
      url: `/pages/ai-chat/report?cid=${this.data.conversationId || 0}`,
      fail: () => {
        wx.showToast({ title: '报告功能即将开放', icon: 'none' })
      }
    })
  },

  /** 根据消息数刷新 CTA 显隐 */
  _refreshReportCta() {
    const aiCount = (this.data.messages || []).filter(m => m.role === 'assistant').length
    const show = aiCount >= 5 && this.data.myReportStatus !== 'done' && this.data.myReportStatus !== 'paid'
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
