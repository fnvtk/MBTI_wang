const { request } = require('../../utils/request.js')
const { getEnterpriseIdForApiPayload } = require('../../utils/enterpriseContext.js')
const inviteCodeGate = require('../../utils/inviteCodeGate.js')

Component({
  properties: {
    visible: {
      type: Boolean,
      value: false
    }
  },

  data: {
    code: '',
    submitting: false,
    maxLen: 32
  },

  observers: {
    visible(v) {
      if (v) {
        this.setData({ code: '', submitting: false })
      }
    }
  },

  methods: {
    noop() {},

    onInput(e) {
      const v = (e.detail && e.detail.value) ? String(e.detail.value) : ''
      this.setData({ code: v })
    },

    onMaskSkip() {
      this.onSkip()
    },

    onSkip() {
      inviteCodeGate.markInviteSkipped()
      this.triggerEvent('skip', {})
    },

    onConfirmTap() {
      const raw = (this.data.code || '').trim()
      if (!raw) {
        wx.showToast({ title: '请输入邀请码', icon: 'none' })
        return
      }
      if (this.data.submitting) return
      this.setData({ submitting: true })
      const token = wx.getStorageSync('token')
      if (!token) {
        wx.showToast({ title: '请先登录', icon: 'none' })
        this.setData({ submitting: false })
        return
      }
      const payload = { inviteCode: raw }
      const eid = getEnterpriseIdForApiPayload()
      if (eid != null && Number(eid) > 0) {
        payload.eid = Number(eid)
      }
      request({
        url: '/api/distribution/bind',
        method: 'POST',
        data: payload,
        success: (res) => {
          const body = res.data || {}
          if (res.statusCode === 200 && body.code === 200) {
            inviteCodeGate.markInviteBound()
            wx.showToast({ title: body.message || '已记录邀请关系', icon: 'success' })
            this.triggerEvent('success', body.data || {})
          } else {
            const msg = body.message || '邀请码无效'
            wx.showToast({ title: msg, icon: 'none' })
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
