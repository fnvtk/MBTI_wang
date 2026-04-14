// pages/result/sbti.js — SBTI 结果页
const app = getApp()
const payment = require('../../utils/payment')
const { hasPhone, bindPhoneByCode, isReportProfileComplete } = require('../../utils/phoneAuth.js')
const { TYPE_IMAGES } = require('../../utils/sbtiEngine.js')

/** 根据结果类型代码取展示图（与 sbtiData.TYPE_IMAGES 一致） */
/** 旧版结果仅有 badge / bestNormal，补全 matchPercent、hitDimCount */
function normalizeSbtiResultForDisplay(result) {
  if (!result || typeof result !== 'object') return result
  const out = { ...result }
  const bn = out.bestNormal
  if (out.matchPercent == null && bn != null && typeof bn.similarity === 'number') {
    out.matchPercent = bn.similarity
  }
  if (out.hitDimCount == null && bn != null && typeof bn.exact === 'number') {
    out.hitDimCount = bn.exact
  }
  if (out.special && out.sbtiType === 'DRUNK') {
    if (out.matchPercent == null) out.matchPercent = 100
    if (out.hitDimCount == null) out.hitDimCount = 15
  }
  return out
}

function resolveSbtiTypeImageUrl(result) {
  if (!result || typeof result !== 'object') return ''
  const code = result.sbtiType || (result.finalType && result.finalType.code) || ''
  if (!code || !TYPE_IMAGES) return ''
  const url = TYPE_IMAGES[code]
  return typeof url === 'string' ? url : ''
}

function toProfileLockedSbti(full) {
  if (!full) return full
  const code = full.sbtiType || full.finalType?.code || ''
  return { sbtiType: code, sbtiCn: full.sbtiCn || full.finalType?.cn || '', locked: true }
}

Page({
  data: {
    result: null,
    typeImageUrl: '',
    /** 为 true 时展示顶部文字备用（无图地址或图片加载失败） */
    typeImageLoadFailed: false,
    /** 类型图是否已成功加载（有 URL 且 bindload 触发后为 true，用于隐藏与图重复的顶部三行字） */
    typeImageLoaded: false,
    dimExplainList: [],
    payInfo: {
      requiresPayment: false,
      isPaid: false,
      amountYuan: 0
    },
    testResultId: null,
    shareToken: '',
    hasReloadedAfterPay: false,
    hasPhone: false
  },

  onLoad(options) {
    const id = options && options.id
    const type = options && options.type

    if (id && type === 'sbti') {
      this.setData({ testResultId: id })
      if (options.st) {
        this.loadShareDetail(id, options.st)
      } else {
        this.loadDetail(id)
      }
      return
    }

    const raw = wx.getStorageSync('sbtiResult')
    if (raw) {
      const result = isReportProfileComplete() ? raw : toProfileLockedSbti(raw)
      this.applyResult(result)
      this.initPayInfoFromRuntime('sbti')
    } else {
      wx.showToast({ title: '暂无测试结果', icon: 'none' })
      setTimeout(() => wx.navigateBack(), 1500)
    }
  },

  onShow() {
    this.setData({ hasPhone: hasPhone() })
    if (this.data.testResultId) return
    const raw = wx.getStorageSync('sbtiResult')
    if (raw) {
      const result = isReportProfileComplete() ? raw : toProfileLockedSbti(raw)
      this.applyResult(result)
    }
  },

  goCompleteProfile() {
    wx.navigateTo({ url: '/pages/user-profile/index' })
  },

  applyDetailPayload(payload) {
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
    this.setData({
      payInfo,
      shareToken: payload.shareToken || ''
    })
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
          this.applyDetailPayload(res.data.data || {})
        } else {
          wx.showToast({ title: res.data?.message || '加载失败', icon: 'none' })
        }
      },
      fail: () => wx.showToast({ title: '网络错误', icon: 'none' }),
      complete: () => wx.hideLoading()
    })
  },

  loadShareDetail(id, st) {
    const apiBase = app.globalData?.apiBase || ''
    if (!apiBase) {
      wx.showToast({ title: '配置异常', icon: 'none' })
      return
    }
    wx.showLoading({ title: '加载中...' })
    wx.request({
      url: `${apiBase}/api/test/share-detail`,
      method: 'GET',
      data: { id, st },
      success: (res) => {
        if (res.statusCode === 200 && res.data && res.data.code === 200) {
          this.applyDetailPayload(res.data.data || {})
        } else {
          wx.showToast({ title: res.data?.message || '分享链接无效或已失效', icon: 'none' })
        }
      },
      fail: () => wx.showToast({ title: '网络错误', icon: 'none' }),
      complete: () => wx.hideLoading()
    })
  },

  applyResult(result) {
    if (!result) return
    const normalized = normalizeSbtiResultForDisplay(result)
    const dimExplainList = (!normalized.locked && normalized.dimExplainList) ? normalized.dimExplainList : []
    const typeImageUrl = resolveSbtiTypeImageUrl(normalized)
    this.setData({
      result: normalized,
      dimExplainList,
      typeImageUrl,
      typeImageLoadFailed: false,
      typeImageLoaded: false
    })
  },

  onTypeImageLoad() {
    this.setData({ typeImageLoadFailed: false, typeImageLoaded: true })
  },

  onTypeImageError() {
    this.setData({ typeImageLoadFailed: true, typeImageLoaded: false })
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
      payment.purchaseSbtiTest({
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

  onGetPhoneNumberForSbtiPay(e) {
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
      .catch(() => {})
  },

  retakeTest() {
    if (!this.data.testResultId) {
      wx.removeStorageSync('sbtiResult')
    }
    wx.navigateTo({ url: '/pages/test/sbti' })
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
    const { getResultSharePath } = require('../../utils/share')
    const label = result?.sbtiCn || result?.finalType?.cn || 'SBTI'
    const code = result?.sbtiType || result?.finalType?.code || ''
    const img = this.data.typeImageUrl || '/images/share-mbti.png'
    return {
      title: `我的 SBTI 类型是 ${code}（${label}），来测测你的吧！`,
      path: getResultSharePath('/pages/result/sbti', {
        id: this.data.testResultId,
        type: 'sbti',
        shareToken: this.data.shareToken
      }),
      imageUrl: img
    }
  },

  onShareTimeline() {
    const result = this.data.result
    const { getResultShareTimelineQuery } = require('../../utils/share')
    const label = result?.sbtiCn || result?.finalType?.cn || 'SBTI'
    const code = result?.sbtiType || result?.finalType?.code || ''
    return {
      title: `我的 SBTI 类型是 ${code}（${label}），来测测你的吧！`,
      query: getResultShareTimelineQuery({
        id: this.data.testResultId,
        type: 'sbti',
        shareToken: this.data.shareToken
      })
    }
  }
})
