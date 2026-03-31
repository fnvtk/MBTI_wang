// app.js - MBTI小程序主入口
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
  if (typeof pageConfig.onShareTimeline === 'function') {
    const origTimeline = pageConfig.onShareTimeline
    pageConfig.onShareTimeline = function () {
      try { require('./utils/analytics.js').reportShare('timeline') } catch (e) {}
      return origTimeline.call(this)
    }
  }
  return _OrigPage(pageConfig)
}

App({
  globalData: {
    userInfo: null,
    openId: null,
    token: null,
    siteTitle: '神仙团队AI性格测试',
    textConfig: null, // 从 /api/config/runtime 动态加载：analyzingTitle, startButtonText, reportTitle, aiAnalysisText 等
    maintenanceMode: undefined, // 审核模式，undefined=未加载，等 getRuntimeConfig 后再决定，避免 tabBar 闪烁
    reviewMode: undefined, // 面相审核开关，与 camera/index 一致，由 runtime.reviewMode 写入
    // 当前使用范围：personal 个人版 / enterprise 企业版（影响定价与 enterpriseId 写入）
    appScope: 'personal',
    // 扫码进入企业页时 scene 解析出的企业ID（e_123），提交测试/分析时优先使用
    enterpriseIdFromScene: null,
    // 超管配置的默认企业 ID（无 scene/eid 等入口参数时回落）
    defaultEnterpriseId: null,
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
    // 企业功能权限（超管在企业管理中配置）：null=无限制（个人版），对象时按 key 控制
    enterprisePermissions: null
  },

  onLaunch(launchOptions) {
    // 记录场景值供埋点使用
    this.globalData.scene = launchOptions && launchOptions.scene ? launchOptions.scene : ''

    // 加载本地存储的数据
    this.loadStoredData()

    // 上报应用启动事件
    try {
      const analytics = require('./utils/analytics.js')
      analytics.reportAppLaunch(launchOptions)
    } catch (e) {}

    // 必须先完成静默登录再拉 runtime：否则无 token 时 enterprisePermissions 永远 null，清除缓存后必现「要二次刷新」
    this.silentLogin()
      .catch(() => {})
      .then(() => this.getRuntimeConfig())
      .then(() => {
        this._afterRuntimeSynced()
      })
      .catch(() => {})
  },

  /**
   * runtime 写入 globalData 后，同步当前栈顶页面与 tabBar（避免首屏用旧 null 权限）
   */
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
          siteTitle: gd.siteTitle || '神仙团队AI性格测试',
          startButtonEnterprise: (maintenanceMode || !pf) ? '开始性格测试' : ((gd.textConfig && gd.textConfig.startButtonEnterprise) || '开始面部测试'),
          aiAnalysisText: (gd.textConfig && gd.textConfig.aiAnalysisText) || '智能分析'
        })
        if (typeof top.getTabBar === 'function') {
          const tb = top.getTabBar()
          if (tb && typeof tb.updateSelected === 'function') tb.updateSelected()
        }
      } else if (route === 'pages/index/camera' && typeof top.setData === 'function') {
        top.setData({ reviewMode: audit })
        if (ep && ep.face === false) {
          wx.navigateTo({ url: '/pages/test-select/index' })
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
          const loginData = { code: res.code }
          try {
            const { getEffectiveEnterpriseId } = require('./utils/enterpriseContext.js')
            const eid = getEffectiveEnterpriseId()
            if (eid) loginData.enterpriseId = eid
          } catch (e) {}
          wx.request({
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
    const { getEnterpriseIdForApiPayload } = require('./utils/enterpriseContext.js')
    const key = `${type}Result`
    wx.setStorageSync(key, result)
    this.globalData[key] = result
    
    // 同步到服务器（需携带 token，后端从 JWT 解析 userId）
    if (this.globalData.token) {
      const enterpriseId = getEnterpriseIdForApiPayload()
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
          enterpriseId: enterpriseId != null ? enterpriseId : undefined,
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
    const reqId = (this._runtimeReqSeq = (this._runtimeReqSeq || 0) + 1)
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
