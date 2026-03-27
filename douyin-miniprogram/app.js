// app.js - MBTI抖音小程序主入口
const { request } = require('./utils/request.js')

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
    reviewMode: true
  },

  onLaunch() {
    this.loadStoredData()
    this.silentLogin()

    this.getRuntimeConfig().then((cfg) => {
      if (cfg) {
        if (cfg.siteTitle) this.globalData.siteTitle = cfg.siteTitle
        if (typeof cfg.reviewMode === 'boolean') {
          this.globalData.reviewMode = cfg.reviewMode
        }
      }
    }).catch(() => {})
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
          tt.request({
            url,
            method: 'POST',
            header: { 'Content-Type': 'application/json' },
            data: {
              code: code || '',
              anonymousCode: anonymousCode || ''
            },
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
    const key = `${type}Result`
    tt.setStorageSync(key, result)
    this.globalData[key] = result

    if (this.globalData.token) {
      const scope = this.globalData.appScope || 'personal'
      const storedUser = tt.getStorageSync('userInfo') || null
      const enterpriseId =
        scope === 'enterprise'
          ? (this.globalData.enterpriseIdFromScene || (this.globalData.userInfo && this.globalData.userInfo.enterpriseId) || (storedUser && storedUser.enterpriseId) || null)
          : null
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
        }
      })
    }
  },

  getTestResult(type) {
    return this.globalData[`${type}Result`] || tt.getStorageSync(`${type}Result`) || null
  },

  getRuntimeConfig() {
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
          if (res.statusCode === 200 && res.data && res.data.code === 200) {
            const data = res.data.data || {}
            if (data.siteTitle) this.globalData.siteTitle = data.siteTitle
            if (data.textConfig) this.globalData.textConfig = data.textConfig
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
