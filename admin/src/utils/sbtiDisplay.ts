/**
 * SBTI 展示与类型图 URL（与小程序 miniprogram/utils/sbtiData.js TYPE_IMAGES 一致）
 */
export const SBTI_TYPE_IMAGES: Record<string, string> = {
  IMSB: 'https://karuosiyujzk.oss-cn-shenzhen.aliyuncs.com/mbti/SBTI/SBTI/IMSB.png',
  BOSS: 'https://karuosiyujzk.oss-cn-shenzhen.aliyuncs.com/mbti/SBTI/SBTI/BOSS.png',
  MUM: 'https://karuosiyujzk.oss-cn-shenzhen.aliyuncs.com/mbti/SBTI/SBTI/MUM.png',
  FAKE: 'https://karuosiyujzk.oss-cn-shenzhen.aliyuncs.com/mbti/SBTI/SBTI/FAKE.png',
  'Dior-s': 'https://karuosiyujzk.oss-cn-shenzhen.aliyuncs.com/mbti/SBTI/SBTI/Dior-s.jpg',
  DEAD: 'https://karuosiyujzk.oss-cn-shenzhen.aliyuncs.com/mbti/SBTI/SBTI/DEAD.png',
  ZZZZ: 'https://karuosiyujzk.oss-cn-shenzhen.aliyuncs.com/mbti/SBTI/SBTI/ZZZZ.png',
  GOGO: 'https://karuosiyujzk.oss-cn-shenzhen.aliyuncs.com/mbti/SBTI/SBTI/GOGO.png',
  FUCK: 'https://karuosiyujzk.oss-cn-shenzhen.aliyuncs.com/mbti/SBTI/SBTI/FUCK.png',
  CTRL: 'https://karuosiyujzk.oss-cn-shenzhen.aliyuncs.com/mbti/SBTI/SBTI/CTRL.png',
  HHHH: 'https://karuosiyujzk.oss-cn-shenzhen.aliyuncs.com/mbti/SBTI/SBTI/HHHH.png',
  SEXY: 'https://karuosiyujzk.oss-cn-shenzhen.aliyuncs.com/mbti/SBTI/SBTI/SEXY.png',
  OJBK: 'https://karuosiyujzk.oss-cn-shenzhen.aliyuncs.com/mbti/SBTI/SBTI/OJBK.png',
  'JOKE-R': 'https://karuosiyujzk.oss-cn-shenzhen.aliyuncs.com/mbti/SBTI/SBTI/JOKE-R.jpg',
  POOR: 'https://karuosiyujzk.oss-cn-shenzhen.aliyuncs.com/mbti/SBTI/SBTI/POOR.png',
  'OH-NO': 'https://karuosiyujzk.oss-cn-shenzhen.aliyuncs.com/mbti/SBTI/SBTI/OH-NO.png',
  MONK: 'https://karuosiyujzk.oss-cn-shenzhen.aliyuncs.com/mbti/SBTI/SBTI/MONK.png',
  SHIT: 'https://karuosiyujzk.oss-cn-shenzhen.aliyuncs.com/mbti/SBTI/SBTI/SHIT.png',
  'THAN-K': 'https://karuosiyujzk.oss-cn-shenzhen.aliyuncs.com/mbti/SBTI/SBTI/THAN-K.png',
  MALO: 'https://karuosiyujzk.oss-cn-shenzhen.aliyuncs.com/mbti/SBTI/SBTI/MALO.png',
  'ATM-er': 'https://karuosiyujzk.oss-cn-shenzhen.aliyuncs.com/mbti/SBTI/SBTI/ATM-er.png',
  'THIN-K': 'https://karuosiyujzk.oss-cn-shenzhen.aliyuncs.com/mbti/SBTI/SBTI/THIN-K.png',
  SOLO: 'https://karuosiyujzk.oss-cn-shenzhen.aliyuncs.com/mbti/SBTI/SBTI/SOLO.png',
  'LOVE-R': 'https://karuosiyujzk.oss-cn-shenzhen.aliyuncs.com/mbti/SBTI/SBTI/LOVE-R.png',
  'WOC!': 'https://karuosiyujzk.oss-cn-shenzhen.aliyuncs.com/mbti/SBTI/SBTI/WOC.png',
  DRUNK: 'https://karuosiyujzk.oss-cn-shenzhen.aliyuncs.com/mbti/SBTI/SBTI/DRUNK.png',
  IMFW: 'https://karuosiyujzk.oss-cn-shenzhen.aliyuncs.com/mbti/SBTI/SBTI/IMFW.png'
}

export function getSbtiCode(data: Record<string, any> | null | undefined): string {
  if (!data || typeof data !== 'object') return ''
  const direct = String(data.sbtiType ?? '').trim()
  if (direct) return direct
  const ft = data.finalType
  if (ft && typeof ft === 'object' && ft.code != null && String(ft.code).trim() !== '') {
    return String(ft.code).trim()
  }
  return String(data.code ?? '').trim()
}

export function getSbtiCn(data: Record<string, any> | null | undefined): string {
  if (!data || typeof data !== 'object') return ''
  const cn = String(data.sbtiCn ?? '').trim()
  if (cn) return cn
  const ft = data.finalType
  if (ft && typeof ft === 'object' && ft.cn != null && String(ft.cn).trim() !== '') {
    return String(ft.cn).trim()
  }
  return ''
}

/** 列表摘要、弹窗标题：CODE（中文名） */
export function formatSbtiSummary(data: Record<string, any> | null | undefined): string {
  const code = getSbtiCode(data)
  const cn = getSbtiCn(data)
  if (code && cn) return `${code}（${cn}）`
  if (code) return code
  if (cn) return cn
  return ''
}

export function sbtiTypeImageUrl(code: string): string {
  if (!code) return ''
  return SBTI_TYPE_IMAGES[code] || ''
}

/** 与小程序 `sbtiData.dimensionOrder` 一致，用于雷达图 15 轴 */
export const SBTI_RADAR_DIMENSION_ORDER = [
  'S1',
  'S2',
  'S3',
  'E1',
  'E2',
  'E3',
  'A1',
  'A2',
  'A3',
  'Ac1',
  'Ac2',
  'Ac3',
  'So1',
  'So2',
  'So3'
] as const

/**
 * 从 SBTI 结果 JSON 生成 15 维雷达数值（0～100）。
 * 优先 `rawScores`（每维两题分和约 2～6）；否则用 `levels` 的 L/M/H 映射。
 */
export function buildSbtiRadarValues(parsed: Record<string, any> | null | undefined): number[] | null {
  if (!parsed || typeof parsed !== 'object') return null
  const raw = parsed.rawScores as Record<string, unknown> | undefined
  const levels = parsed.levels as Record<string, unknown> | undefined
  const vals: number[] = []
  for (const dim of SBTI_RADAR_DIMENSION_ORDER) {
    let v: number | null = null
    if (raw && raw[dim] != null && raw[dim] !== '') {
      const s = Number(raw[dim])
      if (Number.isFinite(s)) {
        v = Math.round(((s - 2) / 4) * 100)
        v = Math.max(0, Math.min(100, v))
      }
    }
    if (v == null && levels && levels[dim] != null && levels[dim] !== '') {
      const lv = String(levels[dim]).toUpperCase()
      if (lv === 'L') v = 34
      else if (lv === 'M') v = 67
      else if (lv === 'H') v = 100
      else v = 50
    }
    if (v == null) return null
    vals.push(v)
  }
  return vals
}
