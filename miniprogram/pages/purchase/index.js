// pages/purchase/index.js - 开通会员（深度服务价格：个人/企业区分，类目由后端配置可新增）
// 禁止模块顶层 getApp()：上传/首屏偶发 App 未就绪会导致空白页
const payment = require('../../utils/payment')
const { hasPhone, bindPhoneByCode } = require('../../utils/phoneAuth.js')
const {
  buildDeepHeroDesc,
  filterCategories
} = require('../../utils/deepPricingFilter.js')

function appSafe() {
  try {
    return getApp()
  } catch (e) {
    return { globalData: {} }
  }
}

/** runtime 内嵌的深度类目（与 deep-pricing 同源） */
function embeddedDeepCategories(scope) {
  const gd = appSafe().globalData || {}
  const list = scope === 'enterprise' ? gd.deepPricingEnterprise : gd.deepPricingPersonal
  return Array.isArray(list) && list.length ? list : null
}

function rememberCategoryKeys(cat, seen) {
  const id = String((cat && cat.id) || '')
  const pk = String((cat && cat.productKey) || '')
  if (id) seen.add('id:' + id)
  if (pk) seen.add('pk:' + pk)
}

function categorySeenInSet(cat, seen) {
  const id = String((cat && cat.id) || '')
  const pk = String((cat && cat.productKey) || '')
  if (id && seen.has('id:' + id)) return true
  if (pk && seen.has('pk:' + pk)) return true
  return false
}

/**
 * 合并接口与 runtime 类目：缺条、仅 1 条而 runtime 更多时以 runtime 为准；否则按 id/productKey 并集补全（防漏档）
 */
function mergeDeepPricingFromRuntime(scope, apiList) {
  let list = Array.isArray(apiList) ? apiList.slice() : []
  const emb = embeddedDeepCategories(scope)
  if (!emb || !emb.length) return list
  if (!list.length) return emb.slice()
  if (list.length === 1 && emb.length > list.length) return emb.slice()

  const seen = new Set()
  list.forEach((c) => rememberCategoryKeys(c, seen))
  emb.forEach((c) => {
    if (!categorySeenInSet(c, seen)) {
      list.push(c)
      rememberCategoryKeys(c, seen)
    }
  })
  return list
}

