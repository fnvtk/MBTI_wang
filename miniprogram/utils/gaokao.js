const { requestPromise } = require('./request')

function gaokaoPricingQueryParts(extraParams) {
  const app = getApp()
  const gd = (app && app.globalData) || {}
  const scope =
    (extraParams && extraParams.pricingScope) ||
    (gd.appScope === 'enterprise' ? 'enterprise' : 'personal')
  const parts = [`pricingScope=${encodeURIComponent(scope)}`]
  try {
    const { getEnterpriseIdForApiPayload } = require('./enterpriseContext.js')
    const eid = getEnterpriseIdForApiPayload()
    if (eid != null && Number(eid) > 0) {
      parts.push(`enterpriseId=${encodeURIComponent(String(eid))}`)
    }
  } catch (e) {}
  return parts
}

function getTaskStatus(params = {}) {
  const query = []
  if (params.referrerId) query.push(`referrerId=${encodeURIComponent(params.referrerId)}`)
  if (params.channelCode) query.push(`channelCode=${encodeURIComponent(params.channelCode)}`)
  if (params.scene) query.push(`scene=${encodeURIComponent(params.scene)}`)
  gaokaoPricingQueryParts(params).forEach((p) => query.push(p))
  const qs = query.length ? `?${query.join('&')}` : ''
  return requestPromise({
    url: `/api/gaokao/task-status${qs}`,
    method: 'GET'
  }).then((res) => (res.data && res.data.data) || {})
}

function getForm() {
  return requestPromise({
    url: '/api/gaokao/form',
    method: 'GET'
  }).then((res) => (res.data && res.data.data) || {})
}

function saveForm(data) {
  return requestPromise({
    url: '/api/gaokao/form',
    method: 'POST',
    data
  }).then((res) => (res.data && res.data.data) || {})
}

function analyze(extra = {}) {
  const app = getApp()
  const gd = (app && app.globalData) || {}
  const pricingScope =
    (extra && extra.pricingScope) || (gd.appScope === 'enterprise' ? 'enterprise' : 'personal')
  let enterpriseId = 0
  try {
    const { getEnterpriseIdForApiPayload } = require('./enterpriseContext.js')
    const eid = getEnterpriseIdForApiPayload()
    if (eid != null && Number(eid) > 0) enterpriseId = Number(eid)
  } catch (e) {}

  return requestPromise({
    url: '/api/gaokao/analyze',
    method: 'POST',
    data: Object.assign({}, extra, { pricingScope, enterpriseId }),
    timeout: 120000
  }).then((res) => {
    const body = res.data || {}
    if (body.code !== 200) {
      throw new Error(body.message || '分析失败')
    }
    return body.data || {}
  })
}

function latestReport() {
  return requestPromise({
    url: '/api/gaokao/report/my-latest',
    method: 'GET'
  }).then((res) => (res.data && res.data.data) || {})
}

module.exports = {
  getTaskStatus,
  getForm,
  saveForm,
  analyze,
  latestReport
}
