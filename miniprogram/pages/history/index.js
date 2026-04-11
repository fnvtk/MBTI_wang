// pages/history/index.js - 测试历史记录（一次拉取全部，时间行右侧展示企业名）
const app = getApp()
const { getEffectiveEnterpriseId } = require('../../utils/enterpriseContext.js')

Page({
  data: {
    activeTab: 'all',
    tabName: '',
    list: [],
    total: 0,
    page: 1,
    hasMore: false,
    loading: false,
    loadingMore: false,
    isEnterprise: false,
    reviewMode: false,
    permMbti: true,
    permSbti: true,
    permPdp: true,
    permDisc: true,
    permFace: true
  },

  _checkIsEnterprise() {
    const gd = app.globalData || {}
    const storedUser = wx.getStorageSync('userInfo') || null
    const scope = gd.appScope || 'personal'
    const enterpriseId = getEffectiveEnterpriseId()
    return scope === 'enterprise' || !!enterpriseId
  },

  _syncPermsAndTab() {
    const p = app.globalData.enterprisePermissions
    const permMbti = !p || p.mbti !== false
    const permSbti = !p || p.sbti !== false
    const permPdp = !p || p.pdp !== false
    const permDisc = !p || p.disc !== false
    const permFace = !p || p.face !== false
    const reviewMode = !!app.globalData.reviewMode
    const names = { all: '', mbti: 'MBTI', sbti: 'SBTI', pdp: 'PDP', disc: 'DISC', ai: '面相', resume: '简历' }
    let { activeTab } = this.data
    if (activeTab === 'mbti' && !permMbti) activeTab = 'all'
    if (activeTab === 'sbti' && !permSbti) activeTab = 'all'
    if (activeTab === 'pdp' && !permPdp) activeTab = 'all'
    if (activeTab === 'disc' && !permDisc) activeTab = 'all'
    if (activeTab === 'ai' && (!permFace || reviewMode)) activeTab = 'all'
    this.setData({
      permMbti, permSbti, permPdp, permDisc, permFace, reviewMode,
      activeTab,
      tabName: names[activeTab] || ''
    })
  },

  onLoad() {
    this.setData({ isEnterprise: this._checkIsEnterprise() })
    this._syncPermsAndTab()
    this.loadAll()
  },

  onShow() {
    this.setData({ isEnterprise: this._checkIsEnterprise() })
    this._syncPermsAndTab()
    this.loadAll()
    if (typeof this.getTabBar === 'function' && this.getTabBar()) {
      const tb = this.getTabBar()
      if (typeof tb.updateSelected === 'function') tb.updateSelected()
    }
  },

  /** 首屏/切换 Tab：接口默认 pageSize=10，更多走 loadMore */
  loadAll() {
    if (this.data.loading) return
    this.setData({ loading: true, list: [], page: 1, hasMore: false, loadingMore: false })
    this._fetchHistoryPage(1, false)
  },

  /** 触底加载下一页 */
  loadMore() {
    if (!this.data.hasMore || this.data.loadingMore || this.data.loading) return
    const next = this.data.page + 1
    this.setData({ loadingMore: true })
    this._fetchHistoryPage(next, true)
  },

  onReachBottom() {
    this.loadMore()
  },

  _fetchHistoryPage(pageNum, append) {
    const token = app.globalData.token || wx.getStorageSync('token')
    const apiBase = app.globalData.apiBase
    const { activeTab } = this.data
    const typeParam = activeTab === 'all' ? '' : `&type=${activeTab}`

    if (!token || !apiBase) {
      this.setData({ loading: false, loadingMore: false })
      this.loadFromStorage()
      return
    }

    wx.request({
      url: `${apiBase}/api/test/history?page=${pageNum}&pageSize=10${typeParam}&scope=all`,
      method: 'GET',
      header: { Authorization: `Bearer ${token}` },
      success: (res) => {
        if (res.statusCode === 200 && res.data && res.data.data) {
          const payload = res.data.data
          const rawList = payload.list || []
          const formatted = this.formatList(rawList)
          const total = typeof payload.total === 'number' ? payload.total : formatted.length
          const hasMore = !!payload.hasMore
          if (append) {
            this.setData({
              list: this.data.list.concat(formatted),
              total,
              hasMore,
              page: pageNum,
              loadingMore: false,
              loading: false
            })
          } else {
            this.setData({
              list: formatted,
              total,
              hasMore,
              page: pageNum,
              loading: false,
              loadingMore: false
            })
          }
        } else {
          this.setData({ loading: false, loadingMore: false })
          if (!append) this.loadFromStorage()
        }
      },
      fail: () => {
        this.setData({ loading: false, loadingMore: false })
        if (!append) this.loadFromStorage()
      }
    })
  },

  formatList(rawList) {
    const typeNames = { mbti: 'MBTI性格测试', sbti: 'SBTI性格测试', disc: 'DISC性格测试', pdp: 'PDP行为偏好测试', ai: '面相分析', resume: '简历综合分析' }
    const emojis    = { mbti: '🧠', sbti: '🎭', disc: '📊', pdp: '🦁', ai: '👁️', resume: '📋' }

    return rawList.map((item, idx) => {
      if (item.typeName) {
        const t = String(item.testType || item.type || 'mbti').toLowerCase()
        return { ...item, type: t, enterpriseName: item.enterpriseName || '' }
      }
      const testType = (item.testType || item.type || 'mbti').toLowerCase()
      const ts = item.createdAt || item.testTime || item.timestamp
      return {
        ...item,
        type: testType,
        key: testType + '_' + (item.id || idx),
        emoji: emojis[testType] || '📋',
        typeName: typeNames[testType] || '测试',
        testTime: ts ? this.formatTime(typeof ts === 'number' ? ts * 1000 : ts) : '',
        enterpriseName: item.enterpriseName || ''
      }
    })
  },

  // 本地缓存回退
  loadFromStorage() {
    const { permMbti, permSbti, permPdp, permDisc, permFace, reviewMode } = this.data
    const mbtiResult = wx.getStorageSync('mbtiResult')
    const sbtiResult = wx.getStorageSync('sbtiResult')
    const discResult = wx.getStorageSync('discResult')
    const pdpResult  = wx.getStorageSync('pdpResult')
    const aiResult   = wx.getStorageSync('aiResult')
    const list = []
    if (mbtiResult && permMbti) list.push({ type: 'mbti', key: 'mbti', emoji: '🧠', typeName: 'MBTI性格测试', resultText: mbtiResult.mbtiType || '未知', testTime: this.formatTime(mbtiResult.timestamp), data: mbtiResult })
    if (sbtiResult && permSbti) {
      const rt = sbtiResult.sbtiType || (sbtiResult.finalType && sbtiResult.finalType.code) || '未知'
      const cn = sbtiResult.sbtiCn || (sbtiResult.finalType && sbtiResult.finalType.cn) || ''
      list.push({
        type: 'sbti',
        key: 'sbti',
        emoji: '🎭',
        typeName: 'SBTI性格测试',
        resultText: cn ? `${rt}（${cn}）` : rt,
        testTime: this.formatTime(sbtiResult.timestamp || sbtiResult.completedAt),
        data: sbtiResult
      })
    }
    if (pdpResult && permPdp)  list.push({ type: 'pdp',  key: 'pdp',  emoji: pdpResult.description?.emoji || '🦁', typeName: 'PDP行为偏好测试', resultText: pdpResult.description?.type || '未知', testTime: this.formatTime(pdpResult.timestamp || pdpResult.completedAt), data: pdpResult })
    if (discResult && permDisc) list.push({ type: 'disc', key: 'disc', emoji: '📊', typeName: 'DISC性格测试', resultText: (discResult.dominantType || '未知') + '型', testTime: this.formatTime(discResult.timestamp || discResult.completedAt), data: discResult })
    if (aiResult && permFace && !reviewMode) list.push({ type: 'ai', key: 'ai', emoji: '👁️', typeName: '面相分析', resultText: aiResult.mbti || '未知', testTime: this.formatTime(aiResult.timestamp || aiResult.completedAt), data: aiResult })
    this.setData({ list, total: list.length, page: 1, hasMore: false, loading: false, loadingMore: false })
  },

  changeTab(e) {
    const tab = e.currentTarget.dataset.tab
    const names = { all: '', mbti: 'MBTI', sbti: 'SBTI', pdp: 'PDP', disc: 'DISC', ai: '面相', resume: '简历' }
    this.setData({ activeTab: tab, tabName: names[tab] || '' })
    this.loadAll()
  },

  formatTime(timestamp) {
    if (!timestamp) return '未知时间'
    const date = new Date(timestamp)
    const y   = date.getFullYear()
    const m   = String(date.getMonth() + 1).padStart(2, '0')
    const d   = String(date.getDate()).padStart(2, '0')
    const h   = String(date.getHours()).padStart(2, '0')
    const min = String(date.getMinutes()).padStart(2, '0')
    return `${y}-${m}-${d} ${h}:${min}`
  },

  viewDetail(e) {
    const type = e.currentTarget.dataset.type
    const id   = e.currentTarget.dataset.id
    const routes = {
      mbti:   '/pages/result/mbti',
      sbti:   '/pages/result/sbti',
      disc:   '/pages/result/disc',
      pdp:    '/pages/result/pdp',
      ai:     '/pages/index/result',
      resume: '/pages/result/resume'
    }
    const base = routes[type]
    if (!base) return
    if ((type === 'ai' || type === 'resume') && !id) return
    const query = id ? `?id=${id}&type=${type}` : ''
    wx.navigateTo({ url: query ? base + query : base })
  },

  goToTest() {
    const routes = {
      mbti:   '/pages/test/mbti',
      sbti:   '/pages/test/sbti',
      disc:   '/pages/test/disc',
      pdp:    '/pages/test/pdp',
      ai:     '/pages/index/camera',
      resume: '/pages/enterprise/index'
    }
    const url = routes[this.data.activeTab]
    if (url) wx.navigateTo({ url })
    else wx.switchTab({ url: '/pages/index/index' })
  },

})
