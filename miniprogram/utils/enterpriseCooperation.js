/**
 * 企业版合作模式弹框：仅在 enterprise 定价 + 企业上下文 + 非审核隐藏 + 三项完成且未选择时触发
 */

const { getEffectiveEnterpriseId } = require('./enterpriseContext.js')
const { shouldHideInviteCodeEntry } = require('./miniprogramAuditGate.js')

let flowLock = false

function shouldRunCooperationFlow(app) {
  const gd = app && app.globalData
  if (!gd) return false
  try {
    if (shouldHideInviteCodeEntry(gd)) return false
  } catch (e) {}
  return gd.appScope === 'enterprise' || getEffectiveEnterpriseId() != null
}

/**
 * @param {WechatMiniprogram.Page.TrivialInstance | null} page
 */
function maybeShowCooperationModal(page) {
  const app = getApp()
  if (!page || typeof page.setData !== 'function') return
  if (flowLock) return
  if (!app.ensureLogin) return

  app.ensureLogin().then((ok) => {
    if (!ok) return

    flowLock = true
    const unlock = () => {
      flowLock = false
    }

    Promise.resolve(app.getRuntimeConfig ? app.getRuntimeConfig() : null)
      .then((cfg) => {
        if (!cfg || cfg.pricingType !== 'enterprise') {
          unlock()
          return
        }
        if (!shouldRunCooperationFlow(app)) {
          unlock()
          return
        }

        try {
          const phoneAuth = require('./phoneAuth.js')
          if (!phoneAuth.isReportProfileComplete()) {
            wx.showToast({ title: '请先完善手机号与资料', icon: 'none' })
            setTimeout(() => {
              wx.navigateTo({
                url: '/pages/user-profile/index?from=cooperation_gate',
                fail: () => {}
              })
            }, 400)
            unlock()
            return
          }
        } catch (e) {
          unlock()
          return
        }

        const apiBase = (app.globalData && app.globalData.apiBase) || ''
        const token = wx.getStorageSync('token') || (app.globalData && app.globalData.token) || ''
        if (!apiBase || !token) {
          unlock()
          return
        }

        wx.request({
          url: `${apiBase.replace(/\/$/, '')}/api/user/cooperation-status`,
          method: 'GET',
          header: { Authorization: `Bearer ${token}` },
          complete: unlock,
          success: (res) => {
            const body = res.data || {}
            if (res.statusCode !== 200 || body.code !== 200 || !body.data) return
            const d = body.data

            const eid = d.enterpriseId != null ? Number(d.enterpriseId) : 0
            if (!eid) return

            if (d.chosen) return
            if (!d.allDone) return

            const modes = Array.isArray(d.modes) ? d.modes : []
            if (modes.length === 0) return

            page.setData({
              showCooperationModal: true,
              cooperationModes: modes
            })
          }
        })
      })
      .catch(unlock)
  })
}

module.exports = {
  shouldRunCooperationFlow,
  maybeShowCooperationModal
}
