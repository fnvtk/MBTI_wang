// pages/purchase/index.js - 开通会员（深度服务价格：个人/企业区分，类目由后端配置可新增）
const app = getApp()
const payment = require('../../utils/payment')
const { hasPhone, bindPhoneByCode, ensureProfileCompleteAndRedirect } = require('../../utils/phoneAuth.js')

Page({
  data: {
    activeTab: 'personal',
    personalCategories: [],
    enterpriseCategories: [],
    loading: true,
    purchasing: false,
    hasPhone: false,
    successModal: {
      visible: false,
      title: '',
      content: '',
      wechat: ''
    }
  },

  onLoad(options) {
    const tab = (options && options.tab === 'enterprise') ? 'enterprise' : 'personal'
    this.setData({ activeTab: tab })
    tt.setNavigationBarTitle({ title: '深度服务' })
    this.loadDeepPricing()
  },

  onShow() {
    if (!ensureProfileCompleteAndRedirect()) return
    this.setData({ hasPhone: hasPhone() })
  },

  loadDeepPricing() {
    const apiBase = app.globalData.apiBase || ''
    if (!apiBase) {
      this.setData({ loading: false })
      return
    }
    this.setData({ loading: true })
    Promise.all([
      this.requestDeepPricing('personal'),
      this.requestDeepPricing('enterprise')
    ]).then(([personal, enterprise]) => {
      this.setData({
        personalCategories: personal || [],
        enterpriseCategories: enterprise || [],
        loading: false
      })
    }).catch(() => {
      this.setData({ loading: false })
    })
  },

  requestDeepPricing(scope) {
    return new Promise((resolve) => {
      tt.request({
        url: `${app.globalData.apiBase.replace(/\/$/, '')}/api/config/deep-pricing`,
        method: 'GET',
        data: { scope },
        success: (res) => {
          if (res.statusCode === 200 && res.data && res.data.code === 200 && Array.isArray(res.data.data && res.data.data.categories)) {
            resolve(res.data.data.categories)
          } else {
            resolve([])
          }
        },
        fail: () => resolve([])
      })
    })
  },

  switchTab(e) {
    const tab = e.currentTarget.dataset.tab
    if (tab !== 'personal' && tab !== 'enterprise') return
    this.setData({ activeTab: tab })
    tt.setNavigationBarTitle({ title: '深度服务' })
  },

  // 无需再次授权时，直接点击按钮执行购买/咨询
  handlePurchaseTap(e) {
    const tab = e.currentTarget.dataset.tab
    const index = e.currentTarget.dataset.index
    this.handlePurchase(tab, index)
  },

  // 实际执行购买/咨询逻辑（已确保有手机号）
  handlePurchase(tab, index) {
    if (!ensureProfileCompleteAndRedirect()) return
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
        tt.showToast({ title: '需要授权手机号才能继续', icon: 'none' })
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
        tt.showToast({ title: '获取手机号失败', icon: 'none' })
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
    tt.showLoading({ title: '处理中...', mask: true })
    const deepProductId = category.id || category.productKey || ''
    const title = category.title || '个人深度服务（1v1深度解读）'
    payment.purchasePersonalDeepService({
      deepProductId,
      description: title,
      success: () => {
        tt.hideLoading()
        this.setData({ purchasing: false })
        this._reportCrmLead(category, 'buy')
        const successMsg = (category.successMessage || '购买成功！我们的顾问会尽快与您联系，为您提供专属深度解读服务。').trim()
        const wechat = (category.serviceWechat || '').trim()
        this._showSuccessModal('购买成功', successMsg, wechat)
      },
      fail: () => {
        tt.hideLoading()
        this.setData({ purchasing: false })
      }
    })
  },

  applyConsult(category) {
    // serviceWechat 展示给用户，consultWechat 是存客宝 API key
    const wechat = (category.serviceWechat || '').trim()
    const apiKey = (category.consultWechat || '').trim()
    const successMsg = (category.successMessage || '感谢您的申请，我们的顾问会尽快与您联系！').trim()
    tt.showLoading({ title: '提交中...', mask: true })
    if (apiKey) {
      this._reportCrmLead(category, 'consult')
    }
    setTimeout(() => {
      tt.hideLoading()
      this._showSuccessModal('申请成功', successMsg, wechat)
    }, 600)
  },

  _showSuccessModal(title, content, wechat) {
    this.setData({
      successModal: {
        visible: true,
        title: title || '成功',
        content: content || '',
        wechat: wechat || ''
      }
    })
  },

  catchTap() {},

  closeSuccessModal() {
    this.setData({ 'successModal.visible': false })
  },

  copyWechat() {
    const wechat = this.data.successModal.wechat
    if (!wechat) return
    tt.setClipboardData({
      data: wechat,
      success: () => tt.showToast({ title: '已复制微信号', icon: 'success' })
    })
  },

  /**
   * 向后端上报存客宝线索，后端负责签名和调用存客宝 API
   * @param {Object} category  深度服务类目对象（需含 consultWechat / title）
   * @param {string} actionType  'buy'（付款完成）| 'consult'（申请咨询）
   */
  _reportCrmLead(category, actionType) {
    const apiKey = category.consultWechat || ''
    if (!apiKey) return
    const apiBase = app.globalData.apiBase || ''
    if (!apiBase) return

    const isEnterprise = this.data.activeTab === 'enterprise'
    const source = (isEnterprise ? '企业深度服务' : '个人深度服务') + (category.title ? `-${category.title}` : '')
    const remark = actionType === 'buy' ? '完成付款' : '申请咨询'

    tt.request({
      url: `${apiBase.replace(/\/$/, '')}/api/crm/report`,
      method: 'POST',
      header: {
        Authorization: `Bearer ${tt.getStorageSync('token') || ''}`,
        'Content-Type': 'application/json',
      },
      data: {
        apiKey,
        source,
        remark,
        siteTags: category.title || '',
      },
      success(res) {
        console.log('[CRM] 线索上报结果', res.data)
      },
      fail(err) {
        console.warn('[CRM] 线索上报请求失败', err)
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
