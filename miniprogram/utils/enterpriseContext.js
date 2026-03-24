/**
 * 小程序企业上下文：显式入口参数 > 用户绑定企业 > 超管配置的默认企业
 */

/**
 * 企业版落地页、分享带 eid、充值等：完整回落链
 * @returns {number|null}
 */
function getEffectiveEnterpriseId() {
  const app = getApp()
  const gd = app.globalData || {}
  const fromScene = gd.enterpriseIdFromScene
  if (fromScene != null && Number(fromScene) > 0) {
    return Number(fromScene)
  }
  const u = gd.userInfo || wx.getStorageSync('userInfo') || {}
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
 * 提交给后端的 enterpriseId（analyze、test/submit、支付创建订单、简历分析等）
 * - 个人版 Tab（appScope=personal）：仅传递「本次入口携带的 eid」（enterpriseIdFromScene），
 *   不因「已绑定企业」或「超管默认企业」带参，避免个人入口却走企业定价/落库企业字段。
 * - 企业版（appScope=enterprise）：与 getEffectiveEnterpriseId 一致。
 * @returns {number|null}
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
