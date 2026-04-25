/**
 * 神仙 AI：根据用户输入识别跳转「我的」同源能力（匹配工作 / 了解自己 / 性格测试）
 */

function detectJobIntent(text) {
  const s = String(text || '').trim()
  if (!s) return false
  return (
    /工作|求职|就业|职业|岗位|职位|面试|跳槽|转行|offer|薪资|薪水|上班|打工|应聘|招聘|简历|适合.+工作|找.+工作|干什么工作|做什么工作|哪类工作|啥工作|职场|事业|工种/i.test(
      s
    ) || /适合什么样|适合什么类型|MBTI.+工作|性格.+工作/i.test(s)
  )
}

function detectSelfKnowIntent(text) {
  const s = String(text || '').trim()
  if (!s) return false
  return /了解自己|认识自己|认识自我|内在|潜能|我是什么样|我是谁|读懂自己|深度了解|真实的我|本心|自我认知|探索自己|看清自己|个性|人格|我的特点|我是怎样的人/i.test(
    s
  )
}

/** 用户明确想做题 / 测评（与「了解自己」泛聊区分） */
function detectPersonalityTestIntent(text) {
  const s = String(text || '').trim()
  if (!s) return false
  return /性格测试|人格测试|MBTI测试|测MBTI|做测评|做测试|测一测|问卷|量表|测题|测一下|重新测|再测/i.test(s)
}

/**
 * 求职 > 测评 > 了解自己
 * @returns {'job'|'test'|'self'|null}
 */
function resolveChatFeatureIntent(text, hasMbtiType) {
  const s = String(text || '').trim()
  if (!s) return null
  if (detectJobIntent(s)) return 'job'
  if (detectPersonalityTestIntent(s)) return 'test'
  if (detectSelfKnowIntent(s)) return 'self'
  return null
}

/** 助手回复是否与该功能意图相关（避免文不对题的推荐卡片） */
function replyMatchesFeatureIntent(intent, assistantText) {
  const t = String(assistantText || '').trim()
  if (!t) return false
  if (intent === 'job') {
    return /工作|职业|岗位|求职|就业|职场|行业|方向|规划|面试|转行|技能|发展|适配|资源|管理|团队|领导|MBTI|性格.*工作|适合.*工作/i.test(t)
  }
  if (intent === 'test') {
    return /测试|测评|MBTI|问卷|维度|类型|题目|结果|人格|性格|指标|信度|效度/i.test(t)
  }
  if (intent === 'self') {
    return /性格|人格|特质|优势|盲点|内在|情绪|状态|成长|了解自己|认识|适合|关系|伴侣|沟通|需求|模式/i.test(t)
  }
  return false
}

/**
 * 「了解自己」类：随机打开 了解自己(购买页) 或 性格测试(选测页)。
 * 尚无 MBTI 结果时略提高去「性格测试」的概率，便于先测评。
 * @param {boolean} hasMbtiType 是否已有 MBTI 类型（与 ai-chat 页 mbtiType 一致）
 * @returns {string} 小程序 path
 */
function pickSelfServicePath(hasMbtiType) {
  try {
    const app = getApp()
    if (app && app.globalData && app.globalData.miniprogramAuditMode) {
      return '/pages/test-select/index'
    }
  } catch (e) {}
  const r = Math.random()
  if (!hasMbtiType) {
    return r < 0.65 ? '/pages/test-select/index' : '/pages/purchase/index'
  }
  return r < 0.5 ? '/pages/purchase/index' : '/pages/test-select/index'
}

/**
 * 阅读数展示：8630 + 距发布日天数×50；≥1万 显示为「x万+」
 * @param {string} publishedAt 如 2026-04-17
 */
function formatInlineReadLabel(publishedAt) {
  const s = String(publishedAt || '').trim()
  let ts = s ? Date.parse(s.replace(/\./g, '-')) : NaN
  if (Number.isNaN(ts)) ts = Date.now()
  const days = Math.max(0, Math.floor((Date.now() - ts) / 86400000))
  const raw = 8630 + days * 50
  if (raw >= 10000) {
    const wan = raw / 10000
    const rounded = Math.round(wan * 10) / 10
    return `${rounded}万+`
  }
  return `${raw}阅读`
}

module.exports = {
  detectJobIntent,
  detectSelfKnowIntent,
  detectPersonalityTestIntent,
  resolveChatFeatureIntent,
  replyMatchesFeatureIntent,
  pickSelfServicePath,
  formatInlineReadLabel
}
