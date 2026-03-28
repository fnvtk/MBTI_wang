// miniprogram/utils/payment.js
// 微信支付工具类 - 复刻自Soul项目

const app = getApp()
const { getEnterpriseIdForApiPayload } = require('./enterpriseContext.js')

function paymentApiBase() {
  const b = (app.globalData && app.globalData.apiBase) ? String(app.globalData.apiBase) : ''
  return b.replace(/\/$/, '')
}

/** 创建订单时传给后端的 enterpriseId（0 表示无企业上下文）；与个人/企业 Tab 一致 */
function enterpriseIdForOrder() {
  const eid = getEnterpriseIdForApiPayload()
  return eid != null && Number(eid) > 0 ? Number(eid) : 0
}

/**
 * 生成符合微信规则的订单号
 * 规则示例：FACE20260226151900001（前缀+时间戳+随机数，最长不超过32位）
 * @param {String} productType - 业务类型，如 face/mbti/disc/pdp/report/team_analysis/vip 等
 */
function generateOrderId(productType) {
  const now = new Date()
  const pad = (n, len = 2) => String(n).padStart(len, '0')
  const prefixMap = {
    face: 'FACE',
    mbti: 'MBTI',
    disc: 'DISC',
    pdp: 'PDP',
    report: 'REPT',
    team_analysis: 'TEAM',
    vip: 'VIP',
    test_count: 'TNUM',
    single_test: 'TSGL',
    recharge: 'RCG',
    deep_personal: 'DPER',
    deep_team: 'DTEAM'
  }

  const prefix = prefixMap[productType] || (productType || 'ORD').toUpperCase().slice(0, 6)
  const dateStr =
    now.getFullYear().toString() +
    pad(now.getMonth() + 1) +
    pad(now.getDate()) +
    pad(now.getHours()) +
    pad(now.getMinutes()) +
    pad(now.getSeconds())

  // 3位随机数，避免重复，如 001~999
  const rand = pad(Math.floor(Math.random() * 1000), 3)
  const raw = `${prefix}${dateStr}${rand}` // 示例：FACE20260226151900001

  // 微信 out_trade_no 最长 32 字节，这里兜底截断
  return raw.length > 32 ? raw.slice(0, 32) : raw
}

/**
 * 发起微信支付
 * @param {Object} options - 支付选项
 * @param {String} options.orderId - 订单ID
 * @param {Number} [options.amount] - 支付金额(分)，部分产品使用（如充值/VIP/测试次数等）；深度服务与按配置定价的产品传 0 或不传
 * @param {String} options.description - 商品描述
 * @param {String} options.productType - 商品类型: 'vip' | 'test_count' | 'single_test' | 'face' | 'mbti' 等
 * @param {Number} [options.testResultId] - 可选，对应 mbti_test_results.id，用于精确关联测试记录
 * @param {String} [options.deepProductId] - 可选，深度服务对应的套餐ID/产品Key，用于后台从 categories 中选中具体价格
 * @param {Function} options.success - 成功回调
 * @param {Function} options.fail - 失败回调
 */
