/**
 * 超管审核相关：隐藏神仙 AI Tab/入口；提审模式下另由后端/各页关闭虚拟商品与「了解自己」深度套餐。
 * - miniprogramAuditMode：提审专用（含虚拟支付合规）
 * - maintenanceMode / reviewMode：面相审核（用户口语「审核模式」常指其一）
 */

/** 临时：为 true 时在客户端忽略审核/提审隐藏逻辑。平时必须为 false，由 runtime 与后端 miniprogramAuditMode 等决定展示 */
const TEMP_FORCE_SHOW_ALL_HIDDEN_UI = false

/**
 * 在 getRuntimeConfig 写入 globalData 之后调用：把审核相关开关置为关闭，供各页与 TabBar 使用。
 */
function applyAuditUiOverride(app) {
  if (!TEMP_FORCE_SHOW_ALL_HIDDEN_UI || !app || !app.globalData) return
  app.globalData.reviewMode = false
  app.globalData.maintenanceMode = false
  app.globalData.miniprogramAuditMode = false
}

/** 是否因审核/面相权限隐藏中间凸起 Tab（拍摄） */
function shouldHideTabBarHighlightFab(gd) {
  if (TEMP_FORCE_SHOW_ALL_HIDDEN_UI) return false
  if (!gd) return false
  const ep = gd.enterprisePermissions
  const faceOff = !!(ep && ep.face === false)
  return !!(gd.reviewMode || gd.maintenanceMode) || faceOff
}

function isAuditHideAiMode(gd) {
  if (TEMP_FORCE_SHOW_ALL_HIDDEN_UI) return false
  if (!gd) return false
  return !!(gd.miniprogramAuditMode || gd.maintenanceMode || gd.reviewMode)
}

/**
 * 隐藏「填写邀请码 / 展示我的邀请码」等分销向入口（与「我的」页推广区策略一致：
 * reviewMode / maintenanceMode / miniprogramAuditMode 任一开启即隐藏）
 */
function shouldHideInviteCodeEntry(gd) {
  if (TEMP_FORCE_SHOW_ALL_HIDDEN_UI) return false
  if (!gd) return false
  return !!(gd.reviewMode || gd.maintenanceMode || gd.miniprogramAuditMode)
}

function redirectIfMiniprogramAudit(message) {
  const app = getApp()
  const gd = app && app.globalData
  if (!isAuditHideAiMode(gd)) return false
  wx.showToast({ title: message || '功能升级中', icon: 'none' })
  setTimeout(() => {
    wx.switchTab({ url: '/pages/index/index' })
  }, 300)
  return true
}

/**
 * 先拉 runtime（若存在），再若提审则踢回首页；否则执行 callback。
 */
function ensureRuntimeThenGate(callback) {
  const app = getApp()
  const run = () => {
    if (isAuditHideAiMode(app.globalData)) {
      redirectIfMiniprogramAudit()
      return
    }
    if (typeof callback === 'function') callback()
  }
  if (app && typeof app.getRuntimeConfig === 'function') {
    app.getRuntimeConfig().then(run).catch(run)
    return
  }
  run()
}

module.exports = {
  TEMP_FORCE_SHOW_ALL_HIDDEN_UI,
  applyAuditUiOverride,
  shouldHideTabBarHighlightFab,
  isAuditHideAiMode,
  shouldHideInviteCodeEntry,
  redirectIfMiniprogramAudit,
  ensureRuntimeThenGate
}
