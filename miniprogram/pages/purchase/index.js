// pages/purchase/index.js - 开通会员（深度服务价格：个人/企业区分，类目由后端配置可新增）
const app = getApp()
const payment = require('../../utils/payment')
const { hasPhone, bindPhoneByCode } = require('../../utils/phoneAuth.js')

Page({
  data: {
    activeTab: 'personal',
    personalCategories: [],
    enterpriseCategories: [],
    loading: true,
    loadError: false,
    loadErrorMsg: '',
    purchasing: false,
    hasPhone: false,
    successModal: {
      visible: false,
      title: '',
      content: ''
    }
  },

  retryLoad() {
    this.loadDeepPricing()
  },

  onLoad(options) {
    const tab = (options && options.tab === 'enterprise') ? 'enterprise' : 'personal'
    this.setData({ activeTab: tab })
    wx.setNavigationBarTitle({ title: '了解自己' })
    this.loadDeepPricing()
  },

  onShow() {
    // 不在此页强制跳转资料页：避免与付费按钮上的手机号授权打架导致死循环；未绑手机由按钮 open-type 引导
    this.setData({ hasPhone: hasPhone() })
  },

  loadDeepPricing() {
    const apiBase = app.globalData.apiBase || ''
    if (!apiBase) {
      this.setData({ loading: false, loadError: true, loadErrorMsg: '服务地址未配置' })
      return
    }
    this.setData({ loading: true, loadError: false, loadErrorMsg: '' })
    Promise.all([
      this.requestDeepPricing('personal'),
      this.requestDeepPricing('enterprise')
    ]).then(([personal, enterprise]) => {
      const pErr = personal && personal.__error
      const eErr = enterprise && enterprise.__error
      const pList = Array.isArray(personal) ? personal : []
      const eList = Array.isArray(enterprise) ? enterprise : []
      const bothFailed = pErr && eErr
      const bothEmpty = !pList.length && !eList.length
      if (bothFailed || bothEmpty) {
        const msg = pErr ? (personal.__errorMsg || '网络异常') : (eErr ? (enterprise.__errorMsg || '网络异常') : '暂无可购买方案')
        this.setData({
          personalCategories: [],
          enterpriseCategories: [],
          loading: false,
          loadError: true,
          loadErrorMsg: msg
        })
        return
      }
      this.setData({
        personalCategories: pList,
        enterpriseCategories: eList,
        loading: false,
        loadError: false,
        loadErrorMsg: ''
      })
    }).catch((err) => {
      this.setData({
        loading: false,
        loadError: true,
        loadErrorMsg: (err && err.message) || '加载失败，请检查网络后重试'
      })
    })
  },

  requestDeepPricing(scope) {
    return new Promise((resolve) => {
      wx.request({
        url: `${app.globalData.apiBase.replace(/\/$/, '')}/api/config/deep-pricing`,
        method: 'GET',
        data: { scope },
        timeout: 15000,
        success: (res) => {
          if (res.statusCode === 200 && res.data && res.data.code === 200 && Array.isArray(res.data.data && res.data.data.categories)) {
            resolve(res.data.data.categories)
          } else {
            resolve({ __error: true, __errorMsg: (res && res.data && res.data.message) || '响应异常' })
          }
        },
        fail: (err) => resolve({ __error: true, __errorMsg: (err && err.errMsg) || '网络异常' })
      })
    })
  },

  switchTab(e) {
    const tab = e.currentTarget.dataset.tab
    if (tab !== 'personal' && tab !== 'enterprise') return
    this.setData({ activeTab: tab })
    wx.setNavigationBarTitle({ title: '了解自己' })
  },

  // 无需再次授权时，直接点击按钮执行购买/咨询
  handlePurchaseTap(e) {
    const tab = e.currentTarget.dataset.tab
    const index = e.currentTarget.dataset.index
    this.handlePurchase(tab, index)
  },

  // 实际执行购买/咨询逻辑（手机号由外层 getPhoneNumber 或 hasPhone 分支保证）
  handlePurchase(tab, index) {
    if (index === undefined || index === null) return
    const list = tab === 'enterprise' ? this.data.enterpriseCategories : this.data.personalCategories
    const category = list[index]
    if (!category) return

    if (category.actionType === 'buy' && category.productKey) {
      this.purchasePersonal(category)
    } else {
      this.applyConsult(category)
    }
  },

  // 购买/企业咨询按钮：就地触发微信系统手机号授权，然后执行 handlePurchase
  onGetPhoneNumberForPurchase(e) {
    const tab = e.currentTarget.dataset.tab
    const index = e.currentTarget.dataset.index
    const { code, errMsg } = e.detail || {}

    if (errMsg && errMsg.indexOf('getPhoneNumber:fail') === 0) {
      if (!hasPhone()) {
        wx.showToast({ title: '需要授权手机号才能继续', icon: 'none' })
        return
      }
      // 用户拒绝但之前已授权过，本地已有手机号，则直接继续
      this.handlePurchase(tab, index)
      return
    }

    if (!code) {
      if (hasPhone()) {
        this.handlePurchase(tab, index)
      } else {
        wx.showToast({ title: '获取手机号失败', icon: 'none' })
      }
      return
    }

    bindPhoneByCode(code)
      .then(() => {
        this.setData({ hasPhone: true })
        this.handlePurchase(tab, index)
      })
      .catch(() => {
        // 失败时只提示，不阻塞后续再次点击
      })
  },

  purchasePersonal(category) {
    if (this.data.purchasing) return
    this.setData({ purchasing: true })
    wx.showLoading({ title: '处理中...', mask: true })
    const deepProductId = category.id || category.productKey || ''
    const title = category.title || '个人深度服务（1v1深度解读）'
    payment.purchasePersonalDeepService({
      deepProductId,
      description: title,
      success: () => {
        wx.hideLoading()
        this.setData({ purchasing: false })
        this._reportCrmLead(category, 'buy')
        const successMsg = (category.successMessage || '购买成功！我们的顾问会尽快与您联系，为您提供专属深度解读服务。').trim()
        this._showSuccessModal('购买成功', successMsg)
      },
      fail: () => {
        wx.hideLoading()
        this.setData({ purchasing: false })
      }
    })
  },

  applyConsult(category) {
    const successMsg = (category.successMessage || '感谢您的申请，我们的顾问会尽快与您联系！').trim()
    wx.showLoading({ title: '提交中...', mask: true })
    const done = () => {
      wx.hideLoading()
      this._showSuccessModal('申请成功', successMsg)
    }
    this._reportCrmLead(category, 'consult', done)
  },

  _showSuccessModal(title, content) {
    this.setData({
      successModal: {
        visible: true,
        title: title || '成功',
        content: content || ''
      }
    })
  },

  catchTap() {},

  closeSuccessModal() {
    this.setData({ 'successModal.visible': false })
  },

  /**
   * 向后端上报存客宝线索，后端负责签名和调用存客宝 API
   * @param {Object} category  深度服务类目对象（consultWechat 为存客宝 KEY，可空由后端按企业配置回落）
   * @param {string} actionType  'buy'（付款完成）| 'consult'（申请咨询）
   * @param {Function} [onDone]  请求结束回调（含失败）
   */
  _reportCrmLead(category, actionType, onDone) {
    const apiKey = (category.consultWechat || '').trim()
    const apiBase = app.globalData.apiBase || ''
    if (actionType === 'buy' && !apiKey) {
      if (typeof onDone === 'function') onDone()
      return
    }
    if (!apiBase) {
      if (typeof onDone === 'function') onDone()
      return
    }

    const isEnterprise = this.data.activeTab === 'enterprise'
    const source = (isEnterprise ? '企业深度服务' : '个人深度服务') + (category.title ? `-${category.title}` : '')
    const remark = actionType === 'buy' ? '完成付款' : '申请咨询'

    wx.request({
      url: `${apiBase.replace(/\/$/, '')}/api/crm/report`,
      method: 'POST',
      header: {
        Authorization: `Bearer ${wx.getStorageSync('token') || ''}`,
        'Content-Type': 'application/json',
      },
      data: {
        apiKey,
        source,
        remark,
        siteTags: category.title || '',
        deepConsult: actionType === 'consult',
      },
      success(res) {
        console.log('[CRM] 线索上报结果', res.data)
      },
      fail(err) {
        console.warn('[CRM] 线索上报请求失败', err)
      },
      complete() {
        if (typeof onDone === 'function') onDone()
      },
    })
  },

  onShareAppMessage() {
    const { getSharePath } = require('../../utils/share')
    return { title: '神仙团队性格测试 - 发现你的内在潜能', path: getSharePath('/pages/purchase/index') }
  },

  onShareTimeline() {
    const { buildShareQuery } = require('../../utils/share')
    return {
      title: '神仙团队性格测试 - 发现你的内在潜能',
      query: buildShareQuery()
    }
  }
})
