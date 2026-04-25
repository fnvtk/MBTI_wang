/**
 * 结果页门禁：非分享落地且资料未齐（手机+头像+昵称）时展示约 30% 预览
 */
const { needsResultProfileGate } = require('./phoneAuth.js')

/** @param {WechatMiniprogram.Page.TrivialInstance} page */
function setProfileGateOnPage(page) {
  const fromShare = !!(page.data && page.data.fromShare)
  const gate = needsResultProfileGate(fromShare)
  page.setData({ profileGate: gate })
}

/**
 * 截取约 ratio 比例的纯文本（最少 minChars，尾部省略号）
 * @param {string} str
 * @param {number} ratio 0~1
 * @param {number} minChars
 */
function slicePreviewText(str, ratio, minChars) {
  const s = (str && String(str).trim()) || ''
  const r = typeof ratio === 'number' && ratio > 0 && ratio < 1 ? ratio : 0.3
  const minC = minChars != null ? minChars : 36
  if (!s) return ''
  const n = Math.max(minC, Math.floor(s.length * r))
  return s.length <= n ? s : s.slice(0, n) + '…'
}

/**
 * @param {string[]} list
 * @param {number} ratio
 */
function slicePreviewList(list, ratio) {
  const arr = Array.isArray(list) ? list : []
  const r = typeof ratio === 'number' && ratio > 0 && ratio < 1 ? ratio : 0.3
  if (!arr.length) return []
  const take = Math.max(1, Math.ceil(arr.length * r))
  return arr.slice(0, take)
}

function openTimelineShareHint() {
  try {
    wx.showShareMenu({ withShareTicket: true, menus: ['shareAppMessage', 'shareTimeline'] })
  } catch (e) {}
  wx.showModal({
    title: '分享到朋友圈',
    content: '已为你打开分享能力：请先点右上角「···」，选择「分享到朋友圈」。好友点链接即可测试。',
    showCancel: false,
    confirmText: '知道了'
  })
}

module.exports = {
  setProfileGateOnPage,
  slicePreviewText,
  slicePreviewList,
  openTimelineShareHint
}
