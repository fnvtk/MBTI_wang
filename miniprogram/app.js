// app.js - MBTI小程序主入口
const { request } = require('./utils/request.js')

App({
  globalData: {
    userInfo: null,
    openId: null,
    token: null,
    siteTitle: '神仙团队AI性格测试',
    textConfig: null, // 从 /api/config/runtime 动态加载：analyzingTitle, startButtonText, reportTitle, aiAnalysisText 等
    // 当前使用范围：personal 个人版 / enterprise 企业版（影响定价与 enterpriseId 写入）
    appScope: 'personal',
    // 扫码进入企业页时 scene 解析出的企业ID（e_123），提交测试/分析时优先使用
    enterpriseIdFromScene: null,
    // API基础地址（开发时用本地，生产环境替换为实际域名）
    apiBase: 'https://mbtiapi.quwanzhi.com',
    //apiBase: 'http://mbti.com',
    // VIP信息
    vipInfo: null,
    // 测试次数
    testCount: 0,
    // 已解锁的测试
    unlockedTests: [],
    // 测试结果缓存
    mbtiResult: null,
    discResult: null,
    pdpResult: null,
    aiResult: null,
    // 审核模式：true 时隐藏AI面相分析功能，仅展示问卷测试
    reviewMode: false
  },

  onLaunch() {
    // 加载本地存储的数据
    this.loadStoredData()
    
    // 静默登录获取openId
    this.silentLogin()

    // 预加载站点/小程序名称 + 审核模式
    this.getRuntimeConfig().then((cfg) => {
      if (cfg) {
        if (cfg.siteTitle) this.globalData.siteTitle = cfg.siteTitle
        this.globalData.reviewMode = !!cfg.reviewMode
      }
    }).catch(() => {})
  },

  // 加载本地存储数据
  loadStoredData() {
    const token = wx.getStorageSync('token')
    if (token) {
      this.globalData.token = token
    }
    const userInfo = wx.getStorageSync('userInfo')
    if (userInfo) {
      this.globalData.userInfo = userInfo
      // 优先使用后端返回的 openid 字段，而不是内部自增 id
      this.globalData.openId = userInfo.openid || userInfo.openId || null
    }
    
    // VIP信息
    const vipInfo = wx.getStorageSync('vipInfo')
    if (vipInfo) {
      this.globalData.vipInfo = vipInfo
    }
    
    // 测试次数
    const testCount = wx.getStorageSync('testCount')
    if (testCount) {
      this.globalData.testCount = testCount
    }
    
    // 已解锁测试
    const unlockedTests = wx.getStorageSync('unlockedTests')
    if (unlockedTests) {
      this.globalData.unlockedTests = unlockedTests
    }
    
    // 测试结果
    this.globalData.mbtiResult = wx.getStorageSync('mbtiResult') || null
    this.globalData.discResult = wx.getStorageSync('discResult') || null
    this.globalData.pdpResult = wx.getStorageSync('pdpResult') || null
    this.globalData.aiResult = wx.getStorageSync('aiResult') || null
  },

  /**
   * 静默登录：wx.login 取 code，请求后端换 token 与用户信息
   * @returns {Promise<boolean>} 是否登录成功（拿到 token）
   */
  silentLogin() {
    return new Promise((resolve) => {
      wx.login({
        success: (res) => {
          if (!res.code) {
            resolve(false)
            return
          }
          const url = `${this.globalData.apiBase}/api/auth/wechat`
          wx.request({
            url,
            method: 'POST',
            header: { 'Content-Type': 'application/json' },
            data: { code: res.code },
            success: (response) => {
              if (response.statusCode === 200 && response.data && response.data.code === 200) {
                const data = response.data.data || {}
                const { token, user } = data
                if (token) {
                  this.globalData.token = token
                  wx.setStorageSync('token', token)
                }
                if (user) {
                  this.globalData.userInfo = user
                  // 使用微信真实 openid，而不是 wechat_users 表的 id
                  this.globalData.openId = user.openid || user.openId || null
                  wx.setStorageSync('userInfo', user)
                }
                // 登录成功后处理分销绑定（若进入时携带了推荐人参数）
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
              const storedToken = wx.getStorageSync('token')
              const storedUser = wx.getStorageSync('userInfo')
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

  /**
   * 确保已登录：有 token 直接 resolve，否则先执行静默登录
   * @returns {Promise<boolean>} 当前是否有有效登录态
   */
  ensureLogin() {
    if (this.globalData.token) return Promise.resolve(true)
    return this.silentLogin()
  },

  /**
   * 登录成功后尝试分销绑定（处理进入时携带的 uid 参数）
   */
  _tryDistributionBind() {
    const inviterId = this.globalData._pendingInviterId
    const scope     = this.globalData._pendingInviterScope || 'personal'
    const eid       = this.globalData._pendingInviterEid || null

    if (!inviterId || inviterId <= 0) return

    // 清除 pending 状态，避免重复绑定
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

  /** 清除登录态（退出登录时调用） */
  logout() {
    this.globalData.token = null
    this.globalData.userInfo = null
    this.globalData.openId = null
    wx.removeStorageSync('token')
    wx.removeStorageSync('userInfo')
  },

  // 获取用户信息（弹窗授权），并同步昵称/头像到后端
  getUserInfo(callback) {
    wx.getUserProfile({
      desc: '用于展示用户头像和昵称',
      success: (res) => {
        const u = res.userInfo
        this.globalData.userInfo = { ...this.globalData.userInfo, ...u, avatarUrl: u.avatarUrl || u.avatar }
        wx.setStorageSync('userInfo', this.globalData.userInfo)
        callback && callback(this.globalData.userInfo)
        this.syncProfileToServer({ nickname: u.nickName, avatar: u.avatarUrl || u.avatar })
      },
      fail: () => {
        callback && callback(this.globalData.userInfo || null)
      }
    })
  },

  // 同步昵称、头像到后端（需已登录）
  syncProfileToServer(profile, callback) {
    if (!this.globalData.token || !profile) {
      callback && callback(false)
      return
    }
    
    // 记录同步请求（调试用）
    console.log('同步用户资料到服务器:', profile)
    
    request({
      url: '/api/auth/wechat/profile',
      method: 'PUT',
      data: profile,
      success: (res) => {
        console.log('同步用户资料响应:', res)
        if (res.statusCode === 200 && res.data && res.data.code === 200) {
          // 如果服务器返回了更新后的数据，使用服务器数据
          if (res.data.data) {
            const updatedUserInfo = { ...this.globalData.userInfo, ...res.data.data }
            // 确保 nickname 和 nickName 字段都更新
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
            wx.setStorageSync('userInfo', updatedUserInfo)
            console.log('用户资料已同步:', updatedUserInfo)
          } else {
            // 服务器没有返回数据，使用本地更新的数据
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
            wx.setStorageSync('userInfo', updatedUserInfo)
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

  // 保存测试结果
  saveTestResult(type, result) {
    const key = `${type}Result`
    wx.setStorageSync(key, result)
    this.globalData[key] = result
    
    // 同步到服务器（需携带 token，后端从 JWT 解析 userId）
    if (this.globalData.token) {
      const scope = this.globalData.appScope || 'personal'
      const storedUser = wx.getStorageSync('userInfo') || null
      const enterpriseId =
        scope === 'enterprise'
          ? (this.globalData.enterpriseIdFromScene || (this.globalData.userInfo && this.globalData.userInfo.enterpriseId) || (storedUser && storedUser.enterpriseId) || null)
          : null
      wx.request({
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

  // 获取测试结果
  getTestResult(type) {
    return this.globalData[`${type}Result`] || wx.getStorageSync(`${type}Result`) || null
  },

  /**
   * 获取运行配置：个人/企业定价 + 当前 AI 服务商（超管配置，默认第一个启用的）
   * 有 token 且用户属于企业则返回企业定价，否则个人定价
   * @returns {Promise<{pricingType, pricing, aiProviderId, aiProviderName}>}
   */
  getRuntimeConfig() {
    return new Promise((resolve, reject) => {
      const scope = this.globalData.appScope || 'personal'
      const base = this.globalData.apiBase.replace(/\/$/, '')
      const url = `${base}/api/config/runtime?scope=${encodeURIComponent(scope)}`
      const token = this.globalData.token || wx.getStorageSync('token') || ''
      wx.request({
        url,
        method: 'GET',
        header: token ? { Authorization: 'Bearer ' + token } : {},
        success: (res) => {
          if (res.statusCode === 200 && res.data && res.data.code === 200) {
            const data = res.data.data || {}
            if (data.siteTitle) this.globalData.siteTitle = data.siteTitle
            if (data.textConfig) this.globalData.textConfig = data.textConfig
            this.globalData.reviewMode = !!data.reviewMode
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
