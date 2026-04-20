/**
 * 「了解自己」深度服务页：按 enterprisePermissions（与 /api/config/runtime 一致）过滤套餐权益文案。
 * permissions 为 null 时视为全开（个人未绑定企业）。
 */

const TEST_ORDER = [
  { key: 'mbti', label: 'MBTI' },
  { key: 'disc', label: 'DISC' },
  { key: 'pdp', label: 'PDP' },
  { key: 'sbti', label: 'SBTI' },
  { key: 'face', label: '面相' }
]

/** @param {Record<string, any>|null|undefined} p */
function isPermOn(key, p) {
  if (!p || typeof p !== 'object') return true
  return p[key] !== false
}

/**
 * 顶部副标题里「结合 xxx 的综合解读」动态片段
 * @param {Record<string, any>|null|undefined} perms
 */
function buildTestsLabelForHero(perms) {
  const parts = []
  for (const { key, label } of TEST_ORDER) {
    if (isPermOn(key, perms)) parts.push(label)
  }
  if (parts.length === 0) return '定制测评组合'
  return parts.join(' / ')
}

/**
 * 完整 Hero 说明（第二行长文案）
 * @param {Record<string, any>|null|undefined} perms
 */
function buildDeepHeroDesc(perms) {
  const label = buildTestsLabelForHero(perms)
  return `结合 ${label} 的综合解读；个人 1v1、团队工作坊、VIP 职业发展三档方案。提交后顾问会主动与你联系。`
}

/**
 * 是否至少有一项性格/面相类能力开启（用于「综合报告」「职业」等泛化权益）
 * @param {Record<string, any>|null|undefined} p
 */
function anyPersonalityOn(p) {
  if (!p || typeof p !== 'object') return true
  return TEST_ORDER.some(({ key }) => p[key] !== false)
}

// 单行权益与权限键：命中正则则受对应开关控制
const FEATURE_LINE_RULES = [
  { re: /面相|面部分析|面部|三张照片|照片\+问卷/i, key: 'face' },
  { re: /\bMBTI\b|MBTI性格|16型/i, key: 'mbti' },
  { re: /\bDISC\b|DISC沟通/i, key: 'disc' },
  { re: /\bPDP\b|PDP行为/i, key: 'pdp' },
  { re: /盖洛普|SBTI|sbti/i, key: 'sbti' }
]

/**
 * @param {string[]} features
 * @param {Record<string, any>|null|undefined} perms
 * @returns {string[]}
 */
function filterFeatureLines(features, perms) {
  if (!Array.isArray(features) || !features.length) return []
  return features.filter((line) => {
    const s = String(line || '').trim()
    if (!s) return false
    for (const { re, key } of FEATURE_LINE_RULES) {
      if (re.test(s)) return isPermOn(key, perms)
    }
    if (/多维度|综合.*性格|优势解读|潜在盲区/i.test(s)) return anyPersonalityOn(perms)
    if (/职业|发展方向|匹配.*职业/i.test(s)) return anyPersonalityOn(perms)
    return true
  })
}

/**
 * @param {any[]} categories
 * @param {Record<string, any>|null|undefined} perms
 */
function filterCategories(categories, perms) {
  if (!Array.isArray(categories)) return []
  return categories.map((cat) => {
    const copy = Object.assign({}, cat)
    if (Array.isArray(copy.features)) {
      copy.features = filterFeatureLines(copy.features, perms)
    }
    return copy
  })
}

module.exports = {
  buildTestsLabelForHero,
  buildDeepHeroDesc,
  filterFeatureLines,
  filterCategories,
  isPermOn,
  anyPersonalityOn
}
