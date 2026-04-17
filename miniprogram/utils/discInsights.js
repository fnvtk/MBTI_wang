/**
 * DISC 深度洞察：主类型 → 心智模式 / 决策倾向 / 能量来源 / 压力反应
 * 支持单字母主类型（D/I/S/C）与组合（如 "S+I"），取首字母作为主导。
 */

// 8 维：心智 / 决策 / 能量 / 压力 / 沟通 / 领导 / 恋爱 / 团队角色
const DISC_INSIGHTS = {
  D: { mind: '力量型 · 动能主导', decision: '目标·速决',   energy: '挑战·掌控',   stress: '压迫他人',   comm: '直接',     lead: '铁腕推进', love: '保护欲强', team: '业务开拓' },
  I: { mind: '活跃型 · 影响主导', decision: '氛围·直觉',   energy: '社交·舞台',   stress: '注意力分散', comm: '热情表达', lead: '激励型',   love: '浪漫热烈', team: '气氛担当' },
  S: { mind: '和平型 · 稳定主导', decision: '共识·耐心',   energy: '关系·节奏',   stress: '回避冲突',   comm: '温和倾听', lead: '稳定协调', love: '默默陪伴', team: '人和支持' },
  C: { mind: '完美型 · 分析主导', decision: '规则·数据',   energy: '独立·细节',   stress: '过度自省',   comm: '精准严谨', lead: '标准驱动', love: '精选长期', team: '质量把关' }
}

const DISC_TAGS = {
  D: ['高执行', '目标感', '控制'],
  I: ['影响力', '乐观', '高社交'],
  S: ['可靠', '稳定', '协作'],
  C: ['严谨', '分析', '系统']
}

const DISC_END_LABELS = {
  D: { label: '力量 D', emoji: '🔥', color: '#ef4444', desc: '主导型：行动、速度、结果' },
  I: { label: '活跃 I', emoji: '🎉', color: '#f59e0b', desc: '影响型：表达、社交、氛围' },
  S: { label: '和平 S', emoji: '🌿', color: '#10b981', desc: '稳定型：耐心、协作、节奏' },
  C: { label: '完美 C', emoji: '🎯', color: '#3b82f6', desc: '严谨型：分析、规则、数据' }
}

function toFirstLetter(code) {
  if (!code) return ''
  return String(code).trim().toUpperCase().charAt(0)
}

function getDiscInsight(code) {
  const k = toFirstLetter(code)
  return DISC_INSIGHTS[k] || null
}

function getDiscTags(code) {
  const k = toFirstLetter(code)
  return DISC_TAGS[k] || []
}

function getDiscEndMeta(letter) {
  return DISC_END_LABELS[letter] || null
}

function buildDiscDimensions(result) {
  const p = (result && result.percentages) || {}
  const pi = (result && result.percentagesInt) || {}
  return ['D', 'I', 'S', 'C'].map((k) => {
    const meta = DISC_END_LABELS[k]
    return {
      key: k,
      label: meta.label,
      emoji: meta.emoji,
      desc: meta.desc,
      dashClass: 'dash-fill--disc-' + k.toLowerCase(),
      percentage: pi[k] != null ? pi[k] : (p[k] != null ? Math.round(Number(p[k]) || 0) : 0),
      raw: p[k] != null ? Number(p[k]) || 0 : (pi[k] != null ? pi[k] : 0)
    }
  })
}

module.exports = {
  getDiscInsight,
  getDiscTags,
  getDiscEndMeta,
  buildDiscDimensions
}
