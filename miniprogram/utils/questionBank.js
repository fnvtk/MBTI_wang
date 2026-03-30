/**
 * 从服务端拉取启用题库，失败或为空时回落本地 questions.js；顺序由 shuffleQuestions 随机。
 */
const { requestPromise } = require('./request')
const { shuffleQuestions } = require('./questions')

function getAppSafe() {
  try {
    return getApp()
  } catch (e) {
    return null
  }
}

/** 与 runtime 一致：>0 时在乱序后截取前 N 题 */
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
 * @param {'mbti'|'disc'|'pdp'} type
 * @param {number|null|undefined} enterpriseId
 * @returns {Promise<Array>}
 */
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

/**
 * @param {'mbti'|'disc'|'pdp'} type
 * @param {Array} localQuestions
 * @param {{ enterpriseId?: number|null }} opts
 * @returns {Promise<Array>}
 */
function loadQuestionsWithFallback(type, localQuestions, opts = {}) {
  const { enterpriseId } = opts
  return fetchQuestionBank(type, enterpriseId)
    .then((list) => {
      if (!list.length) {
        return applyDrawCountAfterShuffle(shuffleQuestions(localQuestions))
      }
      return shuffleQuestions(list)
    })
    .catch(() => applyDrawCountAfterShuffle(shuffleQuestions(localQuestions)))
}

module.exports = {
  fetchQuestionBank,
  loadQuestionsWithFallback
}
