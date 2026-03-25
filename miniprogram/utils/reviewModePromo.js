/**
 * 推广/分销相关子页：拉取最新 runtime 后若为审核模式则回「我的」，不执行后续逻辑
 */
function getAppSafe() {
  return getApp()
}

function afterReviewModeChecked(run) {
  const app = getAppSafe()
  app
    .getRuntimeConfig()
    .then((cfg) => {
      if (cfg && cfg.reviewMode !== undefined) {
        app.globalData.reviewMode = !!cfg.reviewMode
        if (app.globalData.reviewMode && typeof app._clearPendingDistributionBind === 'function') {
          app._clearPendingDistributionBind()
        }
      }
    })
    .catch(() => {})
    .then(() => {
      if (app.globalData.reviewMode) {
        wx.switchTab({ url: '/pages/profile/index' })
        return
      }
      if (typeof run === 'function') run()
    })
}

module.exports = { afterReviewModeChecked }
