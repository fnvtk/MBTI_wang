// pages/history/index.js - 测试历史记录（一次拉取全部，时间行右侧展示企业名）
const app = getApp()

Page({
  data: {
    activeTab: 'all',
    tabName: '',
    list: [],
    total: 0,
    loading: false,
    isEnterprise: false,
    reviewMode: false
  },

  _checkIsEnterprise() {
    const gd = app.globalData || {}
    const storedUser = wx.getStorageSync('userInfo') || null
    const scope = gd.appScope || 'personal'
    const enterpriseId = gd.enterpriseIdFromScene
      || (gd.userInfo && gd.userInfo.enterpriseId)
      || (storedUser && storedUser.enterpriseId)
      || null
    return scope === 'enterprise' || !!enterpriseId
  },

  onLoad() {
    this.setData({ isEnterprise: this._checkIsEnterprise() })
    this.loadAll()
  },

  onShow() {
    this.setData({ isEnterprise: this._checkIsEnterprise(), reviewMode: !!app.globalData.reviewMode })
    this.loadAll()
    if (typeof this.getTabBar === 'function' && this.getTabBar()) {
      this.getTabBar().setData({ selected: 1 })
    }
  },

  // 一次拉取全部历史（pageSize=500）
  loadAll() {
    if (this.data.loading) return
    this.setData({ loading: true, list: [] })

    const token = app.globalData.token || wx.getStorageSync('token')
    const apiBase = app.globalData.apiBase
    const { activeTab } = this.data
    const typeParam = activeTab === 'all' ? '' : `&type=${activeTab}`

    if (!token || !apiBase) {
      this.setData({ loading: false })
      this.loadFromStorage()
      return
    }

    wx.request({
      url: `${apiBase}/api/test/history?page=1&pageSize=500${typeParam}&scope=all`,
      method: 'GET',
      header: { Authorization: `Bearer ${token}` },
      success: (res) => {
        if (res.statusCode === 200 && res.data && res.data.data) {
          const payload = res.data.data
          const rawList = Array.isArray(payload) ? payload : (payload.list || [])
          const total = Array.isArray(payload) ? rawList.length : (payload.total || 0)
          const formatted = this.formatList(rawList)
          this.setData({ list: formatted, total, loading: false })
        } else {
          this.setData({ loading: false })
          this.loadFromStorage()
        }
      },
      fail: () => {
        this.setData({ loading: false })
        this.loadFromStorage()
      }
    })
  },

  formatList(rawList) {
    const typeNames = { mbti: 'MBTI性格测试', disc: 'DISC性格测试', pdp: 'PDP行为偏好测试', ai: '面相分析', resume: '简历综合分析' }
    const emojis    = { mbti: '🧠', disc: '📊', pdp: '🦁', ai: '👁️', resume: '📋' }

    return rawList.map((item, idx) => {
      if (item.typeName) {
        return { ...item, enterpriseName: item.enterpriseName || '' }
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
    const mbtiResult = wx.getStorageSync('mbtiResult')
    const discResult = wx.getStorageSync('discResult')
    const pdpResult  = wx.getStorageSync('pdpResult')
    const aiResult   = wx.getStorageSync('aiResult')
    const list = []
    if (mbtiResult) list.push({ type: 'mbti', key: 'mbti', emoji: '🧠', typeName: 'MBTI性格测试', resultText: mbtiResult.mbtiType || '未知', testTime: this.formatTime(mbtiResult.timestamp), data: mbtiResult })
    if (pdpResult)  list.push({ type: 'pdp',  key: 'pdp',  emoji: pdpResult.description?.emoji || '🦁', typeName: 'PDP行为偏好测试', resultText: pdpResult.description?.type || '未知', testTime: this.formatTime(pdpResult.timestamp || pdpResult.completedAt), data: pdpResult })
    if (discResult) list.push({ type: 'disc', key: 'disc', emoji: '📊', typeName: 'DISC性格测试', resultText: (discResult.dominantType || '未知') + '型', testTime: this.formatTime(discResult.timestamp || discResult.completedAt), data: discResult })
    if (aiResult && !app.globalData.reviewMode) list.push({ type: 'ai', key: 'ai', emoji: '👁️', typeName: '面相分析', resultText: aiResult.mbti || '未知', testTime: this.formatTime(aiResult.timestamp || aiResult.completedAt), data: aiResult })
    this.setData({ list, total: list.length, loading: false })
  },

  changeTab(e) {
    const tab = e.currentTarget.dataset.tab
    const names = { all: '', mbti: 'MBTI', pdp: 'PDP', disc: 'DISC', ai: '面相', resume: '简历' }
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
