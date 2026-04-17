/**
 * 仅从服务端拉取启用题库（/api/test/questions），无本地题目保底。
 * enterpriseId：未传时与 test/submit 一致，见 enterpriseContext.getEnterpriseIdForApiPayload()
 */
const { requestPromise } = require('./request')

function getAppSafe() {
  try {
    return getApp()
  } catch (e) {
    return null
  }
}

/**
 * Fisher-Yates：打乱题目顺序，并随机每题选项顺序（深拷贝）
 * @param {Array} questions
 * @returns {Array}
 */
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

/**
 * @param {{ enterpriseId?: number|null }} opts
 * @returns {number|null}
 */
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

/**
 * @param {'mbti'|'sbti'|'disc'|'pdp'} type
 * @param {number|null|undefined} enterpriseId
 * @returns {Promise<Array>}
 */
function parseJsonBody(raw) {
  if (raw == null) return {}
  if (typeof raw === 'object' && !Array.isArray(raw)) return raw
  if (typeof raw === 'string') {
    const s = raw.trim()
    if (!s) return {}
    try {
      return JSON.parse(s)
    } catch (e) {
      return { _parseError: true, _rawSnippet: s.slice(0, 120) }
    }
  }
  return {}
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
    const body = parseJsonBody(res.data)
    if (body._parseError) {
      throw new Error('接口返回非 JSON，请检查小程序 apiBase 与后台域名（可设 Storage key: apiBaseOverride）')
    }
    if (body.code !== 200 || body.data == null) {
      const msg = body.message || '拉取题库失败'
      const hint = body.code === 401 || body.code === 403 ? `${msg}（请重新进入小程序或下拉刷新）` : msg
      throw new Error(hint)
    }
    const list = body.data.list
    if (!Array.isArray(list)) {
      throw new Error('题库格式错误')
    }
    return list
  })
}

/**
 * 仅 mbti 类型支持本地 fallback 题库（60 题）
 * 网络异常（isNetworkError）或 5xx 且线上拉题失败时启用，保证测试可完成
 */
function loadLocalFallback(type) {
  if (type !== 'mbti') return null
  try {
    const { getMbtiLocalQuestions } = require('./mbtiLocalQuestions.js')
    const list = getMbtiLocalQuestions()
    return Array.isArray(list) && list.length ? list : null
  } catch (e) {
    return null
  }
}

/**
 * @param {'mbti'|'sbti'|'disc'|'pdp'} type
 * @param {{ enterpriseId?: number|null, allowLocalFallback?: boolean }} opts
 * @returns {Promise<Array>} 乱序后的题目；接口无题或失败则 reject（mbti 可降级本地）
 */
function loadQuestions(type, opts = {}) {
  const enterpriseId = resolveEnterpriseIdForQuestionBank(opts)
  const allowLocal = opts.allowLocalFallback !== false // 默认允许
  return fetchQuestionBank(type, enterpriseId).then((list) => {
    if (!list.length) {
      throw new Error('暂无启用题目')
    }
    return applyDrawCountAfterShuffle(shuffleQuestions(list))
  }).catch((err) => {
    // 网络异常 / 5xx / 401（登录失败）/ 超时均降级；4xx 业务错误不降级
    const status = err && err.statusCode
    const canFallback = err && (
      err.isNetworkError ||
      (status >= 500 && status < 600) ||
      status === 401
    )
    if (!allowLocal || !canFallback) throw err
    const local = loadLocalFallback(type)
    if (!local) throw err
    console.warn('[questionBank] 后端不可达，降级本地 fallback 题库:', type, err && err.message)
    return applyDrawCountAfterShuffle(shuffleQuestions(local))
  })
}

module.exports = {
  fetchQuestionBank,
  loadQuestions,
  shuffleQuestions
}
