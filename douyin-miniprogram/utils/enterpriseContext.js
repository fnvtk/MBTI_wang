/**
 * 抖音小程序企业上下文（与微信 miniprogram 逻辑对齐，storage 用 tt）
 */

function getEffectiveEnterpriseId() {
  const app = getApp()
  const gd = app.globalData || {}
  const fromScene = gd.enterpriseIdFromScene
  if (fromScene != null && Number(fromScene) > 0) {
    return Number(fromScene)
  }
  const u = gd.userInfo || tt.getStorageSync('userInfo') || {}
  const bound = u.enterpriseId
  if (bound != null && Number(bound) > 0) {
    return Number(bound)
  }
  const def = gd.defaultEnterpriseId
  if (def != null && Number(def) > 0) {
    return Number(def)
  }
  return null
}

/**
 * 与微信 miniprogram/utils/enterpriseContext.getEnterpriseIdForApiPayload 一致。
 * 个人版无 scene 时返回 null；后端 submit / analyze 会用绑定企业或系统默认企业落库。
 */
function getEnterpriseIdForApiPayload() {
  const app = getApp()
  const gd = app.globalData || {}
  const scope = gd.appScope || 'personal'
  if (scope === 'personal') {
    const fromScene = gd.enterpriseIdFromScene
    if (fromScene != null && Number(fromScene) > 0) {
      return Number(fromScene)
    }
    return null
  }
  return getEffectiveEnterpriseId()
}

module.exports = {
  getEffectiveEnterpriseId,
  getEnterpriseIdForApiPayload
}
