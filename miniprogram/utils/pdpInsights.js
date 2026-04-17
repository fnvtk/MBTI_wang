/**
 * PDP 深度洞察：主导动物型 → 心智模式 / 决策倾向 / 能量来源 / 压力反应
 */

// 8 维：心智 / 决策 / 能量 / 压力 / 沟通 / 领导 / 恋爱 / 团队角色
const PDP_INSIGHTS = {
  Tiger:     { mind: '老虎型 · 支配主导',     decision: '速断·结果',   energy: '挑战·夺冠',   stress: '强硬控场',   comm: '命令直接', lead: '果敢铁腕', love: '保护占有', team: '业务指挥' },
  Peacock:   { mind: '孔雀型 · 表达主导',     decision: '氛围·直觉',   energy: '舞台·社交',   stress: '情绪放大',   comm: '热情感染', lead: '激励型',   love: '浪漫热烈', team: '气氛担当' },
  Koala:     { mind: '无尾熊型 · 支持主导',   decision: '共识·稳妥',   energy: '关系·节奏',   stress: '回避冲突',   comm: '温和聆听', lead: '幕后协调', love: '默默陪伴', team: '人和黏合' },
  Owl:       { mind: '猫头鹰型 · 分析主导',   decision: '数据·规则',   energy: '独立·深研',   stress: '过度完美',   comm: '精准严谨', lead: '标准驱动', love: '精选理性', team: '专家深研' },
  Chameleon: { mind: '变色龙型 · 整合主导',   decision: '平衡·权变',   energy: '多场景切换', stress: '定位模糊',   comm: '灵活变化', lead: '多面手',   love: '契合环境', team: '跨界整合' }
}

const PDP_TAGS = {
  Tiger: ['目标感', '高执行', '控制'],
  Peacock: ['影响力', '乐观', '表达'],
  Koala: ['可靠', '耐心', '协作'],
  Owl: ['严谨', '分析', '系统'],
  Chameleon: ['灵活', '适应', '整合']
}

const PDP_META = {
  Tiger:     { emoji: '🐅', label: '老虎 · 支配',   desc: '行动果断、目标导向' },
  Peacock:   { emoji: '🦚', label: '孔雀 · 表达',   desc: '富有感染力、善于社交' },
  Koala:     { emoji: '🐨', label: '无尾熊 · 支持', desc: '稳定可靠、照顾团队' },
  Owl:       { emoji: '🦉', label: '猫头鹰 · 分析', desc: '注重逻辑、追求精准' },
  Chameleon: { emoji: '🦎', label: '变色龙 · 整合', desc: '灵活平衡、多面手' }
}

function toKey(code) {
  const s = (code || '').trim()
  if (!s) return ''
  const up = s.charAt(0).toUpperCase() + s.slice(1).toLowerCase()
  return PDP_INSIGHTS[up] ? up : ''
}

function getPdpInsight(code) {
  const k = toKey(code)
  return PDP_INSIGHTS[k] || null
}

function getPdpTags(code) {
  const k = toKey(code)
  return PDP_TAGS[k] || []
}

function buildPdpDimensions(result) {
  const pi = (result && result.percentagesInt) || {}
  const p = (result && result.percentages) || {}
  return ['Tiger', 'Peacock', 'Koala', 'Owl', 'Chameleon'].map((k) => {
    const meta = PDP_META[k]
    return {
      key: k,
      label: meta.label,
      emoji: meta.emoji,
      desc: meta.desc,
      dashClass: 'dash-fill--pdp-' + k.toLowerCase(),
      percentage: pi[k] != null ? pi[k] : (p[k] != null ? Math.round(Number(p[k]) || 0) : 0)
    }
  })
}

module.exports = {
  getPdpInsight,
  getPdpTags,
  buildPdpDimensions
}