Page({
  data: {
    activeTab: 'personal',
    personalCategories: [],
    enterpriseCategories: [],
    deepHeroDesc: '',
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

  /** 提审模式：深度套餐属虚拟商品，关闭本页并返回 */
  _exitPurchaseForAudit() {
    if (this._purchaseAuditExitScheduled) return
    this._purchaseAuditExitScheduled = true
    wx.showToast({ title: '版本审核中暂不可用', icon: 'none' })
    this.setData({ loading: false, loadError: false, loadErrorMsg: '' })
    setTimeout(() => {
      wx.navigateBack({ fail: () => wx.switchTab({ url: '/pages/profile/index' }) })
    }, 400)
  },

  onLoad(options) {
    this._purchaseAuditExitScheduled = false
    this._deepPersonalRecheckScheduled = false
    const app = appSafe()
    const tab = (options && options.tab === 'enterprise') ? 'enterprise' : 'personal'
    this.setData({
      activeTab: tab,
      deepHeroDesc: buildDeepHeroDesc(app.globalData.enterprisePermissions)
    })
    wx.setNavigationBarTitle({ title: '了解自己' })
    this.loadDeepPricing()
  },

  /** 企业 permissions 在 runtime 返回后可能与首屏不同，onShow / 拉价完成后重算 */
  _reapplyPermissionFilter() {
    const app = appSafe()
    const rawP = this._rawPersonalCategories
    const rawE = this._rawEnterpriseCategories
    if ((!rawP || !rawP.length) && (!rawE || !rawE.length)) return
    const perms = app.globalData.enterprisePermissions
    this.setData({
      personalCategories: filterCategories(rawP || [], perms),
      enterpriseCategories: filterCategories(rawE || [], perms),
      deepHeroDesc: buildDeepHeroDesc(perms)
    })
  },

  onShow() {
    // 不在此页强制跳转资料页：避免与付费按钮上的手机号授权打架导致死循环；未绑手机由按钮 open-type 引导
    this.setData({ hasPhone: hasPhone() })
    if (appSafe().globalData.miniprogramAuditMode) {
      this._exitPurchaseForAudit()
      return
    }
    this._reapplyPermissionFilter()
    // 网关/缓存曾只返回 1 条时，runtime 稍后就绪：首屏仅一条则静默再拉一次，便于露出 VMP 第二档
    const raw = this._rawPersonalCategories
    if (!this._deepPersonalRecheckScheduled && Array.isArray(raw) && raw.length === 1) {
      this._deepPersonalRecheckScheduled = true
      setTimeout(() => {
        this.loadDeepPricing()
      }, 500)
    }
  },

  loadDeepPricing() {
    const app = appSafe()
    const apiBase = app.globalData.apiBase || ''
    if (!apiBase) {
      this.setData({ loading: false, loadError: true, loadErrorMsg: '服务地址未配置' })
      return
    }
    this.setData({ loading: true, loadError: false, loadErrorMsg: '' })
    const preRuntime =
      app.getRuntimeConfig && typeof app.getRuntimeConfig === 'function'
        ? app.getRuntimeConfig().catch(() => null)
        : Promise.resolve(null)
    preRuntime.then(() => {
      if (appSafe().globalData.miniprogramAuditMode) {
        this._exitPurchaseForAudit()
        return
      }
      return Promise.all([this.requestDeepPricing('personal'), this.requestDeepPricing('enterprise')])
    }).then((pair) => {
      if (!pair) return
      const [personal, enterprise] = pair
      const pErr = personal && personal.__error
      const eErr = enterprise && enterprise.__error
      let pList = Array.isArray(personal) ? personal : []
      let eList = Array.isArray(enterprise) ? enterprise : []
      if (pErr && embeddedDeepCategories('personal')) {
        pList = mergeDeepPricingFromRuntime('personal', [])
        personal = pList
      }
      if (eErr && embeddedDeepCategories('enterprise')) {
        eList = mergeDeepPricingFromRuntime('enterprise', [])
        enterprise = eList
      }
      if (!pErr && pList.length) {
        pList = mergeDeepPricingFromRuntime('personal', pList)
      }
      if (!eErr && eList.length) {
        eList = mergeDeepPricingFromRuntime('enterprise', eList)
      }
      const bothFailed = pErr && eErr && !pList.length && !eList.length
      const bothEmpty = !pList.length && !eList.length
      if (bothFailed || bothEmpty) {
        const msg = pErr ? (personal.__errorMsg || '网络异常') : (eErr ? (enterprise.__errorMsg || '网络异常') : '暂无可购买方案')
        this._rawPersonalCategories = []
        this._rawEnterpriseCategories = []
        this.setData({
          personalCategories: [],
          enterpriseCategories: [],
          deepHeroDesc: buildDeepHeroDesc(null),
          loading: false,
          loadError: true,
          loadErrorMsg: msg
        })
        return
      }
      this._rawPersonalCategories = pList
      this._rawEnterpriseCategories = eList
      const perms = appSafe().globalData.enterprisePermissions
      this.setData({
        personalCategories: filterCategories(pList, perms),
        enterpriseCategories: filterCategories(eList, perms),
        deepHeroDesc: buildDeepHeroDesc(perms),
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
    const app = appSafe()
    return new Promise((resolve) => {
      wx.request({
        url: `${app.globalData.apiBase.replace(/\/$/, '')}/api/config/deep-pricing`,
        method: 'GET',
        data: { scope },
        timeout: 25000,
        success: (res) => {
          if (res.statusCode === 200 && res.data && res.data.code === 200 && Array.isArray(res.data.data && res.data.data.categories)) {
            resolve(mergeDeepPricingFromRuntime(scope, res.data.data.categories))
          } else {
            const emb = embeddedDeepCategories(scope)
            if (emb && emb.length) {
              resolve(emb.slice())
              return
            }
            resolve({ __error: true, __errorMsg: (res && res.data && res.data.message) || '响应异常' })
          }
        },
        fail: (err) => {
          const emb = embeddedDeepCategories(scope)
          if (emb && emb.length) {
            resolve(emb.slice())
            return
          }
          resolve({ __error: true, __errorMsg: (err && err.errMsg) || '网络异常' })
        }
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
    this._reportCrmLead(category, 'consult', () => {
      wx.hideLoading()
      this._showSuccessModal('申请成功', successMsg)
    })
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
   * 向后端上报线索：存客宝（有 KEY 时）+ 飞书群（申请咨询且 deepConsult）
   * @param {Function} [onDone] 请求结束回调（含失败）
   */
  _reportCrmLead(category, actionType, onDone) {
    const app = appSafe()
    const apiKey = (category.consultWechat || '').trim()
    const apiBase = (app.globalData.apiBase || '').replace(/\/$/, '')
    const isConsult = actionType === 'consult'
    const deepConsult = isConsult

    const finish = () => {
      if (typeof onDone === 'function') onDone()
    }

    if (!apiBase) {
      finish()
      return
    }

    if (!deepConsult && !apiKey) {
      finish()
      return
    }

    const isEnterprise = this.data.activeTab === 'enterprise'
    const source = (isEnterprise ? '企业深度服务' : '个人深度服务') + (category.title ? `-${category.title}` : '')
    const remark =
      actionType === 'buy'
        ? '完成付款'
        : isEnterprise
          ? '申请咨询并降低30%成本'
          : '申请咨询'

    wx.request({
      url: `${apiBase}/api/crm/report`,
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
        deepConsult: deepConsult ? true : false
      },
      success(res) {
        console.log('[CRM] 线索上报结果', res.data)
      },
      fail(err) {
        console.warn('[CRM] 线索上报请求失败', err)
      },
      complete: finish
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
