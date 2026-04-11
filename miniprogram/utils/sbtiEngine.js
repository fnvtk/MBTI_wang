/**
 * SBTI 计分与匹配（与 aisbti.com 公开页逻辑一致）
 * @param {Array<{id:number,dimension:string,question:string,options:Array}>} questions 题库全部行（含 DG1/DG2）
 * @param {Record<number|string, number>} answers questionId -> 选项 value
 */
const data = require('./sbtiData.js')

/** 每维 2 题、每题 1～3 分 → 分和约 2～6；据此划 L/M/H（非「满分 6 分」） */
function sumToLevel(score) {
  if (score <= 3) return 'L'
  if (score === 4) return 'M'
  return 'H'
}

function levelNum(level) {
  return { L: 1, M: 2, H: 3 }[level] || 1
}

function parsePattern(pattern) {
  return pattern.replace(/-/g, '').split('')
}

function shuffle(arr) {
  const a = arr.slice()
  for (let i = a.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1))
    ;[a[i], a[j]] = [a[j], a[i]]
  }
  return a
}

/**
 * 组卷：打乱计分题，随机插入爱好闸口；饮酒时出现 DG2（由页面在 DG1=3 时插入）
 */
function buildShuffledPaper(allQuestions) {
  const scoring = allQuestions.filter((q) => q.dimension && !['DG1', 'DG2'].includes(q.dimension))
  const dg1 = allQuestions.find((q) => q.dimension === 'DG1')
  const dg2 = allQuestions.find((q) => q.dimension === 'DG2')
  const shuffled = shuffle(scoring)
  const insertIndex = Math.floor(Math.random() * shuffled.length) + 1
  const ordered = [...shuffled.slice(0, insertIndex)]
  if (dg1) ordered.push(dg1)
  ordered.push(...shuffled.slice(insertIndex))
  return { ordered, dg1, dg2 }
}

/** 当前可见题序：选「饮酒」后在闸口题后插入 DG2 */
function getVisibleQuestions(ordered, answers, dg2) {
  const visible = ordered.slice()
  if (!dg2) return visible
  if (visible.some((q) => q.id === dg2.id)) return visible
  const gateIdx = visible.findIndex((q) => q.dimension === 'DG1')
  if (gateIdx !== -1 && Number(answers[visible[gateIdx].id]) === 3) {
    visible.splice(gateIdx + 1, 0, dg2)
  }
  return visible
}

function computeSbtiResult(questions, answers) {
  const { TYPE_LIBRARY, NORMAL_TYPES, DIM_EXPLANATIONS, dimensionOrder } = data
  const rawScores = {}
  dimensionOrder.forEach((dim) => {
    rawScores[dim] = 0
  })

  questions.forEach((q) => {
    const dim = q.dimension
    if (!dim || dim === 'DG1' || dim === 'DG2') return
    if (!dimensionOrder.includes(dim)) return
    const v = answers[q.id]
    if (v == null || v === '') return
    rawScores[dim] += Number(v) || 0
  })

  const levels = {}
  Object.entries(rawScores).forEach(([dim, score]) => {
    levels[dim] = sumToLevel(score)
  })

  const userVector = dimensionOrder.map((dim) => levelNum(levels[dim]))
  const ranked = NORMAL_TYPES.map((type) => {
    const vector = parsePattern(type.pattern).map(levelNum)
    let distance = 0
    let exact = 0
    for (let i = 0; i < vector.length; i++) {
      const diff = Math.abs(userVector[i] - vector[i])
      distance += diff
      if (diff === 0) exact += 1
    }
    const similarity = Math.max(0, Math.round((1 - distance / 30) * 100))
    return { ...type, ...TYPE_LIBRARY[type.code], distance, exact, similarity }
  }).sort((a, b) => {
    if (a.distance !== b.distance) return a.distance - b.distance
    if (b.exact !== a.exact) return b.exact - a.exact
    return b.similarity - a.similarity
  })

  const bestNormal = ranked[0]
  let dg2Id = null
  questions.forEach((q) => {
    if (q.dimension === 'DG2') dg2Id = q.id
  })
  const drunkTriggered = dg2Id != null && Number(answers[dg2Id]) === 2

  let finalType
  let modeKicker = '你的主类型'
  let badge = `匹配度 ${bestNormal.similarity}% · 精准命中 ${bestNormal.exact}/15 维`
  let sub = '维度命中度较高，当前结果可视为你的第一人格画像。'
  let special = false
  let secondaryType = null

  if (drunkTriggered) {
    finalType = { ...TYPE_LIBRARY.DRUNK }
    secondaryType = bestNormal
    modeKicker = '隐藏人格已激活'
    badge = '匹配度 100% · 酒精异常因子已接管'
    sub = '乙醇亲和性过强，系统已直接跳过常规人格审判。'
    special = true
  } else if (bestNormal.similarity < 60) {
    finalType = { ...TYPE_LIBRARY.HHHH }
    modeKicker = '系统强制兜底'
    badge = `标准人格库最高匹配仅 ${bestNormal.similarity}%`
    sub = '标准人格库对你的脑回路集体罢工了，于是系统把你强制分配给了 HHHH。'
    special = true
  } else {
    finalType = { ...bestNormal }
  }

  /** 供结果页「匹配度 / 精准命中」展示（与 badge 文案一致） */
  let matchPercent = bestNormal.similarity
  let hitDimCount = bestNormal.exact
  if (drunkTriggered) {
    matchPercent = 100
    hitDimCount = 15
  }

  const dimExplainList = dimensionOrder.map((dim) => ({
    dim,
    name: data.dimensionMeta[dim].name,
    model: data.dimensionMeta[dim].model,
    level: levels[dim],
    raw: rawScores[dim],
    text: DIM_EXPLANATIONS[dim][levels[dim]]
  }))

  return {
    rawScores,
    levels,
    ranked,
    bestNormal,
    finalType,
    modeKicker,
    badge,
    sub,
    special,
    secondaryType,
    dimExplainList,
    sbtiType: finalType.code,
    sbtiCn: finalType.cn,
    intro: finalType.intro,
    desc: finalType.desc,
    matchPercent,
    hitDimCount
  }
}

module.exports = {
  computeSbtiResult,
  buildShuffledPaper,
  getVisibleQuestions,
  TYPE_IMAGES: data.TYPE_IMAGES
}
