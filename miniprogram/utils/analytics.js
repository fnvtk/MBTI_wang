/**
 * 小程序埋点：批量上报 /api/analytics/events（可选登录，便于超管后台关联用户）
 */
const { request } = require('./request.js')

const MAX_BATCH = 30
const queue = []
let lastPageReport = { path: '', t: 0 }

function getAppSafe() {
  try {
    return getApp()
  } catch (e) {
    return null
  }
}

function getCurrentRoute() {
  const pages = getCurrentPages()
  const p = pages[pages.length - 1]
  return p && p.route ? p.route : ''
}

function track(eventName, props) {
  if (!eventName || typeof eventName !== 'string') return
  const app = getAppSafe()
  const openId =
    app && app.globalData
      ? (app.globalData.openId || (app.globalData.userInfo && (app.globalData.userInfo.openid || app.globalData.userInfo.openId)) || '')
      : ''
  queue.push({
    event_name: eventName,
    page_path: getCurrentRoute(),
    props: props && typeof props === 'object' ? props : {},
    client_ts: Date.now(),
    openid: openId || undefined
  })
  if (queue.length >= MAX_BATCH) {
    flush()
  }
}

/** 页面曝光（防抖：同路径 2 秒内只记一次） */
function reportPageView() {
  const path = getCurrentRoute()
  if (!path) return
  const now = Date.now()
  if (path === lastPageReport.path && now - lastPageReport.t < 2000) {
    return
  }
  lastPageReport = { path, t: now }
  track('page_view', { path })
}

function flush() {
  if (queue.length === 0) return
  const events = queue.splice(0, queue.length)
  request({
    url: '/api/analytics/events',
    method: 'POST',
    needAuth: true,
    data: { events },
    allow401: false,
    success() {},
    fail() {}
  })
}

module.exports = {
  track,
  flush,
  reportPageView,
  getCurrentRoute
}
