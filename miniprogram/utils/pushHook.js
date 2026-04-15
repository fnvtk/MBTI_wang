const { request } = require('./request')

function triggerPushHook(event, payload = {}) {
  if (!event) return Promise.resolve(false)

  return new Promise((resolve) => {
    request({
      url: '/api/push-hook/trigger',
      method: 'POST',
      data: {
        event,
        ...payload
      },
      success(res) {
        const ok = !!(res && res.statusCode === 200 && res.data && res.data.code === 200)
        if (!ok) {
          console.warn('[PushHook] trigger rejected', event, res && res.data)
        }
        resolve(ok)
      },
      fail(err) {
        console.warn('[PushHook] trigger failed', event, err)
        resolve(false)
      }
    })
  })
}

function triggerTestResultCompleted(testResultId) {
  const id = Number(testResultId || 0)
  if (id <= 0) return Promise.resolve(false)
  return triggerPushHook('test.result_completed', { testResultId: id })
}

function triggerOrderPaid(orderId) {
  const no = String(orderId || '').trim()
  if (!no) return Promise.resolve(false)
  return triggerPushHook('lead.order_paid', { orderId: no })
}

module.exports = {
  triggerPushHook,
  triggerTestResultCompleted,
  triggerOrderPaid
}
