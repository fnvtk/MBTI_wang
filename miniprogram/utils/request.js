/**
 * 统一请求封装：自动拼接 baseURL、携带 token、处理 401
 * 401 时先尝试静默登录一次并重试，减少 token 过期/首屏竞态导致的误 401
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
  const g = app && app.globalData && app.globalData.token
  if (g) return g
  try {
    const st = wx.getStorageSync('token')
    if (st && app && app.globalData) {
      app.globalData.token = st
    }
    return st || ''
  } catch (e) {
    return ''
  }
}

function getApiBase() {
  const app = getAppSafe()
  return (app && app.globalData && app.globalData.apiBase) || ''
}

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
 * @param {Object} options - 同 wx.request
 * @param {boolean} options.needAuth - 是否带 Authorization，默认 true
 * @param {boolean} options.optionalAuth
 * @param {boolean} options.allow401 - 401 且已带 Bearer 时是否清登录态（重试失败后），默认 true
 */
function request(options) {
  const apiBase = getApiBase()
  const {
    __didAuthRetry,
    success: userSuccess,
    fail: userFail,
    complete: userComplete,
    url: optUrl,
    ...rest
  } = options

  const fullUrl = optUrl.startsWith('http')
    ? optUrl
    : `${apiBase.replace(/\/$/, '')}${optUrl.startsWith('/') ? '' : '/'}${optUrl}`
  const needAuth = options.needAuth !== false
  const optionalAuth = options.optionalAuth === true
  const allow401 = options.allow401 !== false
  const didRetry = __didAuthRetry === true

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

  wx.request({
    ...rest,
    url: fullUrl,
    header,
    success(res) {
      if (
        res.statusCode === 401 &&
        allow401 &&
        authHeaderSent &&
        needAuth &&
        !didRetry
      ) {
        const app = getAppSafe()
        if (app && typeof app.silentLogin === 'function') {
          app
            .silentLogin()
            .then((loginOk) => {
              if (loginOk && getToken()) {
                request({ ...options, __didAuthRetry: true })
              } else {
                clearLoginState()
                if (userSuccess) userSuccess(res)
              }
            })
            .catch(() => {
              clearLoginState()
              if (userSuccess) userSuccess(res)
            })
          return
        }
      }
      if (res.statusCode === 401 && allow401 && authHeaderSent) {
        clearLoginState()
      }
      if (userSuccess) userSuccess(res)
    },
    fail(err) {
      if (userFail) userFail(err)
    },
    complete(res) {
      if (userComplete) userComplete(res)
    }
  })
}

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
        const e = new Error((err && err.errMsg) || '网络异常')
        e.isNetworkError = true
        reject(e)
      }
    })
  })
}

function requestPromise(options) {
  const retry = options.retry == null ? 2 : Number(options.retry) || 0
  const retryDelayMs = options.retryDelayMs == null ? 400 : Number(options.retryDelayMs) || 0
  let attempt = 0
  const run = () =>
    requestPromiseOnce(options).catch((err) => {
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
