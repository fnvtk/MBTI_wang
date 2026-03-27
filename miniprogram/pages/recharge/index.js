const app = getApp()
const payment = require('../../utils/payment')
const { request } = require('../../utils/request')
const { getEffectiveEnterpriseId } = require('../../utils/enterpriseContext.js')
let analyticsMod = null
try {
  analyticsMod = require('../../utils/analytics')
} catch (e) {}

Page({
  data: {
    enterpriseId: 0,
    enterpriseName: '',
    amountFen: 0,
    amountYuan: '0.00',
    paying: false
  },

  onLoad(options) {
    const rawScene = options && options.scene ? decodeURIComponent(options.scene) : ''
    const sceneParams = {}
    if (rawScene) {
      rawScene.split('&').forEach(pair => {
        const [k, v] = pair.split('=')
        if (k) sceneParams[k] = v || ''
      })
    }

    const enterpriseId = parseInt(sceneParams.eid || options.eid || 0, 10) || 0
    const amountFen = parseInt(sceneParams.a || options.amountFen || 0, 10) || 0
    const amountYuan = (amountFen / 100).toFixed(2)

    if (enterpriseId > 0) {
      app.globalData.enterpriseIdFromScene = enterpriseId
    }

    const resolveEnterpriseId = () => {
      if (enterpriseId > 0) return enterpriseId
      return getEffectiveEnterpriseId() || 0
    }

    this.setData({
      enterpriseId: resolveEnterpriseId(),
      enterpriseName: (app.globalData.userInfo && app.globalData.userInfo.enterpriseName) || '',
      amountFen,
      amountYuan
    })

    app.ensureLogin()
      .then((ok) => {
        if (!ok) {
          wx.showToast({ title: '请先登录', icon: 'none' })
          return Promise.reject(new Error('no login'))
        }
        return app.getRuntimeConfig().catch(() => {})
      })
      .then(() => {
        const finalEid = resolveEnterpriseId()
        if (finalEid > 0 && enterpriseId <= 0) {
          this.setData({ enterpriseId: finalEid })
        }
        if (enterpriseId > 0) {
          request({
            url: '/api/enterprise/bind',
            method: 'POST',
            data: { enterpriseId },
            success: (res) => {
              const payload = res && res.data && res.data.data ? res.data.data : {}
              const enterpriseName = payload.enterpriseName || this.data.enterpriseName || ''
              this.setData({ enterpriseName })
            },
            fail: () => {}
          })
        }
      })
      .catch(() => {})
  },

  submitRecharge() {
    if (this.data.paying) return
    if (!this.data.enterpriseId || !this.data.amountFen) {
      wx.showToast({ title: '充值参数无效', icon: 'none' })
      return
    }

    this.setData({ paying: true })
    if (analyticsMod && typeof analyticsMod.track === 'function') {
      analyticsMod.track('click_recharge', {
        action: '充值页确认充值',
        enterpriseId: this.data.enterpriseId,
        amountFen: this.data.amountFen
      })
      if (typeof analyticsMod.flush === 'function') {
        analyticsMod.flush()
      }
    }
    payment.recharge({
      amountYuan: Number(this.data.amountYuan),
      enterpriseId: this.data.enterpriseId,
      success: () => {
        this.setData({ paying: false })
        wx.showToast({ title: '充值成功', icon: 'success' })
        setTimeout(() => {
          wx.navigateTo({ url: `/pages/enterprise/index?eid=${this.data.enterpriseId}` })
        }, 1200)
      },
      fail: () => {
        this.setData({ paying: false })
      }
    })
  }
})
