/**
 * 小程序埋点：批量上报 /api/analytics/events
 * - session_id 区分每次启动
 * - 自动采集平台、网络、设备、场景值
 * - 定时 flush + 页面隐藏 flush
 */
const { request } = require('./request.js')

const MAX_BATCH = 30
const FLUSH_INTERVAL = 10000
const queue = []
let lastPageReport = { path: '', t: 0 }

const sessionId = 'wx_' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6)

let _deviceInfo = null
function getDeviceInfo() {
  if (_deviceInfo) return _deviceInfo
  try {
    const sys = wx.getSystemInfoSync()
    _deviceInfo = {
      brand: sys.brand,
      model: sys.model,
      system: sys.system,
      platform: sys.platform,
      version: sys.version,
      SDKVersion: sys.SDKVersion,
      screenWidth: sys.screenWidth,
      screenHeight: sys.screenHeight,
      pixelRatio: sys.pixelRatio
    }
  } catch (e) {
    _deviceInfo = {}
  }
  return _deviceInfo
}

let _networkType = 'unknown'
function refreshNetwork() {
  try {
    wx.getNetworkType({
      success(res) { _networkType = res.networkType || 'unknown' }
    })
  } catch (e) {}
}
refreshNetwork()

function getAppSafe() {
  try { return getApp() } catch (e) { return null }
}

function getCurrentRoute() {
  const pages = getCurrentPages()
  const p = pages[pages.length - 1]
  return p && p.route ? p.route : ''
}

function getScene() {
  const app = getAppSafe()
  return (app && app.globalData && app.globalData.scene) || ''
}

function track(eventName, props) {
  if (!eventName || typeof eventName !== 'string') return
  const app = getAppSafe()
  const openId = app && app.globalData
    ? (app.globalData.openId || (app.globalData.userInfo && (app.globalData.userInfo.openid || app.globalData.userInfo.openId)) || '')
    : ''
  queue.push({
    event_name: eventName,
    page_path: getCurrentRoute(),
    props: props && typeof props === 'object' ? props : {},
    client_ts: Date.now(),
    openid: openId || undefined,
    session_id: sessionId,
    platform: 'wechat',
    network: _networkType,
    scene: getScene()
  })
  if (queue.length >= MAX_BATCH) {
    flush()
  }
}

/** 页面曝光（防抖：同路径 2 秒内只记一次） */
function reportPageView(extraProps) {
  const path = getCurrentRoute()
  if (!path) return
  const now = Date.now()
  if (path === lastPageReport.path && now - lastPageReport.t < 2000) return
  lastPageReport = { path, t: now }
  track('page_view', { path, ...(extraProps || {}) })
}

function flush() {
  if (queue.length === 0) return
  const events = queue.splice(0, queue.length)
  request({
    url: '/api/analytics/events',
    method: 'POST',
    needAuth: true,
    data: { events, device: getDeviceInfo() },
    allow401: false,
    success() {},
    fail() {}
  })
}

/** 上报应用启动（首次 onLaunch 调用一次） */
function reportAppLaunch(launchOptions) {
  const opts = launchOptions || {}
  track('app_launch', {
    scene: opts.scene,
    query: opts.query || {},
    referrerInfo: opts.referrerInfo || {},
    device: getDeviceInfo()
  })
}

/** 上报分享行为 */
function reportShare(channel, extra) {
  track('share', { channel: channel || 'friend', ...(extra || {}) })
  flush()
}

/** 上报支付结果 */
function reportPayResult(success, extra) {
  track(success ? 'pay_success' : 'pay_fail', extra || {})
  flush()
}

let _flushTimer = null
function startAutoFlush() {
  if (_flushTimer) return
  _flushTimer = setInterval(flush, FLUSH_INTERVAL)
}
function stopAutoFlush() {
  if (_flushTimer) {
    clearInterval(_flushTimer)
    _flushTimer = null
  }
}
startAutoFlush()

module.exports = {
  track,
  flush,
  reportPageView,
  reportAppLaunch,
  reportShare,
  reportPayResult,
  getCurrentRoute,
  startAutoFlush,
  stopAutoFlush
}
