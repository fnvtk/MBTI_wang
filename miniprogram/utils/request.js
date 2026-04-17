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
  return (app && app.globalData && app.globalData.token) || wx.getStorageSync('token') || ''
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
    wx.removeStorageSync('token')
    wx.removeStorageSync('userInfo')
  } catch (e) {}
}

/**
 * 发起请求
 * @param {Object} options - 同 wx.request，url 可为相对路径（自动加 apiBase）
 * @param {boolean} options.needAuth - 是否携带 Authorization，默认 true
 * @param {boolean} options.allow401 - 401 时是否静默清除登录态而不 fail，默认 true
 */
function request(options) {
  const apiBase = getApiBase()
  const url = options.url
  const fullUrl = url.startsWith('http') ? url : `${apiBase.replace(/\/$/, '')}${url.startsWith('/') ? '' : '/'}${url}`
  const needAuth = options.needAuth !== false
  const allow401 = options.allow401 !== false

  const header = {
    'Content-Type': 'application/json',
    ...(options.header || {})
  }
  if (needAuth) {
    const token = getToken()
    if (token) header['Authorization'] = `Bearer ${token}`
  }

  const success = options.success
  const fail = options.fail
  const complete = options.complete

  return wx.request({
    ...options,
    url: fullUrl,
    header,
    success(res) {
      if (res.statusCode === 401 && allow401) {
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
function requestPromiseOnce(options) {
  return new Promise((resolve, reject) => {
    request({
      timeout: options.timeout || 15000,
      ...options,
      success(res) {
        if (res.statusCode >= 200 && res.statusCode < 300) {
          resolve(res)
        } else {
          let msg = '请求失败'
          const d = res.data
          if (d && typeof d === 'object' && d.message) msg = d.message
          else if (typeof d === 'string') {
            try {
              const o = JSON.parse(d)
              if (o && o.message) msg = o.message
            } catch (e) {
              if (res.statusCode === 401) msg = '未登录或登录已过期'
            }
          }
          const err = new Error(msg)
          err.statusCode = res.statusCode
          reject(err)
        }
      },
      fail(err) {
        // wx.request fail：网络不可达 / CONNECTION_CLOSED / TLS / 超时
        const e = new Error((err && err.errMsg) || '网络异常')
        e.isNetworkError = true
        reject(e)
      }
    })
  })
}

/**
 * 带指数退避重试（默认 2 次）：仅对"网络异常"或 5xx 重试，4xx 不重试
 * 用法：requestPromise({ url, ...opts, retry: 2, retryDelayMs: 400 })
 */
function requestPromise(options) {
  const retry = options.retry == null ? 2 : Number(options.retry) || 0
  const retryDelayMs = options.retryDelayMs == null ? 400 : Number(options.retryDelayMs) || 0
  let attempt = 0
  const run = () => requestPromiseOnce(options).catch((err) => {
    const retriable = err && (err.isNetworkError || (err.statusCode >= 500 && err.statusCode < 600))
    if (!retriable || attempt >= retry) {
      return Promise.reject(err)
    }
    attempt += 1
    const wait = retryDelayMs * Math.pow(2, attempt - 1)
    return new Promise((r) => setTimeout(r, wait)).then(run)
  })
  return run()
}

module.exports = {
  request,
  requestPromise,
  requestPromiseOnce,
  getToken,
  getApiBase,
  clearLoginState
}