function wxPay(options) {
  const { orderId, amount = 0, description, productType, testResultId, deepProductId, enterpriseId, success, fail } = options

  try {
    const analyticsMod = require('./analytics')
    if (analyticsMod && typeof analyticsMod.track === 'function') {
      if (productType === 'recharge') {
        analyticsMod.track('click_recharge', { action: '点击充值并发起支付', productType: 'recharge' })
      } else {
        analyticsMod.track('click_pay', { action: '发起支付', productType: productType || '' })
      }
      if (typeof analyticsMod.flush === 'function') {
        analyticsMod.flush()
      }
    }
  } catch (e) {}

  wx.showLoading({
    title: '正在支付...',
    mask: true
  })

  // 1. 调用后端创建支付订单
  wx.request({
    url: `${paymentApiBase()}/api/payment/create`,
    method: 'POST',
    header: {
      'Authorization': `Bearer ${wx.getStorageSync('token')}`,
      'Content-Type': 'application/json'
    },
    data: {
      orderId,
      amount,
      description,
      productType,
      paymentMethod: 'wechat',
      openId: app.globalData.openId || '',
      enterpriseId: enterpriseId || 0,
      // 创建订单时将本次测试记录ID传给后端，避免每次都只更新“最新一条”
      testResultId: testResultId || 0,
      // 深度服务使用的具体套餐ID/产品Key（用于从 categories 中选择价格）
      deepProductId: deepProductId || ''
    },
    success: (res) => {
      wx.hideLoading()
      
      if (res.statusCode === 200 && res.data.code === 200) {
        const paymentData = res.data.data
        
        // 2. 调起微信支付
        wx.requestPayment({
          timeStamp: paymentData.timeStamp,
          nonceStr: paymentData.nonceStr,
          package: paymentData.package,
          signType: paymentData.signType || 'MD5',
          paySign: paymentData.paySign,
          success: (payRes) => {
            console.log('支付成功', payRes)

            // 按照微信文档推荐，仅依赖查询接口确认支付结果，不做本地立即标记
            // 支付成功后轮询后端订单状态 3~5 次，确保通过微信订单查询接口确认成功
            pollOrderStatus(orderId, 5, 1000, (ok, order) => {
              if (ok) {
                try { require('./analytics').reportPayResult(true, { productType: productType || '', orderId, amount }) } catch (e) {}
                wx.showToast({
                  title: '支付成功',
                  icon: 'success',
                  duration: 2000
                })
                success && success({ payRes, order })
              } else {
                try { require('./analytics').reportPayResult(true, { productType: productType || '', orderId, amount, note: 'poll_pending' }) } catch (e) {}
                wx.showToast({
                  title: '支付结果处理中，请稍后在历史中查看',
                  icon: 'none',
                  duration: 2500
                })
                success && success({ payRes, order: null })
              }
            })
          },
          fail: (payErr) => {
            console.error('支付失败', payErr)
            const isCancel = payErr.errMsg && payErr.errMsg.indexOf('cancel') !== -1
            try { require('./analytics').reportPayResult(false, { productType: productType || '', orderId, reason: isCancel ? 'cancel' : 'fail' }) } catch (e) {}
            if (isCancel) {
              wx.showToast({
                title: '支付已取消',
                icon: 'none'
              })
            } else {
              wx.showToast({
                title: '支付失败',
                icon: 'none'
              })
            }
            
            fail && fail(payErr)
          }
        })
      } else {
        wx.showToast({
          title: res.data.message || '创建订单失败',
          icon: 'none'
        })
        fail && fail(res)
      }
    },
    fail: (err) => {
      wx.hideLoading()
      console.error('请求失败', err)
      
      wx.showToast({
        title: '网络请求失败',
        icon: 'none'
      })
      
      fail && fail(err)
    }
  })
}

/**
 * 通知后端支付成功
 */
function notifyPaymentSuccess(orderId, prepayId) {
  wx.request({
    url: `${paymentApiBase()}/api/payment/notify`,
    method: 'POST',
    header: {
      'Authorization': `Bearer ${wx.getStorageSync('token')}`,
      'Content-Type': 'application/json'
    },
    data: {
      orderId,
      prepayId,
      status: 'success'
    },
    success: (res) => {
      console.log('支付通知成功', res)
    },
    fail: (err) => {
      console.error('支付通知失败', err)
    }
  })
}

/**
 * 查询订单状态
 */
function queryOrderStatus(orderId, callback) {
  wx.request({
    url: `${paymentApiBase()}/api/payment/query`,
    method: 'GET',
    header: {
      'Authorization': `Bearer ${wx.getStorageSync('token')}`
    },
    data: { orderId },
    success: (res) => {
      if (res.statusCode === 200 && res.data.code === 200) {
        callback && callback(true, res.data.data)
      } else {
        callback && callback(false, null)
      }
    },
    fail: () => {
      callback && callback(false, null)
    }
  })
}

/**
 * 按照微信文档建议：支付成功后，基于商户订单号轮询查询订单状态（本项目由后端代理查询）
 * @param {String} orderId 商户订单号（如 FACE20260226151900001）
 * @param {Number} maxAttempts 最大轮询次数
 * @param {Number} intervalMs 间隔毫秒
 * @param {Function} done 回调 (ok:boolean, order?:object)
 */
