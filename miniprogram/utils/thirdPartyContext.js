/**
 * 第三方渠道参数：query / scene 中的 userid、phone、tid（与分销 uid 分流）
 * 仅存 globalData，登录时透传 thirdParty
 */

function safeDecode(v) {
  if (v == null || v === '') return ''
  const s = String(v).trim()
  if (!s) return ''
  try {
    return decodeURIComponent(s)
  } catch (e) {
    return s
  }
}

function ensureChannel(app) {
  if (!app.globalData.thirdPartyChannel) {
    app.globalData.thirdPartyChannel = {
      userid: '',
      phone: '',
      tid: '',
      capturedAt: 0,
    }
  }
  return app.globalData.thirdPartyChannel
}

/**
 * 从 query 对象合并（同字段以首次非空为准）
 */
function mergeThirdPartyFromQuery(q) {
  if (!q || typeof q !== 'object') return false
  const app = getApp()
  const ch = ensureChannel(app)
  let changed = false
  const touch = (key, raw) => {
    const v = safeDecode(raw)
    if (!v) return
    if (!ch[key]) {
      ch[key] = v
      changed = true
    }
  }
  touch('userid', q.userid)
  touch('phone', q.phone)
  touch('tid', q.tid)
  if (changed) ch.capturedAt = Date.now()
  return changed
}

/** App.onLaunch 使用 */
function ingestThirdPartyFromLaunch(launchOptions) {
  const q = (launchOptions && launchOptions.query) || {}
  mergeThirdPartyFromQuery(q)
}

/**
 * 页面 onLoad：合并 options + 已解码的 sceneParams
 * @returns {boolean} 是否有新字段写入（便于触发二次 silentLogin）
 */
function mergeFromPageOptions(options, sceneParams) {
  const o = {}
  if (options && typeof options === 'object') {
    if (options.userid != null) o.userid = options.userid
    if (options.phone != null) o.phone = options.phone
    if (options.tid != null) o.tid = options.tid
  }
  const c1 = mergeThirdPartyFromQuery(o)
  const c2 = mergeThirdPartyFromQuery(sceneParams || {})
  return !!(c1 || c2)
}

/**
 * 任意可作为外链入口的页面 onLoad 首行调用：解析 options.query + options.scene，合并第三方参数并视情况 silentLogin
 * @param {object} [options] Page.onLoad 第一个参数
 * @param {object} [appInst] App 实例，默认 getApp()
 */
function ingestThirdPartyOnPageLoad(options, appInst) {
  options = options || {}
  let rawScene = ''
  try {
    rawScene = options.scene ? decodeURIComponent(String(options.scene)) : ''
  } catch (e) {
    rawScene = options.scene ? String(options.scene) : ''
  }
  const sceneParams = {}
  if (rawScene) {
    rawScene.split('&').forEach((pair) => {
      const [k, v] = pair.split('=')
      if (k) sceneParams[k] = v || ''
    })
  }
  const changed = mergeFromPageOptions(options, sceneParams)
  const app = appInst || getApp()
  if (changed && app && typeof app.silentLogin === 'function') {
    app.silentLogin().catch(() => {})
  }
}

/** 登录 POST 体片段 */
function getThirdPartyForLoginBody() {
  try {
    const app = getApp()
    const ch = app.globalData.thirdPartyChannel || {}
    const userid = String(ch.userid || '').trim()
    const phone = String(ch.phone || '').trim()
    const tid = String(ch.tid || '').trim()
    if (!userid && !phone && !tid) return {}
    const thirdParty = {}
    if (userid) thirdParty.userid = userid
    if (phone) thirdParty.phone = phone
    if (tid) thirdParty.tid = tid
    return { thirdParty }
  } catch (e) {
    return {}
  }
}

module.exports = {
  ingestThirdPartyFromLaunch,
  ingestThirdPartyOnPageLoad,
  mergeFromPageOptions,
  mergeThirdPartyFromQuery,
  getThirdPartyForLoginBody,
}
