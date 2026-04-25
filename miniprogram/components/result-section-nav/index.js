// components/result-section-nav/index.js
// 结果页顶部分类锚点导航：横向 chip 条，点击触发 navtap 事件，由页面 scroll-into-view 接管跳转
Component({
  options: {
    addGlobalClass: true,
    multipleSlots: false
  },
  properties: {
    // [{ id: 'sec-insight', label: '洞察', emoji: '🧠' }, ...]
    sections: { type: Array, value: [] },
    // 当前高亮的 section id（可选；暂未接滚动联动）
    active: { type: String, value: '' },
    // 色系锚点：purple / blue / orange / teal（对应 MBTI/DISC/PDP/SBTI）
    theme: { type: String, value: 'purple' }
  },
  methods: {
    onTap(e) {
      const id = e.currentTarget.dataset.id
      if (!id) return
      this.triggerEvent('navtap', { id })
    }
  }
})
