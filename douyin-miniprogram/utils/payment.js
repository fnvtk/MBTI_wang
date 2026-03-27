// douyin-miniprogram/utils/payment.js
// 抖音支付工具类 - 从微信版移植
// 后端需实现 /api/payment/create 返回 { order_id, order_token } 用于 tt.pay

const app = getApp()

/**
 * 生成订单号
 * 规则：前缀+时间戳+随机数，最长64位（抖音限制比微信宽松）
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

  const rand = pad(Math.floor(Math.random() * 1000), 3)
  const raw = `${prefix}${dateStr}${rand}`

  return raw.length > 64 ? raw.slice(0, 64) : raw
}

/**
 * 发起抖音支付
 * 流程：后端创建订单 → 返回 order_id + order_token → tt.pay 调起收银台
 * 后端需实现 /api/payment/create 接口，当 paymentMethod='douyin' 时返回：
 *   { order_id: 'xxx', order_token: 'xxx' }
 */
function douyinPay(options) {
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

  tt.showLoading({
    title: '正在支付...',
    mask: true
  })

  tt.request({
    url: `${app.globalData.apiBase}/api/payment/create`,
    method: 'POST',
    header: {
      'Authorization': `Bearer ${tt.getStorageSync('token')}`,
      'Content-Type': 'application/json'
    },
    data: {
      orderId,
      amount,
      description,
      productType,
      paymentMethod: 'douyin',
      openId: app.globalData.openId || '',
      enterpriseId: enterpriseId || 0,
      testResultId: testResultId || 0,
      deepProductId: deepProductId || ''
    },
    success: (res) => {
      tt.hideLoading()

      if (res.statusCode === 200 && res.data.code === 200) {
        const paymentData = res.data.data

        tt.pay({
          orderInfo: {
            order_id: paymentData.order_id,
            order_token: paymentData.order_token
          },
          service: 5,
          success: (payRes) => {
            console.log('抖音支付回调', payRes)

            if (payRes.code === 0) {
              pollOrderStatus(orderId, 5, 1000, (ok, order) => {
                if (ok) {
                  tt.showToast({
                    title: '支付成功',
                    icon: 'success',
                    duration: 2000
                  })
                  success && success({ payRes, order })
                } else {
                  tt.showToast({
                    title: '支付结果处理中，请稍后查看',
                    icon: 'none',
                    duration: 2500
                  })
                  success && success({ payRes, order: null })
                }
              })
            } else if (payRes.code === 4) {
              tt.showToast({
                title: '支付已取消',
                icon: 'none'
              })
              fail && fail(payRes)
            } else if (payRes.code === 9) {
              pollOrderStatus(orderId, 5, 1500, (ok, order) => {
                if (ok) {
                  tt.showToast({ title: '支付成功', icon: 'success', duration: 2000 })
                  success && success({ payRes, order })
                } else {
                  tt.showToast({ title: '支付结果待确认，请稍后查看', icon: 'none', duration: 2500 })
                  fail && fail(payRes)
                }
              })
            } else {
              tt.showToast({
                title: '支付失败',
                icon: 'none'
              })
              fail && fail(payRes)
            }
          },
          fail: (payErr) => {
            console.error('抖音支付失败', payErr)
            tt.showToast({
              title: '支付失败',
              icon: 'none'
            })
            fail && fail(payErr)
          }
        })
      } else {
        tt.showToast({
          title: res.data.message || '创建订单失败',
          icon: 'none'
        })
        fail && fail(res)
      }
    },
    fail: (err) => {
      tt.hideLoading()
      console.error('请求失败', err)
      tt.showToast({
        title: '网络请求失败',
        icon: 'none'
      })
      fail && fail(err)
    }
  })
}

