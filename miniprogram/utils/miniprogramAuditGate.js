/**
 * 超管审核相关：隐藏神仙 AI Tab/入口；提审模式下另由后端/各页关闭虚拟商品与「了解自己」深度套餐。
 * - miniprogramAuditMode：提审专用（含虚拟支付合规）
 * - maintenanceMode / reviewMode：面相审核（用户口语「审核模式」常指其一）
 */

function isAuditHideAiMode(gd) {
  if (!gd) return false
  return !!(gd.miniprogramAuditMode || gd.maintenanceMode || gd.reviewMode)
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
  isAuditHideAiMode,
  redirectIfMiniprogramAudit,
  ensureRuntimeThenGate
}
