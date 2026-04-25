/**
 * 高考报告页「两步解锁」：看全文 → 分享朋友圈（不与 MBTI 的 mbti_journey_unlocks 混用）
 * storage: gaokao_journey_<testResultId> = { sharedMoment: ts }
 */

function storageKey(testResultId) {
  const id =
    testResultId != null && String(testResultId) !== '' && String(testResultId) !== '0'
      ? String(testResultId)
      : '0'
  return 'gaokao_journey_' + id
}

function read(testResultId) {
  try {
    const v = wx.getStorageSync(storageKey(testResultId))
    if (v && typeof v === 'object') return v
  } catch (e) {}
  return {}
}

function write(testResultId, obj) {
  try {
    wx.setStorageSync(storageKey(testResultId), obj || {})
  } catch (e) {}
}

function isStep1Unlocked({ profileGate, payRequired, isPaid }) {
  if (profileGate) return false
  if (payRequired && !isPaid) return false
  return true
}

function markShared(testResultId) {
  const v = read(testResultId)
  v.sharedMoment = Date.now()
  write(testResultId, v)
}

/**
 * @param {{ profileGate: boolean, payRequired: boolean, isPaid: boolean }} ctx
 * @param {string|number} testResultId
 * @returns {{ step1Unlocked: boolean, step2Unlocked: boolean, activeStep: number }}
 */
function computeJourney(ctx, testResultId) {
  const s1 = isStep1Unlocked(ctx)
  const s2 = s1 && !!read(testResultId).sharedMoment
  let activeStep = 1
  if (!s1) activeStep = 1
  else if (!s2) activeStep = 2
  else activeStep = 0
  return {
    step1Unlocked: s1,
    step2Unlocked: s2,
    activeStep
  }
}

module.exports = {
  computeJourney,
  markShared,
  isStep1Unlocked
}
