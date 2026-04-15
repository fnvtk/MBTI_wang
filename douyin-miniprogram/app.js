// app.js - MBTI抖音小程序主入口
const { request } = require('./utils/request.js')

// 全局注入：所有页面 onShow 自动上报 page_view；onShareAppMessage 自动上报 share
const _OrigPage = Page
Page = function (pageConfig) {
  const origOnShow = pageConfig.onShow
  pageConfig.onShow = function () {
    try {
      const { reportPageView } = require('./utils/analytics.js')
      reportPageView()
    } catch (e) {}
    if (typeof origOnShow === 'function') {
      origOnShow.call(this)
    }
  }
  if (typeof pageConfig.onShareAppMessage === 'function') {
    const origShare = pageConfig.onShareAppMessage
    pageConfig.onShareAppMessage = function (res) {
      try { require('./utils/analytics.js').reportShare('friend') } catch (e) {}
      return origShare.call(this, res)
    }
  }
  return _OrigPage(pageConfig)
}

App({
  globalData: {
    userInfo: null,
    openId: null,
    token: null,
    siteTitle: '神仙团队性格测试',
    textConfig: null,
    appScope: 'personal',
    enterpriseIdFromScene: null,
    apiBase: 'https://mbtiapi.quwanzhi.com',
    vipInfo: null,
    testCount: 0,
    unlockedTests: [],
    mbtiResult: null,
    discResult: null,
    pdpResult: null,
    aiResult: null,
    maintenanceMode: undefined,
    reviewMode: undefined,
    enterprisePermissions: null
  },

  onLaunch(launchOptions) {
    this.globalData.scene = launchOptions && launchOptions.scene ? launchOptions.scene : ''
    this.loadStoredData()

    try {
      const analytics = require('./utils/analytics.js')
      analytics.reportAppLaunch(launchOptions)
    } catch (e) {}

    this.silentLogin()
      .catch(() => {})
      .then(() => this.getRuntimeConfig())
      .then(() => {
        this._afterRuntimeSynced()
      })
      .catch(() => {})
  },

  _afterRuntimeSynced() {
    try {
      const pages = getCurrentPages()
      if (!pages || pages.length === 0) return
      const top = pages[pages.length - 1]
      if (!top) return
      const gd = this.globalData
      const ep = gd.enterprisePermissions
      const audit = !!(gd.reviewMode || gd.maintenanceMode)
      const permFace = !ep || ep.face !== false

      if (typeof top.getTabBar === 'function') {
        const tb = top.getTabBar()
        if (tb && typeof tb.updateSelected === 'function') tb.updateSelected()
      }

      const route = top.route || ''
      if (route === 'pages/index/index' && typeof top.setData === 'function') {
        top.setData({
          reviewMode: audit,
          permFace,
          siteTitle: audit ? String(gd.siteTitle || '神仙团队性格测试').replace(/AI/gi, '') : (gd.siteTitle || '神仙团队性格测试'),
          startButtonText: (audit || (ep && ep.face === false)) ? '开始性格测试' : ((gd.textConfig && gd.textConfig.startButtonText) || '拍摄'),
          aiAnalysisText: audit ? '分析' : ((gd.textConfig && gd.textConfig.aiAnalysisText) || '分析')
        })
      } else if (route === 'pages/profile/index' && typeof top.setData === 'function') {
        if (typeof top._syncPerms === 'function') top._syncPerms.call(top)
        top.setData({ reviewMode: audit })
        if (typeof top.getTabBar === 'function') {
          const tb = top.getTabBar()
          if (tb && typeof tb.updateSelected === 'function') tb.updateSelected()
        }
      } else if (route === 'pages/enterprise/index' && typeof top.setData === 'function') {
        const maintenanceMode = audit
        const pf = permFace
        top.setData({
          maintenanceMode,
          reviewMode: maintenanceMode,
          permFace: pf,
          siteTitle: gd.siteTitle || '神仙团队性格测试',
          startButtonEnterprise: (maintenanceMode || !pf) ? '开始性格测试' : ((gd.textConfig && gd.textConfig.startButtonEnterprise) || '开始性格测试'),
          aiAnalysisText: (gd.textConfig && gd.textConfig.aiAnalysisText) || '分析'
        })
        if (typeof top.getTabBar === 'function') {
          const tb = top.getTabBar()
          if (tb && typeof tb.updateSelected === 'function') tb.updateSelected()
        }
      } else if (route === 'pages/index/camera' && typeof top.setData === 'function') {
        top.setData({ reviewMode: audit })
        if (ep && ep.face === false) {
          tt.navigateTo({ url: '/pages/test-select/index' })
        }
        if (typeof top.getTabBar === 'function') {
          const tb = top.getTabBar()
          if (tb && typeof tb.updateSelected === 'function') tb.updateSelected()
        }
      }
    } catch (e) {
      console.error('_afterRuntimeSynced', e)
    }
  },

  onShow() {
    try {
      const { reportPageView } = require('./utils/analytics.js')
      reportPageView()
    } catch (e) {}
  },

  onHide() {
    try {
      const { flush } = require('./utils/analytics.js')
      flush()
    } catch (e) {}
  },

  loadStoredData() {
    const token = tt.getStorageSync('token')
    if (token) {
      this.globalData.token = token
    }
    const userInfo = tt.getStorageSync('userInfo')
    if (userInfo) {
      this.globalData.userInfo = userInfo
      this.globalData.openId = userInfo.openid || userInfo.openId || null
    }

    const vipInfo = tt.getStorageSync('vipInfo')
    if (vipInfo) {
      this.globalData.vipInfo = vipInfo
    }

    const testCount = tt.getStorageSync('testCount')
    if (testCount) {
      this.globalData.testCount = testCount
    }

    const unlockedTests = tt.getStorageSync('unlockedTests')
    if (unlockedTests) {
      this.globalData.unlockedTests = unlockedTests
    }

    this.globalData.mbtiResult = tt.getStorageSync('mbtiResult') || null
    this.globalData.discResult = tt.getStorageSync('discResult') || null
    this.globalData.pdpResult = tt.getStorageSync('pdpResult') || null
    this.globalData.aiResult = tt.getStorageSync('aiResult') || null
  },

  /**
   * 静默登录：tt.login 取 code，请求后端换 token 与用户信息
   * 抖音 tt.login 返回 code + anonymousCode
   * 后端需实现 /api/auth/douyin 接口（code2session 换 openid）
   */
  silentLogin() {
    return new Promise((resolve) => {
      tt.login({
        force: false,
        success: (res) => {
          const code = res.code
          const anonymousCode = res.anonymousCode

          if (!code && !anonymousCode) {
            resolve(false)
            return
          }

          const url = `${this.globalData.apiBase}/api/auth/douyin`
          const loginData = { code: code || '', anonymousCode: anonymousCode || '' }
          try {
            const gd = this.globalData || {}
            const eid = gd.enterpriseIdFromScene || (gd.userInfo && gd.userInfo.enterpriseId) || gd.defaultEnterpriseId
            if (eid && Number(eid) > 0) loginData.enterpriseId = Number(eid)
          } catch (e) {}
          tt.request({
            url,
            method: 'POST',
            header: { 'Content-Type': 'application/json' },
            data: loginData,
            success: (response) => {
              if (response.statusCode === 200 && response.data && response.data.code === 200) {
                const data = response.data.data || {}
                const { token, user } = data
                if (token) {
                  this.globalData.token = token
                  tt.setStorageSync('token', token)
                }
                if (user) {
                  this.globalData.userInfo = user
                  this.globalData.openId = user.openid || user.openId || null
                  tt.setStorageSync('userInfo', user)
                }
                if (token) {
                  this._tryDistributionBind()
                }
                resolve(!!token)
                return
              }
              resolve(false)
            },
            fail: (err) => {
              console.error('登录请求失败:', err)
              const storedToken = tt.getStorageSync('token')
              const storedUser = tt.getStorageSync('userInfo')
              if (storedToken) {
                this.globalData.token = storedToken
                this.globalData.userInfo = storedUser || null
                this.globalData.openId = storedUser ? storedUser.id : null
                resolve(true)
              } else {
                resolve(false)
              }
            }
          })
        },
        fail: () => resolve(false)
      })
    })
  },

  ensureLogin() {
    if (this.globalData.token) return Promise.resolve(true)
    return this.silentLogin()
  },

  _tryDistributionBind() {
    const inviterId = this.globalData._pendingInviterId
    const scope     = this.globalData._pendingInviterScope || 'personal'
    const eid       = this.globalData._pendingInviterEid || null

    if (!inviterId || inviterId <= 0) return

    this.globalData._pendingInviterId    = null
    this.globalData._pendingInviterScope = null
    this.globalData._pendingInviterEid   = null

    const token = this.globalData.token
    if (!token) return

    const data = { inviterId, scope }
    if (eid) data.eid = eid

    request({
      url: '/api/distribution/bind',
      method: 'POST',
      data,
      success: () => {},
      fail: () => {}
    })
  },

  logout() {
    this.globalData.token = null
    this.globalData.userInfo = null
    this.globalData.openId = null
    tt.removeStorageSync('token')
    tt.removeStorageSync('userInfo')
  },

  /**
   * 获取用户信息
   * 抖音使用 tt.getUserProfile 获取头像和昵称
   */
  getUserInfo(callback) {
    tt.getUserProfile({
      desc: '用于展示用户头像和昵称',
      success: (res) => {
        const u = res.userInfo
        this.globalData.userInfo = { ...this.globalData.userInfo, ...u, avatarUrl: u.avatarUrl || u.avatar }
        tt.setStorageSync('userInfo', this.globalData.userInfo)
        callback && callback(this.globalData.userInfo)
        this.syncProfileToServer({ nickname: u.nickName, avatar: u.avatarUrl || u.avatar })
      },
      fail: () => {
        callback && callback(this.globalData.userInfo || null)
      }
    })
  },

  syncProfileToServer(profile, callback) {
    if (!this.globalData.token || !profile) {
      callback && callback(false)
      return
    }

    console.log('同步用户资料到服务器:', profile)

    request({
      url: '/api/auth/douyin/profile',
      method: 'PUT',
      data: profile,
      success: (res) => {
        console.log('同步用户资料响应:', res)
        if (res.statusCode === 200 && res.data && res.data.code === 200) {
          if (res.data.data) {
            const updatedUserInfo = { ...this.globalData.userInfo, ...res.data.data }
            if (profile.nickname) {
              updatedUserInfo.nickname = profile.nickname
              updatedUserInfo.nickName = profile.nickname
            }
            if (profile.avatar) {
              updatedUserInfo.avatar = profile.avatar
              updatedUserInfo.avatarUrl = profile.avatar
            }
            if (profile.birthday !== undefined) {
              updatedUserInfo.birthday = profile.birthday
            }
            this.globalData.userInfo = updatedUserInfo
            tt.setStorageSync('userInfo', updatedUserInfo)
            console.log('用户资料已同步:', updatedUserInfo)
          } else {
            const updatedUserInfo = { ...this.globalData.userInfo, ...profile }
            if (profile.nickname) {
              updatedUserInfo.nickname = profile.nickname
              updatedUserInfo.nickName = profile.nickname
            }
            if (profile.avatar) {
              updatedUserInfo.avatar = profile.avatar
              updatedUserInfo.avatarUrl = profile.avatar
            }
            if (profile.birthday !== undefined) {
              updatedUserInfo.birthday = profile.birthday
            }
            this.globalData.userInfo = updatedUserInfo
            tt.setStorageSync('userInfo', updatedUserInfo)
            console.log('用户资料已更新（本地）:', updatedUserInfo)
          }
          callback && callback(true)
        } else {
          console.error('更新用户资料失败:', res.data)
          callback && callback(false)
        }
      },
      fail: (err) => {
        console.error('请求失败:', err)
        callback && callback(false)
      }
    })
  },

  saveTestResult(type, result) {
    const { triggerTestResultCompleted } = require('./utils/pushHook.js')
    const key = `${type}Result`
    tt.setStorageSync(key, result)
    this.globalData[key] = result

    if (!this.globalData.token) {
      return Promise.resolve({})
    }

    const scope = this.globalData.appScope || 'personal'
    const storedUser = tt.getStorageSync('userInfo') || null
    const enterpriseId =
      scope === 'enterprise'
        ? (this.globalData.enterpriseIdFromScene || (this.globalData.userInfo && this.globalData.userInfo.enterpriseId) || (storedUser && storedUser.enterpriseId) || null)
        : null

    return new Promise((resolve) => {
      tt.request({
        url: `${this.globalData.apiBase}/api/test/submit`,
        method: 'POST',
        header: {
          'Authorization': `Bearer ${this.globalData.token}`,
          'Content-Type': 'application/json'
        },
        data: {
          testType: type,
          answers: result.answers || [],
          result: result,
          userId: this.globalData.userInfo?.id ?? this.globalData.openId,
          enterpriseId: enterpriseId || undefined,
          testDuration: result.testDuration || 0,
          timestamp: new Date().toISOString()
        },
        success: (res) => {
          if (res.statusCode === 200 && res.data && res.data.code === 200 && res.data.data && typeof res.data.data === 'object') {
            const extra = res.data.data
            if (extra && extra.id) {
              triggerTestResultCompleted(extra.id)
            }
            resolve(extra)
          } else {
            resolve({})
          }
        },
        fail: () => resolve({})
      })
    })
  },

  getTestResult(type) {
    return this.globalData[`${type}Result`] || tt.getStorageSync(`${type}Result`) || null
  },

  getRuntimeConfig() {
    const reqId = (this._runtimeReqSeq = (this._runtimeReqSeq || 0) + 1)
    return new Promise((resolve, reject) => {
      const scope = this.globalData.appScope || 'personal'
      const base = this.globalData.apiBase.replace(/\/$/, '')
      const url = `${base}/api/config/runtime?scope=${encodeURIComponent(scope)}`
      const token = this.globalData.token || tt.getStorageSync('token') || ''
      tt.request({
        url,
        method: 'GET',
        header: token ? { Authorization: 'Bearer ' + token } : {},
        success: (res) => {
          if (reqId !== this._runtimeReqSeq) {
            resolve(null)
            return
          }
          if (res.statusCode === 200 && res.data && res.data.code === 200) {
            const data = res.data.data || {}
            if (data.siteTitle) this.globalData.siteTitle = data.siteTitle
            if (data.textConfig) this.globalData.textConfig = data.textConfig
            if (data.maintenanceMode !== undefined) this.globalData.maintenanceMode = !!data.maintenanceMode
            if (data.reviewMode !== undefined) {
              this.globalData.reviewMode = !!data.reviewMode
            } else if (data.maintenanceMode !== undefined) {
              this.globalData.reviewMode = !!data.maintenanceMode
            }
            if (data.defaultEnterpriseId != null && Number(data.defaultEnterpriseId) > 0) {
              this.globalData.defaultEnterpriseId = Number(data.defaultEnterpriseId)
            } else {
              this.globalData.defaultEnterpriseId = null
            }
            if (data.enterprisePermissions && typeof data.enterprisePermissions === 'object') {
              this.globalData.enterprisePermissions = data.enterprisePermissions
            } else {
              this.globalData.enterprisePermissions = null
            }
            resolve(data)
          } else {
            reject(new Error(res.data && res.data.message ? res.data.message : '获取配置失败'))
          }
        },
        fail: reject
      })
    })
  }
})
