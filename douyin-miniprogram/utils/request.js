/**
 * 统一请求封装：自动拼接 baseURL、携带 token、处理 401
 * 使用前需已执行 App()，否则 getApp() 在 require 时可能未就绪，这里在请求时再取 app
 */
function getAppSafe() {
  try {
    return getApp()
  } catch (e) {
    return null
  }
}

function getToken() {
  const app = getAppSafe()
  return (app && app.globalData && app.globalData.token) || tt.getStorageSync('token') || ''
}

function getApiBase() {
  const app = getAppSafe()
  return (app && app.globalData && app.globalData.apiBase) || ''
}

/**
 * 清除登录态（401 或主动退出时调用）
 */
function clearLoginState() {
  const app = getAppSafe()
  if (app && app.globalData) {
    app.globalData.token = null
    app.globalData.userInfo = null
    app.globalData.openId = null
  }
  try {
    tt.removeStorageSync('token')
    tt.removeStorageSync('userInfo')
  } catch (e) {}
}

/**
 * 发起请求
 * @param {Object} options - 同 tt.request，url 可为相对路径（自动加 apiBase）
 * @param {boolean} options.needAuth - 是否尝试携带 Authorization（有 token 则带上），默认 true
 * @param {boolean} options.optionalAuth - needAuth 为 false 时仍可在有 token 时附带 Bearer
 * @param {boolean} options.allow401 - 401 且本次请求已带 Authorization 时是否清除登录态，默认 true
 */
function request(options) {
  const apiBase = getApiBase()
  const url = options.url
  const fullUrl = url.startsWith('http') ? url : `${apiBase.replace(/\/$/, '')}${url.startsWith('/') ? '' : '/'}${url}`
  const needAuth = options.needAuth !== false
  const optionalAuth = options.optionalAuth === true
  const allow401 = options.allow401 !== false

  const header = {
    'Content-Type': 'application/json',
    ...(options.header || {})
  }
  if (needAuth) {
    const token = getToken()
    if (token) header['Authorization'] = `Bearer ${token}`
  } else if (optionalAuth) {
    const token = getToken()
    if (token) header['Authorization'] = `Bearer ${token}`
  }

  const authHeaderSent = !!header['Authorization']

  const success = options.success
  const fail = options.fail
  const complete = options.complete

  return tt.request({
    ...options,
    url: fullUrl,
    header,
    success(res) {
      if (res.statusCode === 401 && allow401 && authHeaderSent) {
        clearLoginState()
      }
      if (success) success(res)
    },
    fail(err) {
      if (fail) fail(err)
    },
    complete(res) {
      if (complete) complete(res)
    }
  })
}

/**
 * Promise 版 request，便于 async/await
 */
function requestPromise(options) {
  return new Promise((resolve, reject) => {
    request({
      ...options,
      success(res) {
        if (res.statusCode >= 200 && res.statusCode < 300) {
          resolve(res)
        } else {
          reject(new Error(res.data && res.data.message ? res.data.message : '请求失败'))
        }
      },
      fail: reject
    })
  })
}

module.exports = {
  request,
  requestPromise,
  getToken,
  getApiBase,
  clearLoginState
}