function pollOrderStatus(orderId, maxAttempts = 5, intervalMs = 1000, done) {
  let attempts = 0

  const tick = () => {
    attempts += 1
    queryOrderStatus(orderId, (ok, order) => {
      if (ok && order && (order.status === 'paid' || order.status === 'completed')) {
        done && done(true, order)
        return
      }
      if (attempts >= maxAttempts) {
        done && done(false, order || null)
        return
      }
      setTimeout(tick, intervalMs)
    })
  }

  tick()
}

/**
 * 购买VIP会员
 * @param {String} vipType - 'month' | 'quarter' | 'year' | 'lifetime'
 */
function purchaseVIP(vipType, success, fail) {
  const prices = {
    month: 1990,      // 19.9元
    quarter: 4990,    // 49.9元
    year: 9900,       // 99元
    lifetime: 19900,  // 199元
    personal_insight: 19800  // 198元 - 个人深度洞察版
  }
  
  const names = {
    month: '月度VIP会员',
    quarter: '季度VIP会员',
    year: '年度VIP会员',
    lifetime: '终身VIP会员',
    personal_insight: '个人深度洞察版'
  }
  
  const orderId = generateOrderId('vip')
  
  wxPay({
    orderId,
    amount: prices[vipType],
    description: `MBTI性格测试 - ${names[vipType]}`,
    productType: 'vip',
    success: (res) => {
      // 更新VIP状态
      updateVIPStatus(vipType)
      success && success(res)
    },
    fail
  })
}

/**
 * 购买测试次数
 * @param {Number} count - 购买次数
 */
function purchaseTestCount(count, success, fail) {
  // 单次价格 3.9元，10次29元，50次99元
  let price = count * 390
  if (count >= 10) price = Math.floor(count * 290)
  if (count >= 50) price = Math.floor(count * 198)
  
  const orderId = generateOrderId('test_count')
  
  wxPay({
    orderId,
    amount: price,
    description: `MBTI性格测试 - ${count}次测试次数`,
    productType: 'test_count',
    success: (res) => {
      // 更新测试次数
      addTestCount(count)
      success && success(res)
    },
    fail
  })
}

/**
 * 购买单次测试
 * @param {String} testType - 'mbti' | 'disc' | 'pdp' | 'ai'
 */
function purchaseSingleTest(testType, success, fail) {
  const prices = {
    mbti: 990,    // 9.9元
    disc: 690,    // 6.9元
    pdp: 690,     // 6.9元
    ai: 1990      // 19.9元
  }
  
  const names = {
    mbti: 'MBTI性格测试',
    disc: 'DISC行为风格测试',
    pdp: 'PDP动物性格测试',
    ai: 'AI人脸性格分析'
  }
  
  const orderId = generateOrderId(`single_${testType}`)
  
  wxPay({
    orderId,
    amount: prices[testType],
    description: names[testType],
    productType: 'single_test',
    success: (res) => {
      // 解锁该测试
      unlockTest(testType)
      success && success(res)
    },
    fail
  })
}

/**
 * 使用后台定价购买单项测试/报告
 * 支持：人脸测试/MBTI测试/DISC测试/PDP测试/完整报告/团队分析
 * @param {String} productType - 'face' | 'mbti' | 'disc' | 'pdp' | 'report' | 'team_analysis'
 * @param {String} description - 商品描述
 * @param {Function|Object} extra - 兼容两种调用方式：
 *   - purchaseByPricing('face', 'xxx', success, fail)
 *   - purchaseByPricing('face', 'xxx', { testResultId, success, fail })
 */
function purchaseByPricing(productType, description, extra, maybeFail) {
  let opts = {}
  if (typeof extra === 'function' || extra === undefined) {
    opts.success = extra
    opts.fail = maybeFail
  } else {
    opts = extra || {}
  }

  const { testResultId, success, fail } = opts
  const orderId = generateOrderId(productType)

  wxPay({
    orderId,
    amount: 0, // 金额交由后端根据定价配置计算（personal/enterprise）
    description,
    productType,
    testResultId,
    enterpriseId: enterpriseIdForOrder(),
    success,
    fail
  })
}

