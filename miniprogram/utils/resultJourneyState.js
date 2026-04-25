/**
 * 结果页「按步骤解锁」状态：
 *   Step 1 · 看全文（需手机号+头像+昵称齐全，或已付费）
 *   Step 2 · 分享朋友圈（Step 1 完成后启用）
 *   Step 3 · AI 拍照测试（Step 2 完成后启用）
 *
 * 状态持久化到本地 storage：
 *   mbti_journey_unlocks = { sharedMoment: ts, faceCamera: ts }
 * 不依赖后端。
 */

const KEY = 'mbti_journey_unlocks'

function read() {
  try {
    const v = wx.getStorageSync(KEY)
    if (v && typeof v === 'object') return v
  } catch (e) {}
  return {}
}

function write(obj) {
  try {
    wx.setStorageSync(KEY, obj || {})
  } catch (e) {}
}

/**
 * Step 1 是否已解锁
 * 条件：资料已齐（profileGate===false，与 isReportProfileComplete 一致）且（免费或已付费）
 */
function isStep1Unlocked({ profileGate, payRequired, isPaid }) {
  if (profileGate) return false
  if (payRequired && !isPaid) return false
  return true
}

function isStep2Unlocked(ctx) {
  if (!isStep1Unlocked(ctx)) return false
  return !!read().sharedMoment
}

function isStep3Unlocked(ctx) {
  if (!isStep2Unlocked(ctx)) return false
  return !!read().faceCamera
}

function markShared() {
  const v = read()
  v.sharedMoment = Date.now()
  write(v)
}

function markCamera() {
  const v = read()
  v.faceCamera = Date.now()
  write(v)
}

/**
 * 统一封装：返回 { step1, step2, step3, activeStep }
 * @param {{profileGate:boolean, payRequired:boolean, isPaid:boolean}} ctx
 */
function computeJourney(ctx) {
  const s1 = isStep1Unlocked(ctx)
  const s2 = s1 && !!read().sharedMoment
  const s3 = s2 && !!read().faceCamera
  let activeStep = 1
  if (!s1) activeStep = 1
  else if (!s2) activeStep = 2
  else if (!s3) activeStep = 3
  else activeStep = 0
  return {
    step1Unlocked: s1,
    step2Unlocked: s2,
    step3Unlocked: s3,
    activeStep
  }
}

module.exports = {
  computeJourney,
  markShared,
  markCamera,
  isStep1Unlocked,
  isStep2Unlocked,
  isStep3Unlocked
}
