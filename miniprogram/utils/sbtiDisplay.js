/**
 * SBTI 展示辅助：为十五维度补 emoji、简短英文代号、进度百分比
 */
const DIM_ICONS = {
  S1: '🪞', S2: '🧭', S3: '🚀',
  E1: '💌', E2: '💞', E3: '🌱',
  A1: '🤝', A2: '🎯', A3: '🧠',
  C1: '📚', C2: '🕊️', C3: '🌟',
  So1: '👥', So2: '🌞', So3: '🧗'
}

const DIM_COLORS = {
  S: 'sbti-dim-color-s',
  E: 'sbti-dim-color-e',
  A: 'sbti-dim-color-a',
  C: 'sbti-dim-color-c',
  So: 'sbti-dim-color-so'
}

function pickColorClass(dim) {
  if (!dim) return DIM_COLORS.S
  if (dim.toUpperCase().startsWith('SO')) return DIM_COLORS.So
  const ch = dim.charAt(0).toUpperCase()
  return DIM_COLORS[ch] || DIM_COLORS.S
}

function decorateSbtiDims(list) {
  return (Array.isArray(list) ? list : []).map((item) => {
    const dim = item.dim || item.code || ''
    const level = Number(item.level) || 0
    const percent = Math.max(0, Math.min(100, Math.round((level / 6) * 100)))
    return {
      ...item,
      icon: DIM_ICONS[dim] || '🎯',
      colorClass: pickColorClass(dim),
      percent
    }
  })
}

const GROUP_TITLES = {
  S: '自我认同（S 系）',
  E: '亲密关系（E 系）',
  A: '外界交互（A 系）',
  C: '生活方式（C 系）',
  So: '社会参与（So 系）'
}

/**
 * 把装饰后的 15 维列表按首字母分组：S / E / A / C / So
 * @param {Array<object>} decoratedList
 * @returns {Array<{ key: string, title: string, items: Array }>}
 */
function groupSbtiDims(decoratedList) {
  const buckets = { S: [], E: [], A: [], C: [], So: [] }
  ;(Array.isArray(decoratedList) ? decoratedList : []).forEach((item) => {
    const dim = (item.dim || item.code || '').toUpperCase()
    if (dim.startsWith('SO')) buckets.So.push(item)
    else if (dim.startsWith('S')) buckets.S.push(item)
    else if (dim.startsWith('E')) buckets.E.push(item)
    else if (dim.startsWith('A')) buckets.A.push(item)
    else if (dim.startsWith('C')) buckets.C.push(item)
  })
  return Object.keys(buckets)
    .filter((k) => buckets[k].length > 0)
    .map((k) => ({ key: k, title: GROUP_TITLES[k] || k, items: buckets[k] }))
}

/**
 * 把长段落按中英文句号/分号切分为短段
 * @param {string} text
 * @param {number} maxCount
 * @returns {string[]}
 */
function splitSbtiDesc(text, maxCount) {
  const s = (text || '').trim()
  if (!s) return []
  const parts = s
    .replace(/\r/g, '')
    .split(/(?:。|；|！|!|；|\n)+/)
    .map((t) => t.trim())
    .filter((t) => t.length > 0)
  const limit = Number.isFinite(maxCount) && maxCount > 0 ? maxCount : 100
  return parts.slice(0, limit).map((t) => (t.endsWith('。') || t.endsWith('！') || t.endsWith('?') || t.endsWith('？')) ? t : t + '。')
}

module.exports = {
  decorateSbtiDims,
  groupSbtiDims,
  splitSbtiDesc
}