// 人脸测试完整报告
// 兼容两种调用方式：
// - purchaseFaceTest(success, fail)
// - purchaseFaceTest({ testResultId, success, fail })
function purchaseFaceTest(arg1, arg2) {
  let opts = {}
  if (typeof arg1 === 'function' || arg1 === undefined) {
    opts.success = arg1
    opts.fail = arg2
  } else {
    opts = arg1 || {}
  }

  const { testResultId, success, fail } = opts

  purchaseByPricing('face', 'AI人脸性格分析完整报告', { testResultId, success, fail })
}

// MBTI测试付费版（支持 purchaseMbtiTest({ testResultId, success, fail })）
function purchaseMbtiTest(arg1, arg2) {
  const opts = typeof arg1 === 'function' || arg1 == null ? { success: arg1, fail: arg2 } : (arg1 || {})
  const { testResultId, success, fail } = opts
  purchaseByPricing('mbti', 'MBTI性格测试付费版', { testResultId, success, fail })
}

// DISC测试付费版
function purchaseDiscTest(arg1, arg2) {
  const opts = typeof arg1 === 'function' || arg1 == null ? { success: arg1, fail: arg2 } : (arg1 || {})
  const { testResultId, success, fail } = opts
  purchaseByPricing('disc', 'DISC行为风格测试付费版', { testResultId, success, fail })
}

// PDP测试付费版
function purchasePdpTest(arg1, arg2) {
  const opts = typeof arg1 === 'function' || arg1 == null ? { success: arg1, fail: arg2 } : (arg1 || {})
  const { testResultId, success, fail } = opts
  purchaseByPricing('pdp', 'PDP动物性格测试付费版', { testResultId, success, fail })
}

// 简历综合分析付费版（支持 purchaseResumeAnalysis({ testResultId, success, fail })）
function purchaseResumeAnalysis(arg1, arg2) {
  const opts = typeof arg1 === 'function' || arg1 == null ? { success: arg1, fail: arg2 } : (arg1 || {})
  const { testResultId, success, fail } = opts
  purchaseByPricing('resume', '简历综合分析付费版', { testResultId, success, fail })
}

// 完整报告（整合MBTI/DISC/PDP/AI人脸等深度解读）
function purchaseFullReport(success, fail) {
  purchaseByPricing('report', '完整人格与职业发展报告', success, fail)
}

// 团队分析服务
function purchaseTeamAnalysis(success, fail) {
  purchaseByPricing('team_analysis', '团队性格组合与冲突分析服务', success, fail)
}

/**
 * 企业充值
 * 支持两种调用：
 * - recharge(100, success, fail)
 * - recharge({ amountYuan: 100, enterpriseId: 6, success, fail })
 */
function recharge(arg1, arg2, arg3) {
  const opts = (typeof arg1 === 'object' && arg1 !== null)
    ? arg1
    : { amountYuan: arg1, success: arg2, fail: arg3 }
  const safeAmount = Number(opts.amountYuan) || 0
  const amount = Math.round(safeAmount * 100)
  const orderId = generateOrderId('recharge')

  wxPay({
    orderId,
    amount,
    description: `账户充值 ¥${safeAmount.toFixed(2)}`,
    productType: 'recharge',
    enterpriseId: opts.enterpriseId || 0,
    success: opts.success,
    fail: opts.fail
  })
}

/**
 * 个人深度服务（价格从 PricingConfig deep_personal.categories 中读取）
 * 支持两种调用方式：
 *   - purchasePersonalDeepService(deepProductId, success, fail)
 *   - purchasePersonalDeepService({ deepProductId, description, success, fail })
 * @param {String|Object} arg1
 * @param {Function} [arg2]
 * @param {Function} [arg3]
 */
