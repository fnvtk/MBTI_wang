const gaokaoApi = require('../../utils/gaokao')

Page({
  data: {
    loading: false,
    tasks: {
      mbti: { code: 'mbti', status: 'todo', resultText: '', testResultId: 0, id: 0, testType: 'mbti', typeName: 'MBTI性格', emoji: '🧠', testTime: '' },
      pdp: { code: 'pdp', status: 'todo', resultText: '', testResultId: 0, id: 0, testType: 'pdp', typeName: 'PDP行为', emoji: '🦁', testTime: '' },
      disc: { code: 'disc', status: 'todo', resultText: '', testResultId: 0, id: 0, testType: 'disc', typeName: 'DISC测评', emoji: '📊', testTime: '' },
      face: { code: 'face', status: 'todo', resultText: '', testResultId: 0, id: 0, testType: 'ai', typeName: '拍照面相', emoji: '📷', testTime: '', recordTestType: 'ai' },
      form: { code: 'form', status: 'todo', resultText: '', testResultId: 0, id: 0, testType: 'form', typeName: '高考信息表单', emoji: '📝', testTime: '' }
    },
    canAnalyze: false,
    missingItems: [],
    analyzing: false
  },

  onShow() {
    this.refreshStatus()
  },

  refreshStatus() {
    this.setData({ loading: true })
    gaokaoApi
      .getTaskStatus({ scene: 'gaokao_hub' })
      .then((data) => {
        this.setData({
          tasks: data.tasks || this.data.tasks,
          canAnalyze: !!data.canAnalyze,
          missingItems: data.missingItems || []
        })
      })
      .catch((e) => {
        wx.showToast({ title: e.message || '加载失败', icon: 'none' })
      })
      .finally(() => this.setData({ loading: false }))
  },

  goTask(e) {
    const code = e.currentTarget.dataset.code
    const resultId = Number(e.currentTarget.dataset.resultId || 0)
    const done = (e.currentTarget.dataset.status || '') === 'done'
    if (code === 'mbti') {
      if (done && resultId > 0) {
        wx.navigateTo({ url: `/pages/result/mbti?id=${resultId}&type=mbti` })
      } else {
        wx.navigateTo({ url: '/pages/test/mbti' })
      }
      return
    }
    if (code === 'pdp') {
      if (done && resultId > 0) {
        wx.navigateTo({ url: `/pages/result/pdp?id=${resultId}&type=pdp` })
      } else {
        wx.navigateTo({ url: '/pages/test/pdp' })
      }
      return
    }
    if (code === 'disc') {
      if (done && resultId > 0) {
        wx.navigateTo({ url: `/pages/result/disc?id=${resultId}&type=disc` })
      } else {
        wx.navigateTo({ url: '/pages/test/disc' })
      }
      return
    }
    if (code === 'face') {
      const recType = String(e.currentTarget.dataset.recordType || 'ai').toLowerCase()
      const typeParam = recType === 'face' ? 'face' : 'ai'
      if (done && resultId > 0) {
        wx.navigateTo({ url: `/pages/index/result?id=${resultId}&type=${typeParam}` })
      } else {
        wx.switchTab({ url: '/pages/index/camera' })
      }
      return
    }
    if (code === 'form') {
      wx.navigateTo({ url: '/pages/gaokao/form' })
    }
  },

  onAnalyzeTap() {
    if (!this.data.canAnalyze) {
      const nameMap = {
        mbti: 'MBTI测试',
        pdp: 'PDP测试',
        disc: 'DISC测试',
        face: '拍照面相',
        form: '高考信息表单'
      }
      const msg = (this.data.missingItems || []).map((k) => nameMap[k] || k).join('、')
      wx.showToast({
        title: msg ? `请先完成：${msg}` : '请先完成全部任务',
        icon: 'none',
        duration: 2500
      })
      return
    }
    this.setData({ analyzing: true })
    wx.navigateTo({
      url: '/pages/gaokao/report?pendingAnalyze=1',
      complete: () => {
        this.setData({ analyzing: false })
      }
    })
  }
})
