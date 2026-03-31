/**
 * 人脸 / AI 分析结果：与微信小程序 pages/index/result 展示字段对齐，供后台「测试详情」使用
 */

export interface FaceFeatureItem {
  label: string
  description: string
}

export interface FaceBoneIceSummary {
  elementType?: string
  boneFleshRelation?: string
}

export interface FaceDetailView {
  photos: string[]
  mbti: { type?: string; title?: string } | null
  disc: { primary?: string; secondary?: string } | null
  pdp: { primary?: string; secondary?: string } | null
  overview: string
  personalitySummary: string
  /** 字符串版面相（与小程序 faceAnalysisText） */
  faceAnalysisText: string
  /** 结构化面相：面部特征列表 */
  faceFeatures: FaceFeatureItem[]
  /** 字符串版骨相正文 */
  boneAnalysisText: string
  /** 《冰鉴》结构化摘要（小程序 boneFormSummary） */
  boneIceSummary: FaceBoneIceSummary | null
  advantages: string[]
  gallupTop3: string[]
  relationship: string
  careerDevelopment: string
  familyParenting: string
  partnerCofounder: string
  portrait: Record<string, any> | null
  hrView: Record<string, any> | null
  bossView: Record<string, any> | null
  resumeHighlights: string
  careers: string[]
}

function collectPhotoUrls(parsed: Record<string, any>): string[] {
  const out: string[] = []
  const pushUrl = (s: string) => {
    const t = s.trim()
    if (t && (t.startsWith('http://') || t.startsWith('https://'))) out.push(t)
  }
  const pushArr = (v: unknown) => {
    if (!Array.isArray(v)) return
    for (const x of v) {
      if (typeof x === 'string') pushUrl(x)
    }
  }
  pushArr(parsed.photoUrls)
  pushArr(parsed.photos)
  pushArr(parsed.imageUrls)
  for (const k of ['photoUrl', 'imageUrl', 'faceImageUrl'] as const) {
    const u = parsed[k]
    if (typeof u === 'string') pushUrl(u)
  }
  return [...new Set(out)]
}

function isPlaceholderTitle(s: string): boolean {
  return !s || s === '—' || s === '-' || s === '–' || s === '--'
}

function normalizeMbtiBlock(parsed: Record<string, any>): { type?: string; title?: string } | null {
  const m = parsed.mbti
  if (m && typeof m === 'object' && !Array.isArray(m)) {
    const o = m as { type?: string; title?: string }
    const type = o.type != null ? String(o.type).trim() : ''
    let title = o.title != null ? String(o.title).trim() : ''
    if (isPlaceholderTitle(title)) title = ''
    if (!type) return null
    return title ? { type, title } : { type }
  }
  const typeOnly =
    (typeof m === 'string' && m.trim() ? m.trim() : '') ||
    String(parsed.mbtiType ?? parsed.mbti_type ?? '').trim()
  if (!typeOnly) return null
  let titleRaw = String(parsed.mbtiTitle ?? parsed.title ?? '').trim()
  if (isPlaceholderTitle(titleRaw)) titleRaw = ''
  return titleRaw ? { type: typeOnly, title: titleRaw } : { type: typeOnly }
}

function normalizeDiscBlock(parsed: Record<string, any>): { primary?: string; secondary?: string } | null {
  const d = parsed.disc
  if (d && typeof d === 'object' && !Array.isArray(d)) {
    return d as { primary?: string; secondary?: string }
  }
  if (typeof d === 'string' && d.trim()) {
    return { primary: d.trim() }
  }
  return null
}

function normalizePdpBlock(parsed: Record<string, any>): { primary?: string; secondary?: string } | null {
  const p = parsed.pdp
  if (p && typeof p === 'object' && !Array.isArray(p)) {
    return p as { primary?: string; secondary?: string }
  }
  if (typeof p === 'string' && p.trim()) {
    return { primary: p.trim() }
  }
  return null
}

