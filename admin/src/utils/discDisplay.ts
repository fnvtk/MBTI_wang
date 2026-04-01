/**
 * 管理后台 DISC 展示：双字母摘要与「力量 / 活跃 / 和平 / 完美」称谓。
 * 逻辑对齐 miniprogram/utils/resultFormat.js 与 api PdpDiscResultText::discResolveTwoLetters。
 */

export const DISC_STYLE_NAMES: Record<string, string> = {
  D: '力量',
  I: '活跃',
  S: '和平',
  C: '完美'
}

const ALLOW = new Set(['D', 'I', 'S', 'C'])

function discOrderedFromScores(scores: Record<string, unknown> | null | undefined): [string, string] {
  if (!scores || typeof scores !== 'object') return ['', '']
  const pairs: [string, number][] = []
  for (const k of Object.keys(scores)) {
    const u = String(k).trim().toUpperCase().charAt(0)
    if (!ALLOW.has(u)) continue
    pairs.push([u, Number(scores[k]) || 0])
  }
  pairs.sort((a, b) => b[1] - a[1] || a[0].localeCompare(b[0]))
  return [pairs[0]?.[0] ?? '', pairs[1]?.[0] ?? '']
}

export function discPrimaryLetter(data: any): string {
  if (!data || typeof data !== 'object') return ''
  const dType = data.description?.type
  if (typeof dType === 'string' && dType) {
    const noXing = String(dType).trim().replace(/型$/, '')
    if (noXing.length === 1) {
      const u = noXing.toUpperCase()
      if (ALLOW.has(u)) return u
    }
  }
  if (data.dominantType) {
    const u = String(data.dominantType).trim().toUpperCase().charAt(0)
    if (ALLOW.has(u)) return u
  }
  if (data.disc) {
    const noXing = String(data.disc).trim().replace(/型$/, '')
    if (noXing.length === 1) {
      const u = noXing.toUpperCase()
      if (ALLOW.has(u)) return u
    }
  }
  return ''
}

export function discResolveTwoLetters(data: any): [string, string] {
  let f = discPrimaryLetter(data)
  let s = ''
  if (data.secondaryType) {
    const u = String(data.secondaryType).trim().toUpperCase().charAt(0)
    if (ALLOW.has(u)) s = u
  }
  let a0 = ''
  let b = ''
  if (data.scores && typeof data.scores === 'object') {
    const ord = discOrderedFromScores(data.scores as Record<string, unknown>)
    a0 = ord[0] || ''
    b = ord[1] || ''
  }
  if (!a0 && !b && data.percentages && typeof data.percentages === 'object') {
    const ord = discOrderedFromScores(data.percentages as Record<string, unknown>)
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

export function discNormalizeLegacyDualType(desc: unknown): string {
  if (typeof desc !== 'string' || !desc) return ''
  const t = desc.replace(/\s+/g, '').replace(/\uFF0B/g, '+')
  let m = t.match(/^([DISC])型\+([DISC])型$/i)
  if (m) return m[1].toUpperCase() + '+' + m[2].toUpperCase() + '型'
  m = t.match(/^([DISC])型$/i)
  if (m) return m[1].toUpperCase() + '型'
  return ''
}

export function discTopTwoLabel(data: any): string {
  if (!data || typeof data !== 'object') return ''
  const [fL, sL] = discResolveTwoLetters(data)
  if (fL || sL) {
    if (!fL) return sL ? sL + '型' : ''
    if (!sL || sL === fL) return fL + '型'
    return fL + '+' + sL + '型'
  }
  return discNormalizeLegacyDualType(data.description?.type)
}

/** 副标题：单型「完美」，双型「完美 · 力量」 */
export function discStyleSubtitle(data: any): string {
  if (!data || typeof data !== 'object') return ''
  const [f, s] = discResolveTwoLetters(data)
  const nf = f ? DISC_STYLE_NAMES[f] ?? '' : ''
  const ns = s ? DISC_STYLE_NAMES[s] ?? '' : ''
  if (!nf && !ns) return ''
  if (!ns || s === f) return nf
  return `${nf} · ${ns}`
}

/** 柱状图左侧：D（力量） */
export function discDimensionLabel(letter: string): string {
  const u = String(letter).trim().toUpperCase().charAt(0)
  const name = DISC_STYLE_NAMES[u]
  return name ? `${u}（${name}）` : u || letter
}

/** 仅风格名，用于与字母分开展示 */
export function discStyleName(letter: string): string {
  const u = String(letter).trim().toUpperCase().charAt(0)
  return DISC_STYLE_NAMES[u] ?? ''
}

/** 雷达轴等紧凑单行：D·力量 */
export function discCompactLabel(letter: string): string {
  const u = String(letter).trim().toUpperCase().charAt(0)
  const n = DISC_STYLE_NAMES[u]
  return n ? `${u}·${n}` : u || letter
}
