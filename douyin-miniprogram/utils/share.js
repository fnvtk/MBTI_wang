/**
 * 分享参数工具（抖音版）
 * 抖音小程序支持 onShareAppMessage 分享给好友
 * 注意：抖音没有朋友圈分享（onShareTimeline）
 */

function getApp_() {
  try { return getApp() } catch (e) { return null }
}

function buildShareQuery() {
  try {
    const app = getApp_()
    const userInfo = (app && app.globalData && app.globalData.userInfo) || tt.getStorageSync('userInfo') || {}
    const scope = (app && app.globalData && app.globalData.appScope) || 'personal'
    const uid = userInfo.id || ''
    const parts = []
    if (uid) parts.push('uid=' + uid)
    if (scope === 'enterprise') {
      const eid = (app && app.globalData && app.globalData.enterpriseIdFromScene)
        || userInfo.enterpriseId
        || ''
      if (eid) parts.push('eid=' + eid)
    }
    return parts.join('&')
  } catch (e) {
    return ''
  }
}

function getSharePath(basePath) {
  const query = buildShareQuery()
  return query ? basePath + '?' + query : basePath
}

function getSharePathByScope(personalBasePath) {
  const app = getApp_()
  const scope = (app && app.globalData && app.globalData.appScope) || 'personal'
  const base = scope === 'enterprise' ? '/pages/enterprise/index' : (personalBasePath || '/pages/index/index')
  return getSharePath(base)
}

module.exports = { buildShareQuery, getSharePath, getSharePathByScope }
