/**
 * 分享参数工具
 * - uid：当前登录用户 ID（始终携带，用于邀请统计）
 * - eid：企业 ID（仅 appScope=enterprise 时携带）
 *
 * 小程序好友分享（onShareAppMessage）：path 带完整查询串
 * 朋友圈分享（onShareTimeline）：返回 query 字符串（不含 ?）
 * 接收方（落地页 onLoad options）直接读 options.uid / options.eid
 */

const { getEffectiveEnterpriseId } = require('./enterpriseContext.js')

function getApp_() {
  try { return getApp() } catch (e) { return null }
}

/**
 * 构建分享查询串，例如 "uid=1&eid=6" 或 "uid=1"
 */
function buildShareQuery() {
  try {
    const app = getApp_()
    const userInfo = (app && app.globalData && app.globalData.userInfo) || wx.getStorageSync('userInfo') || {}
    const scope = (app && app.globalData && app.globalData.appScope) || 'personal'
    const uid = userInfo.id || ''
    const parts = []
    if (uid) parts.push('uid=' + uid)
    if (scope === 'enterprise') {
      const eid = getEffectiveEnterpriseId() || ''
      if (eid) parts.push('eid=' + eid)
    }
    return parts.join('&')
  } catch (e) {
    return ''
  }
}

/**
 * 返回带查询参数的完整落地页路径
 * @param {string} basePath  例如 '/pages/index/index'
 * @param {boolean} forceEnterprise  强制使用企业版落地页（传 true 时 basePath 无效）
 */
function getSharePath(basePath) {
  const query = buildShareQuery()
  return query ? basePath + '?' + query : basePath
}

/**
 * 根据当前 scope 决定落地页路径
 * - 企业版 → /pages/enterprise/index?uid=X&eid=Y
 * - 个人版 → basePath?uid=X  （basePath 默认 /pages/index/index）
 */
function getSharePathByScope(personalBasePath) {
  const app = getApp_()
  const scope = (app && app.globalData && app.globalData.appScope) || 'personal'
  const base = scope === 'enterprise' ? '/pages/enterprise/index' : (personalBasePath || '/pages/index/index')
  return getSharePath(base)
}

/**
 * 分享「测试结果页」：仅需 id + type（好友免登录，由 /api/test/share-detail 按 type 校验）
 * @param {string} resultPagePath 如 /pages/result/mbti
 * @param {{ id: string|number, type: string }} opts
 */
function getResultSharePath(resultPagePath, opts) {
  const id = opts && opts.id
  const type = opts && opts.type
  if (!id || !type) {
    return getSharePathByScope('/pages/index/index')
  }
  // fs=1：分享落地，结果页用于区分访客（隐藏「去完善资料」、展示「我也要测试」）
  const q =
    'id=' +
    encodeURIComponent(String(id)) +
    '&type=' +
    encodeURIComponent(String(type)) +
    '&fs=1'
  const inv = buildShareQuery()
  return inv ? resultPagePath + '?' + q + '&' + inv : resultPagePath + '?' + q
}

/**
 * 朋友圈分享 query（不含 ?）
 */
function getResultShareTimelineQuery(opts) {
  const id = opts && opts.id
  const type = opts && opts.type
  if (!id || !type) {
    return buildShareQuery()
  }
  const base =
    'id=' +
    encodeURIComponent(String(id)) +
    '&type=' +
    encodeURIComponent(String(type)) +
    '&fs=1'
  const inv = buildShareQuery()
  return inv ? base + '&' + inv : base
}

module.exports = {
  buildShareQuery,
  getSharePath,
  getSharePathByScope,
  getResultSharePath,
  getResultShareTimelineQuery
}
