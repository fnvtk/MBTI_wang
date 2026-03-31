/**
 * 仅从服务端拉取启用题库，无本地题目保底。
 */
const { requestPromise } = require('./request')

function getAppSafe() {
  try {
    return getApp()
  } catch (e) {
    return null
  }
}

function shuffleQuestions(questions) {
  const arr = (questions || []).map(q => ({
    ...q,
    options: (q.options || []).slice().sort(() => Math.random() - 0.5)
  }))
  for (let i = arr.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1))
    ;[arr[i], arr[j]] = [arr[j], arr[i]]
  }
  return arr
}

function resolveEnterpriseIdForQuestionBank(opts) {
  const o = opts || {}
  if (Object.prototype.hasOwnProperty.call(o, 'enterpriseId')) {
    const v = o.enterpriseId
    if (v == null || v === '') {
      return null
    }
    const n = Number(v)
    return Number.isFinite(n) && n > 0 ? n : null
  }
  try {
    const { getEnterpriseIdForApiPayload } = require('./enterpriseContext')
    return getEnterpriseIdForApiPayload()
  } catch (e) {
    return null
  }
}

function getTestQuestionDrawCount() {
  const app = getAppSafe()
  const n = app && app.globalData && app.globalData.testQuestionDrawCount
  const num = parseInt(String(n == null || n === '' ? '0' : n), 10)
  if (!Number.isFinite(num) || num <= 0) {
    return 0
  }
  return Math.min(500, num)
}

function applyDrawCountAfterShuffle(questions) {
  const n = getTestQuestionDrawCount()
  if (!n || questions.length <= n) {
    return questions
  }
  return questions.slice(0, n)
}

function fetchQuestionBank(type, enterpriseId) {
  const q = [`type=${encodeURIComponent(type)}`]
  if (enterpriseId != null && Number(enterpriseId) > 0) {
    q.push(`enterpriseId=${Number(enterpriseId)}`)
  }
  return requestPromise({
    url: `/api/test/questions?${q.join('&')}`,
    method: 'GET',
    needAuth: true
  }).then((res) => {
    const body = res.data || {}
    if (body.code !== 200 || body.data == null) {
      throw new Error(body.message || '拉取题库失败')
    }
    const list = body.data.list
    if (!Array.isArray(list)) {
      throw new Error('题库格式错误')
    }
    return list
  })
}

function loadQuestions(type, opts = {}) {
  const enterpriseId = resolveEnterpriseIdForQuestionBank(opts)
  return fetchQuestionBank(type, enterpriseId).then((list) => {
    if (!list.length) {
      throw new Error('暂无启用题目')
    }
    return applyDrawCountAfterShuffle(shuffleQuestions(list))
  })
}

module.exports = {
  fetchQuestionBank,
  loadQuestions,
  shuffleQuestions
}
