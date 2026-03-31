/**
 * 测试结果摘要格式化：类型 + 整数百分比（与管理后台一致）
 */

function toIntPercent(value) {
  if (value == null) return 0
  const n = typeof value === 'number' ? value : Number(value)
  return Number.isFinite(n) ? Math.round(n) : 0
}

const PDP_EN_TO_CN = {
  Tiger: '老虎型',
  Peacock: '孔雀型',
  Koala: '考拉型',
  Owl: '猫头鹰型',
  Chameleon: '变色龙型'
}

function pdpOrderedFromScores(scores) {
  if (!scores || typeof scores !== 'object') return ['', '']
  const pairs = []
  for (const k of Object.keys(scores)) {
    if (!PDP_EN_TO_CN[k]) continue
    pairs.push([k, Number(scores[k]) || 0])
  }
  pairs.sort((a, b) => b[1] - a[1] || a[0].localeCompare(b[0]))
  return [pairs[0] ? pairs[0][0] : '', pairs[1] ? pairs[1][0] : '']
}

function discOrderedFromScores(scores) {
  if (!scores || typeof scores !== 'object') return ['', '']
  const allow = { D: 1, I: 1, S: 1, C: 1 }
  const pairs = []
  for (const k of Object.keys(scores)) {
    const u = String(k).trim().toUpperCase().charAt(0)
    if (!allow[u]) continue
    pairs.push([u, Number(scores[k]) || 0])
  }
  pairs.sort((a, b) => b[1] - a[1] || a[0].localeCompare(b[0]))
  return [pairs[0] ? pairs[0][0] : '', pairs[1] ? pairs[1][0] : '']
}

/** 与 scores 相同维度；DISC 接口常以 percentages 存四维而无 scores */
function discOrderedFromPercentages(pct) {
  return discOrderedFromScores(pct)
}

/** DISC：「S+I型」，仅最后一项带「型」 */
function discPrimaryLetter(data) {
  if (!data || typeof data !== 'object') return ''
  const dType = data.description && data.description.type
  if (typeof dType === 'string' && dType) {
    const noXing = String(dType).trim().replace(/型$/, '')
    if (noXing.length === 1) {
      const u = noXing.toUpperCase()
      if (['D', 'I', 'S', 'C'].includes(u)) return u
    }
  }
  if (data.dominantType) {
    const u = String(data.dominantType).trim().toUpperCase().charAt(0)
    if (['D', 'I', 'S', 'C'].includes(u)) return u
  }
  if (data.disc) {
    const noXing = String(data.disc).trim().replace(/型$/, '')
    if (noXing.length === 1) {
      const u = noXing.toUpperCase()
      if (['D', 'I', 'S', 'C'].includes(u)) return u
    }
  }
  return ''
}

function discResolveTwoLetters(data) {
  let f = discPrimaryLetter(data)
  let s = ''
  if (data.secondaryType) {
    const u = String(data.secondaryType).trim().toUpperCase().charAt(0)
    if (['D', 'I', 'S', 'C'].includes(u)) s = u
  }
  let a0 = ''
  let b = ''
  if (data.scores && typeof data.scores === 'object') {
    const ord = discOrderedFromScores(data.scores)
    a0 = ord[0] || ''
    b = ord[1] || ''
  }
  if (!a0 && !b && data.percentages && typeof data.percentages === 'object') {
    const ord = discOrderedFromPercentages(data.percentages)
    a0 = ord[0] || ''
    b = ord[1] || ''
  }
  if (!f && a0) f = a0
  if (!s || s === f) {
    if (b && b !== f) s = b
    else s = ''
  }
  return [f, s]
}

/** 旧接口/库存：「S型 + I型」「S型+I型」→ S+I型 */
function discNormalizeLegacyDualType(desc) {
  if (typeof desc !== 'string' || !desc) return ''
  const t = desc.replace(/\s+/g, '').replace(/\uFF0B/g, '+')
  let m = t.match(/^([DISC])型\+([DISC])型$/i)
  if (m) return m[1].toUpperCase() + '+' + m[2].toUpperCase() + '型'
  m = t.match(/^([DISC])型$/i)
  if (m) return m[1].toUpperCase() + '型'
  return ''
}

function discTopTwoLabel(data) {
  if (!data || typeof data !== 'object') return ''
  const [fL, sL] = discResolveTwoLetters(data)
  if (fL || sL) {
    if (!fL) return sL ? sL + '型' : ''
    if (!sL || sL === fL) return fL + '型'
    return fL + '+' + sL + '型'
  }
  return discNormalizeLegacyDualType(data.description && data.description.type)
}

function pdpPrimaryFull(data) {
  if (!data || typeof data !== 'object') return ''
  const desc = data.description && data.description.type
  if (typeof desc === 'string' && desc) return desc.trim()
  if (data.dominantType) {
    const key = String(data.dominantType).trim()
    return PDP_EN_TO_CN[key] || key
  }
  if (data.pdp) return String(data.pdp).trim()
  return ''
}

function pdpResolveTwoFull(data) {
  let f = pdpPrimaryFull(data)
  let s = ''
  if (data.secondaryType) {
    const k = String(data.secondaryType).trim()
    s = PDP_EN_TO_CN[k] || k
  }
  let aEn = ''
  let bEn = ''
  if (data.scores && typeof data.scores === 'object') {
    const ord = pdpOrderedFromScores(data.scores)
    aEn = ord[0] || ''
    bEn = ord[1] || ''
  }
  if (!aEn && !bEn && data.percentages && typeof data.percentages === 'object') {
    const ord = pdpOrderedFromScores(data.percentages)
    aEn = ord[0] || ''
    bEn = ord[1] || ''
  }
  if (!f && aEn) f = PDP_EN_TO_CN[aEn] || aEn
  if (!s || s === f) {
    if (bEn) {
      const cand = PDP_EN_TO_CN[bEn] || bEn
      if (cand !== f) s = cand
    }
  }
  return [f, s]
}

/** PDP：「孔雀+老虎型」，仅最后一项带「型」 */
function pdpTopTwoLabel(data) {
  if (!data || typeof data !== 'object') return ''
  const [ff, sf] = pdpResolveTwoFull(data)
  if (!ff) return sf || ''
  if (!sf || sf === ff) return ff
  const short = String(ff).replace(/型$/, '')
  return short + '+' + sf
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
    const two = discTopTwoLabel(data)
    const desc = data.description && data.description.type
    const label = two || ((typeof desc === 'string' && desc) ? desc : ((data.dominantType ? data.dominantType + '型' : '') || (data.disc || '')))
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
    const two = pdpTopTwoLabel(data)
    const desc = data.description && data.description.type
    const label = two || ((typeof desc === 'string' && desc) ? desc : (data.dominantType || data.pdp || ''))
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
    const two = discTopTwoLabel(data)
    if (two) return two
    const desc = data.description?.type
    if (typeof desc === 'string' && desc) return desc
    if (data.dominantType) return String(data.dominantType) + '型'
    return String(data.disc ?? '')
  }
  if (t === 'pdp') {
    const two = pdpTopTwoLabel(data)
    if (two) return two
    const desc = data.description?.type
    if (typeof desc === 'string' && desc) return desc
    if (data.dominantType) {
      const k = String(data.dominantType).trim()
      return PDP_EN_TO_CN[k] || k
    }
    return String(data.pdp ?? '')
  }
  return ''
}

module.exports = {
  toIntPercent,
  formatTestSummary,
  getTypeOnly,
  discTopTwoLabel,
  pdpTopTwoLabel
}
