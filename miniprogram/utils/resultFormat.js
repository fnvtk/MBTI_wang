/**
 * 测试结果摘要格式化：类型 + 整数百分比（与管理后台一致）
 */

function toIntPercent(value) {
  if (value == null) return 0
  const n = typeof value === 'number' ? value : Number(value)
  return Number.isFinite(n) ? Math.round(n) : 0
}

/**
 * 根据测试类型和原始结果，生成带整数百分比的摘要文案
 * @param {object} data - 单条测试结果（mbtiResult / discResult / pdpResult）
 * @param {string} testType - 'mbti' | 'disc' | 'pdp'
 * @returns {string}
 */
function formatTestSummary(data, testType) {
  if (!data || typeof data !== 'object') return ''
  const t = (testType || '').toLowerCase()

  if (t === 'mbti') {
    const label = data.mbtiType || data.type || data.result || ''
    const dims = data.dimensionScores
    if (dims && typeof dims === 'object') {
      const parts = []
      const order = ['EI', 'SN', 'TF', 'JP']
      for (const key of order) {
        const pct = dims[key] && (dims[key].percentage != null ? dims[key].percentage : dims[key].dominant)
        if (pct != null) parts.push(toIntPercent(pct) + '%')
      }
      if (parts.length) return label + ' (' + parts.join(' ') + ')'
    }
    return String(label)
  }

  if (t === 'disc') {
    const desc = data.description && data.description.type
    const label = (typeof desc === 'string' && desc) ? desc : ((data.dominantType ? data.dominantType + '型' : '') || (data.disc || ''))
    const pct = data.percentages
    if (pct && typeof pct === 'object') {
      const d = toIntPercent(pct.D != null ? pct.D : pct.d)
      const i = toIntPercent(pct.I != null ? pct.I : pct.i)
      const s = toIntPercent(pct.S != null ? pct.S : pct.s)
      const c = toIntPercent(pct.C != null ? pct.C : pct.c)
      return label + ' D:' + d + '% I:' + i + '% S:' + s + '% C:' + c + '%'
    }
    return label || ''
  }

  if (t === 'pdp') {
    const desc = data.description && data.description.type
    const label = (typeof desc === 'string' && desc) ? desc : (data.dominantType || data.pdp || '')
    const pct = data.percentages
    if (pct && typeof pct === 'object') {
      const names = { Tiger: '老虎', Peacock: '孔雀', Owl: '猫头鹰', Koala: '考拉', Chameleon: '变色龙' }
      const parts = []
      for (const [key, name] of Object.entries(names)) {
        const v = pct[key] != null ? pct[key] : pct[key.toLowerCase()]
        if (v != null) parts.push(name + ':' + toIntPercent(v) + '%')
      }
      if (parts.length) return label + ' ' + parts.join(' ')
    }
    return label || ''
  }

  return ''
}

/**
 * 仅返回类型标签（无百分比），用于列表、个人中心等
 */
function getTypeOnly(data, testType) {
  if (!data || typeof data !== 'object') return ''
  const t = (testType || '').toLowerCase()
  if (t === 'mbti') return String(data.mbtiType ?? data.type ?? data.result ?? '')
  if (t === 'disc') {
    const desc = data.description?.type
    if (typeof desc === 'string' && desc) return desc
    if (data.dominantType) return String(data.dominantType) + '型'
    return String(data.disc ?? '')
  }
  if (t === 'pdp') {
    const desc = data.description?.type
    if (typeof desc === 'string' && desc) return desc
    if (data.dominantType) return String(data.dominantType)
    return String(data.pdp ?? '')
  }
  return ''
}

module.exports = {
  toIntPercent,
  formatTestSummary,
  getTypeOnly
}