function purchasePersonalDeepService(arg1, arg2, arg3) {
  let deepProductId = ''
  let description = ''
  let success
  let fail

  if (typeof arg1 === 'object' && arg1 !== null) {
    deepProductId = arg1.deepProductId || ''
    description = arg1.description || ''
    success = arg1.success
    fail = arg1.fail
  } else {
    deepProductId = typeof arg1 === 'string' ? arg1 : ''
    success = arg2
    fail = arg3
  }

  // 默认标题兜底
  const desc = description || '个人深度服务（1v1深度解读）'
  const orderId = generateOrderId('deep_personal')

  wxPay({
    orderId,
    amount: 0,
    description: desc,
    productType: 'deep_personal',
    deepProductId,
    enterpriseId: enterpriseIdForOrder(),
    success,
    fail
  })
}

/**
 * 团队深度服务（价格从 PricingConfig.deep.team 读取）
 */
function purchaseTeamDeepService(success, fail) {
  const orderId = generateOrderId('deep_team')

  wxPay({
    orderId,
    amount: 0,
    description: '团队深度服务（团队画像+策略）',
    productType: 'deep_team',
    enterpriseId: enterpriseIdForOrder(),
    success,
    fail
  })
}

/**
 * 更新VIP状态
 */
function updateVIPStatus(vipType) {
  const durations = {
    month: 30,
    quarter: 90,
    year: 365,
    lifetime: 36500 // 100年
  }
  
  const expireDate = new Date()
  expireDate.setDate(expireDate.getDate() + durations[vipType])
  
  const vipInfo = {
    isVIP: true,
    vipType,
    expireDate: expireDate.toISOString(),
    purchaseDate: new Date().toISOString()
  }
  
  wx.setStorageSync('vipInfo', vipInfo)
  app.globalData.vipInfo = vipInfo
}

/**
 * 增加测试次数
 */
function addTestCount(count) {
  const currentCount = wx.getStorageSync('testCount') || 0
  const newCount = currentCount + count
  wx.setStorageSync('testCount', newCount)
  app.globalData.testCount = newCount
}

/**
 * 解锁单次测试
 */
function unlockTest(testType) {
  const unlockedTests = wx.getStorageSync('unlockedTests') || []
  if (!unlockedTests.includes(testType)) {
    unlockedTests.push(testType)
    wx.setStorageSync('unlockedTests', unlockedTests)
  }
  app.globalData.unlockedTests = unlockedTests
}

/**
 * 检查是否是VIP
 */
function checkVIP() {
  const vipInfo = wx.getStorageSync('vipInfo')
  if (!vipInfo || !vipInfo.isVIP) return false
  
  const expireDate = new Date(vipInfo.expireDate)
  return expireDate > new Date()
}

/**
 * 检查测试是否可用（VIP或已解锁或有测试次数）
 */
function canTakeTest(testType) {
  // 临时策略：所有测试免费开放，直接返回 true
  // 保留原有支付与权益逻辑，后续若恢复收费可还原为 VIP/解锁/次数判断
  return true
}

/**
 * 消耗一次测试次数
 */
function consumeTestCount() {
  const testCount = wx.getStorageSync('testCount') || 0
  if (testCount > 0) {
    wx.setStorageSync('testCount', testCount - 1)
    app.globalData.testCount = testCount - 1
    return true
  }
  return false
}

/**
 * 获取用户权益信息
 */
function getUserBenefits() {
  return {
    isVIP: checkVIP(),
    vipInfo: wx.getStorageSync('vipInfo') || null,
    testCount: wx.getStorageSync('testCount') || 0,
    unlockedTests: wx.getStorageSync('unlockedTests') || []
  }
}

module.exports = {
  wxPay,
  queryOrderStatus,
  pollOrderStatus,
  generateOrderId,
  purchaseVIP,
  purchaseTestCount,
  purchaseSingleTest,
   // 基于后台定价的购买入口（测试/报告/团队分析/深度服务/充值）
  purchaseFaceTest,
  purchaseMbtiTest,
  purchaseDiscTest,
  purchasePdpTest,
  purchaseResumeAnalysis,
  purchaseFullReport,
  purchaseTeamAnalysis,
  recharge,
  purchasePersonalDeepService,
  purchaseTeamDeepService,
  checkVIP,
  canTakeTest,
  consumeTestCount,
  getUserBenefits,
  updateVIPStatus,
  addTestCount,
  unlockTest
}
