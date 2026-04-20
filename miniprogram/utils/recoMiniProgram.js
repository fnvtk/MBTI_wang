/**
 * 精选推荐 → 跳转其他小程序 path（与「一场 soul」阅读页 pages/read/read?id= 对齐）
 * @param {string} basePath 后台配置的 path，无 sourceId 时使用
 * @param {{ id?: string|number, title?: string, sourceId?: string|number, fromTag?: string }} opts
 */
function buildRecoMiniProgramPath(basePath, opts) {
  const id = opts && opts.id != null ? String(opts.id).trim() : ''
  const title = opts && opts.title != null ? String(opts.title).trim() : ''
  const sourceId = opts && opts.sourceId != null ? String(opts.sourceId).trim() : ''
  const fromTag = (opts && opts.fromTag) || 'mbti_reco'
  const parts = ['from=' + encodeURIComponent(fromTag)]
  if (id) parts.push('mbtiArticleId=' + encodeURIComponent(id))
  if (title) parts.push('title=' + encodeURIComponent(title.slice(0, 200)))

  if (sourceId) {
    let path = 'pages/read/read?id=' + encodeURIComponent(sourceId)
    return path + '&' + parts.join('&')
  }
  let path = (basePath || 'pages/index/index').replace(/^\//, '')
  if (!path) path = 'pages/index/index'
  const sep = path.indexOf('?') >= 0 ? '&' : '?'
  return path + sep + parts.join('&')
}

module.exports = {
  buildRecoMiniProgramPath
}
