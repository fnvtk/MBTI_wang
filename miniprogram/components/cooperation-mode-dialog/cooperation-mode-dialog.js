const { request } = require('../../utils/request.js')

Component({
  properties: {
    visible: {
      type: Boolean,
      value: false
    },
    modes: {
      type: Array,
      value: []
    }
  },

  data: {
    selected: '',
    submitting: false
  },

  observers: {
    visible(v) {
      if (v) {
        const modes = this.properties.modes || []
        const first = modes.length ? modes[0].code : ''
        this.setData({ selected: first || '', submitting: false })
      }
    },
    modes(modes) {
      if (this.properties.visible && modes && modes.length) {
        const cur = this.data.selected
        const ok = modes.some((m) => m && m.code === cur)
        if (!ok) this.setData({ selected: modes[0].code })
      }
    }
  },

  methods: {
    noop() {},

    onMask() {},

    onRadioChange(e) {
      const v = e.detail && e.detail.value ? String(e.detail.value) : ''
      if (v) this.setData({ selected: v })
    },

    onConfirm() {
      const code = (this.data.selected || '').trim()
      if (!code) {
        wx.showToast({ title: '请选择一项', icon: 'none' })
        return
      }
      if (this.data.submitting) return
      this.setData({ submitting: true })
      request({
        url: '/api/user/cooperation-preference',
        method: 'POST',
        data: { modeCode: code },
        success: (res) => {
          const body = res.data || {}
          if (res.statusCode === 200 && body.code === 200) {
            wx.showToast({ title: body.message || '已提交', icon: 'success' })
            this.triggerEvent('success', body.data || {})
          } else {
            wx.showToast({ title: body.message || '提交失败', icon: 'none' })
          }
        },
        fail: () => {
          wx.showToast({ title: '网络错误', icon: 'none' })
        },
        complete: () => {
          this.setData({ submitting: false })
        }
      })
    }
  }
})
