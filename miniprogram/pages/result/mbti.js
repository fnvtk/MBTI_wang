// pages/result/mbti.js - MBTI结果页（支持付费墙 + 历史详情拉取）
const app = getApp()
const payment = require('../../utils/payment')
const { hasPhone, bindPhoneByCode } = require('../../utils/phoneAuth.js')

Page({
  data: {
    result: null,
    dimensions: [],
    mbtiDesc: {
      title: '',
      description: '',
      strengths: [],
      weaknesses: [],
      careers: [],
      relationships: ''
    },
    payInfo: {
      requiresPayment: false,
      isPaid: false,
      amountYuan: 0
    },
    testResultId: null,
    hasReloadedAfterPay: false,
    hasPhone: false
  },

  onLoad(options) {
    const id = options && options.id
    const type = options && options.type

    if (id && type === 'mbti') {
      this.setData({ testResultId: id })
      this.loadDetail(id)
      return
    }

    const result = wx.getStorageSync('mbtiResult')
    if (result) {
      this.applyResult(result)
      this.initPayInfoFromRuntime('mbti')
    } else {
      wx.showToast({ title: '暂无测试结果', icon: 'none' })
      setTimeout(() => wx.navigateBack(), 1500)
    }
  },

  onShow() {
    this.setData({ hasPhone: hasPhone() })
  },

  loadDetail(id) {
    const apiBase = app.globalData?.apiBase || ''
    const token = app.globalData?.token || wx.getStorageSync('token') || ''
    if (!apiBase) {
      wx.showToast({ title: '配置异常', icon: 'none' })
      return
    }
    wx.showLoading({ title: '加载中...' })
    wx.request({
      url: `${apiBase}/api/test/detail`,
      method: 'GET',
      header: token ? { Authorization: `Bearer ${token}` } : {},
      data: { id },
      success: (res) => {
        if (res.statusCode === 200 && res.data && res.data.code === 200) {
          const payload = res.data.data || {}
          const data = payload.data || payload
          const isPaid = !!payload.isPaid
          const paidAmount = payload.paidAmount != null ? Number(payload.paidAmount) : 0
          const amountYuan = payload.amountYuan != null ? Number(payload.amountYuan) : (paidAmount > 0 ? paidAmount / 100 : 0)
          const needPaymentToUnlock = payload.needPaymentToUnlock === true || (!!payload.requiresPayment && !isPaid && paidAmount > 0)
          this.applyResult(data)
          const payInfo = {
            requiresPayment: needPaymentToUnlock,
            isPaid,
            amountYuan: needPaymentToUnlock ? amountYuan : 0
          }
          this.setData({ payInfo })
        } else {
          wx.showToast({ title: res.data?.message || '加载失败', icon: 'none' })
        }
      },
      fail: () => wx.showToast({ title: '网络错误', icon: 'none' }),
      complete: () => wx.hideLoading()
    })
  },

  applyResult(result) {
    if (!result) return
    const desc = result.description || {}
    const dimensions = (result.dimensionScores && !result.locked)
      ? [
          { key: 'EI', left: '外向(E)', right: '内向(I)', ...result.dimensionScores.EI },
          { key: 'SN', left: '感觉(S)', right: '直觉(N)', ...result.dimensionScores.SN },
          { key: 'TF', left: '思考(T)', right: '情感(F)', ...result.dimensionScores.TF },
          { key: 'JP', left: '判断(J)', right: '知觉(P)', ...result.dimensionScores.JP }
        ]
      : []
    this.setData({
      result,
      dimensions,
      mbtiDesc: {
        title: desc.name || '',
        description: desc.description || '',
        strengths: desc.strengths || [],
        weaknesses: desc.weaknesses || [],
        careers: desc.careers || [],
        relationships: desc.relationships || ''
      }
    })
  },

  initPayInfoFromRuntime(testType) {
    app.getRuntimeConfig()
      .then((cfg) => {
        const pricing = cfg.pricing || {}
        const reportRequires = cfg.reportRequiresPayment || {}
        const requiresPayment = !!(reportRequires && reportRequires[testType])
        const amountYuan = Number(pricing[testType]) || (requiresPayment ? 1.98 : 0)
        this.setData({
          payInfo: {
            requiresPayment,
            isPaid: false,
            amountYuan
          }
        })
      })
      .catch(() => {
        this.setData({
          payInfo: { requiresPayment: false, isPaid: false, amountYuan: 0 }
        })
      })
  },

  unlockFullReport() {
    const { payInfo, testResultId, hasReloadedAfterPay } = this.data
    if (!payInfo.requiresPayment || payInfo.isPaid) return
    app.ensureLogin && app.ensureLogin().then((logged) => {
      if (!logged) {
        wx.showToast({ title: '请先登录', icon: 'none' })
        return
      }
      payment.purchaseMbtiTest({
        testResultId: testResultId || undefined,
        success: () => {
          wx.showToast({ title: '已解锁完整报告', icon: 'success' })
          this.setData({ 'payInfo.isPaid': true })
          if (testResultId && !hasReloadedAfterPay) {
            this.setData({ hasReloadedAfterPay: true })
            setTimeout(() => this.loadDetail(testResultId), 500)
          }
        },
        fail: () => {}
      })
    })
  },

  // 付费解锁按钮：就地触发微信手机号授权，然后调用 unlockFullReport
  onGetPhoneNumberForMbtiPay(e) {
    const { code, errMsg } = e.detail || {}
    if (errMsg && errMsg.indexOf('getPhoneNumber:fail') === 0) {
      if (!hasPhone()) {
        wx.showToast({ title: '需要授权手机号才能继续', icon: 'none' })
        return
      }
      this.unlockFullReport()
      return
    }
    if (!code) {
      if (hasPhone()) {
        this.unlockFullReport()
      } else {
        wx.showToast({ title: '获取手机号失败', icon: 'none' })
      }
      return
    }
    bindPhoneByCode(code)
      .then(() => {
        this.setData({ hasPhone: true })
        this.unlockFullReport()
      })
      .catch(() => {
        // 保持在当前页，等待用户重新点击
      })
  },

  retakeTest() {
    if (!this.data.testResultId) {
      wx.removeStorageSync('mbtiResult')
    }
    wx.navigateTo({ url: '/pages/test/mbti' })
  },

  goHome() {
    const scope = (getApp().globalData && getApp().globalData.appScope) || 'personal'
    if (scope === 'enterprise') {
      wx.navigateTo({ url: '/pages/enterprise/index' })
    } else {
      wx.switchTab({ url: '/pages/index/index' })
    }
  },

  onShareAppMessage() {
    const result = this.data.result
    const { getSharePathByScope } = require('../../utils/share')
    return {
      title: `我的MBTI类型是${result?.mbtiType}（${result?.description?.name}），来测测你的吧！`,
      path: getSharePathByScope('/pages/index/index'),
      imageUrl: '/images/share-mbti.png'
    }
  },

  onShareTimeline() {
    const result = this.data.result
    const { buildShareQuery } = require('../../utils/share')
    return {
      title: `我的MBTI类型是${result?.mbtiType}（${result?.description?.name}），来测测你的吧！`,
      query: buildShareQuery()
    }
  }
})
