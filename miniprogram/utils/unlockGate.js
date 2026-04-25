/**
 * 解锁完整报告前的留资门禁：先手机号，再与报告一致的资料齐（isReportProfileComplete）
 */
const phoneAuth = require('./phoneAuth.js')

/** scroll-view 的 scroll-into-view 目标：包住解锁/手机号按钮（勿用列表末尾 tail，否则付费墙会被滚出视野） */
const GATE_ANCHOR_ID = 'unlock-gate-anchor'

/** 整页滚动时兜底：可选节点 */
const SCROLL_TAIL_ID = 'unlock-scroll-tail'

function getCurrentPathForRedirect() {
  const pages = getCurrentPages()
  const cur = pages[pages.length - 1]
  if (!cur || !cur.route) return '/pages/index/index'
  let path = '/' + cur.route
  const opts = cur.options || {}
  const keys = Object.keys(opts)
  if (keys.length === 0) return path
  const q = keys
    .map((k) => `${encodeURIComponent(k)}=${encodeURIComponent(opts[k] == null ? '' : String(opts[k]))}`)
    .join('&')
  return `${path}?${q}`
}

function scrollToUnlockAnchor(page, opt) {
  const scrollIntoView = !!(opt && opt.scrollIntoView)
  if (scrollIntoView && page && typeof page.setData === 'function') {
    const key = (opt && opt.scrollTargetDataKey) || 'scrollTarget'
    const targetId = (opt && opt.scrollIntoViewTargetId) || GATE_ANCHOR_ID
    page.setData({ [key]: '' }, () => {
      wx.nextTick(() => {
        page.setData({ [key]: targetId })
      })
    })
    return
  }
  const customSelector = opt && opt.anchorSelector
  wx.nextTick(() => {
    const query =
      page && typeof page.createSelectorQuery === 'function'
        ? page.createSelectorQuery()
        : wx.createSelectorQuery()
    if (customSelector) {
      query.select(customSelector).boundingClientRect()
    } else {
      query.select('#unlock-gate-anchor').boundingClientRect()
      query.select(`#${SCROLL_TAIL_ID}`).boundingClientRect()
    }
    query.selectViewport().scrollOffset()
    query.exec((res) => {
      const viewport = res && res[res.length - 1]
      let rect = null
      if (customSelector) {
        rect = res && res[0]
      } else {
        const gate = res && res[0]
        const tail = res && res[1]
        const gateOk = gate && typeof gate.top === 'number' && gate.width > 0 && gate.height >= 0
        const tailOk = tail && typeof tail.top === 'number' && tail.width > 0 && tail.height >= 0
        rect = gateOk ? gate : tailOk ? tail : null
      }
      if (rect && viewport && typeof rect.top === 'number') {
        const scrollTop = rect.top + viewport.scrollTop - 80
        wx.pageScrollTo({
          scrollTop: Math.max(0, scrollTop),
          duration: 280
        })
      } else {
        wx.pageScrollTo({ scrollTop: 99999, duration: 280 })
      }
    })
  })
}

/**
 * @param {WechatMiniprogram.Page.TrivialInstance} page
 * @param {object} [opt]
 * @param {boolean} [opt.scrollIntoView] 结果页用 scroll-view 时为 true
 * @param {string} [opt.anchorId]
 * @param {string} [opt.anchorSelector] 整页滚动时用 #id
 * @param {string} [opt.scrollTargetDataKey]
 * @returns {Promise<boolean>}
 */
function ensureUnlockPrerequisitesBeforePay(page, opt) {
  if (phoneAuth.hasPhone() && phoneAuth.isReportProfileComplete()) {
    return Promise.resolve(true)
  }
  if (!phoneAuth.hasPhone()) {
    scrollToUnlockAnchor(page, opt)
    wx.showToast({ title: '请滑动到解锁区完成手机号授权', icon: 'none' })
    return Promise.resolve(false)
  }
  const redirect = getCurrentPathForRedirect()
  const url = `/pages/user-profile/index?from=unlock&redirect=${encodeURIComponent(redirect)}`
  wx.showToast({ title: '请先完善头像与昵称', icon: 'none' })
  wx.navigateTo({
    url,
    fail: () => {
      wx.showToast({ title: '无法打开资料页', icon: 'none' })
    }
  })
  return Promise.resolve(false)
}

module.exports = {
  ensureUnlockPrerequisitesBeforePay,
  scrollToUnlockAnchor,
  getCurrentPathForRedirect
}