function parseMaybeJsonObject(raw: unknown): Record<string, any> | null {
  if (raw && typeof raw === 'object' && !Array.isArray(raw)) {
    return raw as Record<string, any>
  }
  if (typeof raw === 'string' && raw.trim().startsWith('{')) {
    try {
      const o = JSON.parse(raw) as unknown
      return o && typeof o === 'object' && !Array.isArray(o) ? (o as Record<string, any>) : null
    } catch {
      return null
    }
  }
  return null
}

function extractFaceFeatures(raw: unknown): FaceFeatureItem[] {
  const o = parseMaybeJsonObject(raw)
  if (!o) return []
  const fe = o.facialFeatures
  if (!Array.isArray(fe)) return []
  return fe
    .map((item: any) => ({
      label: String(item?.label ?? ''),
      description: String(item?.description ?? '')
    }))
    .filter((f) => f.label || f.description)
}

function extractBoneIceSummary(raw: unknown): FaceBoneIceSummary | null {
  const o = parseMaybeJsonObject(raw)
  const sum = o?.boneFormSummary
  if (sum && typeof sum === 'object') {
    return {
      elementType: sum.elementType != null ? String(sum.elementType) : undefined,
      boneFleshRelation: sum.boneFleshRelation != null ? String(sum.boneFleshRelation) : undefined
    }
  }
  return null
}

/** 从 AI 结果 JSON 构建后台展示模型（兼容字符串 / 结构化 / JSON 字符串） */
export function buildFaceDetailFromParsed(parsed: Record<string, any> | null | undefined): FaceDetailView | null {
  if (!parsed || typeof parsed !== 'object') return null

  const faceRaw = parsed.faceAnalysis
  const boneRaw = parsed.boneAnalysis

  const faceFeatures = extractFaceFeatures(faceRaw)
  let faceAnalysisText = ''
  if (typeof faceRaw === 'string') {
    const tryObj = parseMaybeJsonObject(faceRaw)
    if (tryObj && Array.isArray(tryObj.facialFeatures) && tryObj.facialFeatures.length) {
      faceAnalysisText = ''
    } else {
      faceAnalysisText = faceRaw.trim()
    }
  }

  const boneIceSummary = extractBoneIceSummary(boneRaw)
  let boneAnalysisText = ''
  if (typeof boneRaw === 'string') {
    const tryObj = parseMaybeJsonObject(boneRaw)
    if (tryObj?.boneFormSummary && typeof tryObj.boneFormSummary === 'object') {
      boneAnalysisText = ''
    } else {
      boneAnalysisText = boneRaw.trim()
    }
  }

  const strArr = (v: unknown): string[] =>
    Array.isArray(v) ? (v as unknown[]).map((x) => String(x)).filter(Boolean) : []

  return {
    photos: collectPhotoUrls(parsed),
    mbti: normalizeMbtiBlock(parsed),
    disc: normalizeDiscBlock(parsed),
    pdp: normalizePdpBlock(parsed),
    overview: String(parsed.overview ?? ''),
    personalitySummary: String(parsed.personalitySummary ?? ''),
    faceAnalysisText,
    faceFeatures,
    boneAnalysisText,
    boneIceSummary,
    advantages: strArr(parsed.advantages),
    gallupTop3: strArr(parsed.gallupTop3),
    relationship: String(parsed.relationship ?? ''),
    careerDevelopment: String(parsed.careerDevelopment ?? ''),
    familyParenting: String(parsed.familyParenting ?? ''),
    partnerCofounder: String(parsed.partnerCofounder ?? ''),
    portrait: parsed.portrait && typeof parsed.portrait === 'object' ? parsed.portrait : null,
    hrView: parsed.hrView && typeof parsed.hrView === 'object' ? parsed.hrView : null,
    bossView: parsed.bossView && typeof parsed.bossView === 'object' ? parsed.bossView : null,
    resumeHighlights: String(parsed.resumeHighlights ?? ''),
    careers: strArr(parsed.careers)
  }
}