function notifyPaymentSuccess(orderId, prepayId) {
  tt.request({
    url: `${app.globalData.apiBase}/api/payment/notify`,
    method: 'POST',
    header: {
      'Authorization': `Bearer ${tt.getStorageSync('token')}`,
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

function queryOrderStatus(orderId, callback) {
  tt.request({
    url: `${app.globalData.apiBase}/api/payment/query`,
    method: 'GET',
    header: {
      'Authorization': `Bearer ${tt.getStorageSync('token')}`
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

function purchaseVIP(vipType, success, fail) {
  const prices = {
    month: 1990,
    quarter: 4990,
    year: 9900,
    lifetime: 19900,
    personal_insight: 19800
  }

  const names = {
    month: '月度VIP会员',
    quarter: '季度VIP会员',
    year: '年度VIP会员',
    lifetime: '终身VIP会员',
    personal_insight: '个人深度洞察版'
  }

  const orderId = generateOrderId('vip')

  douyinPay({
    orderId,
    amount: prices[vipType],
    description: `MBTI性格测试 - ${names[vipType]}`,
    productType: 'vip',
    success: (res) => {
      updateVIPStatus(vipType)
      success && success(res)
    },
    fail
  })
}

function purchaseTestCount(count, success, fail) {
  let price = count * 390
  if (count >= 10) price = Math.floor(count * 290)
  if (count >= 50) price = Math.floor(count * 198)

  const orderId = generateOrderId('test_count')

  douyinPay({
    orderId,
    amount: price,
    description: `MBTI性格测试 - ${count}次测试次数`,
    productType: 'test_count',
    success: (res) => {
      addTestCount(count)
      success && success(res)
    },
    fail
  })
}

function purchaseSingleTest(testType, success, fail) {
  const prices = {
    mbti: 990,
    disc: 690,
    pdp: 690,
    ai: 1990
  }

  const names = {
    mbti: 'MBTI性格测试',
    disc: 'DISC行为风格测试',
    pdp: 'PDP动物性格测试',
    ai: '性格分析'
  }

  const orderId = generateOrderId(`single_${testType}`)

  douyinPay({
    orderId,
    amount: prices[testType],
    description: names[testType],
    productType: 'single_test',
    success: (res) => {
      unlockTest(testType)
      success && success(res)
    },
    fail
  })
}

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

  douyinPay({
    orderId,
    amount: 0,
    description,
    productType,
    testResultId,
    success,
    fail
  })
}

function purchaseFaceTest(arg1, arg2) {
  let opts = {}
  if (typeof arg1 === 'function' || arg1 === undefined) {
    opts.success = arg1
    opts.fail = arg2
  } else {
    opts = arg1 || {}
  }
  const { testResultId, success, fail } = opts
  purchaseByPricing('face', '性格分析完整报告', { testResultId, success, fail })
}

function purchaseMbtiTest(arg1, arg2) {
  const opts = typeof arg1 === 'function' || arg1 == null ? { success: arg1, fail: arg2 } : (arg1 || {})
  const { testResultId, success, fail } = opts
  purchaseByPricing('mbti', 'MBTI性格测试付费版', { testResultId, success, fail })
}

function purchaseDiscTest(arg1, arg2) {
  const opts = typeof arg1 === 'function' || arg1 == null ? { success: arg1, fail: arg2 } : (arg1 || {})
  const { testResultId, success, fail } = opts
  purchaseByPricing('disc', 'DISC行为风格测试付费版', { testResultId, success, fail })
}

function purchasePdpTest(arg1, arg2) {
  const opts = typeof arg1 === 'function' || arg1 == null ? { success: arg1, fail: arg2 } : (arg1 || {})
  const { testResultId, success, fail } = opts
  purchaseByPricing('pdp', 'PDP动物性格测试付费版', { testResultId, success, fail })
}

function purchaseResumeAnalysis(arg1, arg2) {
  const opts = typeof arg1 === 'function' || arg1 == null ? { success: arg1, fail: arg2 } : (arg1 || {})
  const { testResultId, success, fail } = opts
  purchaseByPricing('resume', '简历综合分析付费版', { testResultId, success, fail })
}

function purchaseFullReport(success, fail) {
  purchaseByPricing('report', '完整人格与职业发展报告', success, fail)
}

function purchaseTeamAnalysis(success, fail) {
  purchaseByPricing('team_analysis', '团队性格组合与冲突分析服务', success, fail)
}

function recharge(arg1, arg2, arg3) {
  const opts = (typeof arg1 === 'object' && arg1 !== null)
    ? arg1
    : { amountYuan: arg1, success: arg2, fail: arg3 }
  const safeAmount = Number(opts.amountYuan) || 0
  const amount = Math.round(safeAmount * 100)
  const orderId = generateOrderId('recharge')

  douyinPay({
    orderId,
    amount,
    description: `账户充值 ¥${safeAmount.toFixed(2)}`,
    productType: 'recharge',
    enterpriseId: opts.enterpriseId || 0,
    success: opts.success,
    fail: opts.fail
  })
}

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

  const desc = description || '个人深度服务（1v1深度解读）'
  const orderId = generateOrderId('deep_personal')

  douyinPay({
    orderId,
    amount: 0,
    description: desc,
    productType: 'deep_personal',
    deepProductId,
    success,
    fail
  })
}

function purchaseTeamDeepService(success, fail) {
  const orderId = generateOrderId('deep_team')

  douyinPay({
    orderId,
    amount: 0,
    description: '团队深度服务（团队画像+策略）',
    productType: 'deep_team',
    success,
    fail
  })
}

function updateVIPStatus(vipType) {
  const durations = {
    month: 30,
    quarter: 90,
    year: 365,
    lifetime: 36500
  }

  const expireDate = new Date()
  expireDate.setDate(expireDate.getDate() + durations[vipType])

  const vipInfo = {
    isVIP: true,
    vipType,
    expireDate: expireDate.toISOString(),
    purchaseDate: new Date().toISOString()
  }

  tt.setStorageSync('vipInfo', vipInfo)
  app.globalData.vipInfo = vipInfo
}

function addTestCount(count) {
  const currentCount = tt.getStorageSync('testCount') || 0
  const newCount = currentCount + count
  tt.setStorageSync('testCount', newCount)
  app.globalData.testCount = newCount
}

function unlockTest(testType) {
  const unlockedTests = tt.getStorageSync('unlockedTests') || []
  if (!unlockedTests.includes(testType)) {
    unlockedTests.push(testType)
    tt.setStorageSync('unlockedTests', unlockedTests)
  }
  app.globalData.unlockedTests = unlockedTests
}

function checkVIP() {
  const vipInfo = tt.getStorageSync('vipInfo')
  if (!vipInfo || !vipInfo.isVIP) return false

  const expireDate = new Date(vipInfo.expireDate)
  return expireDate > new Date()
}

function canTakeTest(testType) {
  return true
}

function consumeTestCount() {
  const testCount = tt.getStorageSync('testCount') || 0
  if (testCount > 0) {
    tt.setStorageSync('testCount', testCount - 1)
    app.globalData.testCount = testCount - 1
    return true
  }
  return false
}

function getUserBenefits() {
  return {
    isVIP: checkVIP(),
    vipInfo: tt.getStorageSync('vipInfo') || null,
    testCount: tt.getStorageSync('testCount') || 0,
    unlockedTests: tt.getStorageSync('unlockedTests') || []
  }
}

module.exports = {
  douyinPay,
  queryOrderStatus,
  pollOrderStatus,
  generateOrderId,
  purchaseVIP,
  purchaseTestCount,
  purchaseSingleTest,
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
