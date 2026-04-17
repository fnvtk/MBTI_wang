// utils/resultSectionScrollSync.js
// 四测结果页共享：scroll 节流 + 计算当前 activeSection，回写 chip 高亮
// 用法：
//   const scrollSync = require('../../utils/resultSectionScrollSync')
//   <scroll-view ... bindscroll="onScroll" />
//   onScroll(e) { scrollSync.onScroll(this, e) }

const THROTTLE_MS = 140
// chip 条 + 安全间距，单位 px（boundingClientRect 返回 px）
const OFFSET_TOP = 70

function onScroll(page, _e) {
  if (!page || !page.data || !Array.isArray(page.data.sectionNav) || !page.data.sectionNav.length) return
  const now = Date.now()
  if (page._scrollThrottleAt && now - page._scrollThrottleAt < THROTTLE_MS) return
  page._scrollThrottleAt = now

  const ids = page.data.sectionNav.map((item) => item.id).filter(Boolean)
  if (!ids.length) return
  const query = wx.createSelectorQuery().in(page)
  ids.forEach((id) => query.select('#' + id).boundingClientRect())
  query.exec((res) => {
    let current = ids[0]
    for (let i = 0; i < ids.length; i++) {
      const r = res[i]
      if (r && typeof r.top === 'number' && r.top - OFFSET_TOP <= 0) {
        current = ids[i]
      }
    }
    if (current && current !== page.data.activeSection) {
      page.setData({ activeSection: current })
    }
  })
}

module.exports = { onScroll }
