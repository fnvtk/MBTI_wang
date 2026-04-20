const { request } = require('../../utils/request.js')

Page({
  data: {
    list: [],
    page: 1,
    pageSize: 20,
    hasMore: true
  },
  onShow() {
    const { ensureRuntimeThenGate } = require('../../utils/miniprogramAuditGate.js')
    ensureRuntimeThenGate(() => {
      this.setData({ list: [], page: 1, hasMore: true }, () => this.fetchPage())
    })
  },
  fetchPage() {
    const { page, pageSize, list, hasMore } = this.data
    if (!hasMore) return
    request({
      url: '/api/ai/conversations',
      method: 'GET',
      data: { page, pageSize },
      success: (res) => {
        const body = (res && res.data) || {}
        if (body.code !== 200) return
        const d = body.data || {}
        const items = (d.list || []).map(x => ({
          id: x.id,
          title: x.title,
          mbtiType: x.mbtiType,
          messageCount: x.messageCount,
          lastMessageAt: x.lastMessageAt,
          lastMessageAtStr: formatTime(x.lastMessageAt)
        }))
        this.setData({
          list: list.concat(items),
          hasMore: !!d.hasMore,
          page: page + 1
        })
      }
    })
  },
  onReachBottom() {
    this.fetchPage()
  },
  onOpen(e) {
    const id = e.currentTarget.dataset.id
    wx.navigateTo({ url: `/pages/ai-chat/index?cid=${id}` })
  }
})

function formatTime(t) {
  if (!t) return ''
  const d = new Date(t * 1000)
  const now = Date.now()
  const diff = now - t * 1000
  if (diff < 86400000) {
    return `${pad(d.getHours())}:${pad(d.getMinutes())}`
  }
  return `${d.getMonth() + 1}-${d.getDate()}`
}
function pad(n) { return n < 10 ? '0' + n : '' + n }
