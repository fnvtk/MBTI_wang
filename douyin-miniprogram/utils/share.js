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

/** 测试结果页分享：id + type + fs=1 + 邀请参数 */
function getResultSharePath(resultPagePath, opts) {
  const id = opts && opts.id
  const type = opts && opts.type
  if (!id || !type) {
    return getSharePathByScope('/pages/index/index')
  }
  const q =
    'id=' +
    encodeURIComponent(String(id)) +
    '&type=' +
    encodeURIComponent(String(type)) +
    '&fs=1'
  const inv = buildShareQuery()
  return inv ? resultPagePath + '?' + q + '&' + inv : resultPagePath + '?' + q
}

/** 单页模式 / 部分场景分享用 query：id + type + fs=1 + 邀请参数 */
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
