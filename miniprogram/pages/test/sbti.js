// pages/test/sbti.js — SBTI：服务端全量拉题 + 组卷（闸口 DG1/DG2）+ sbtiEngine 计分
const { fetchQuestionBank } = require('../../utils/questionBank')
const { buildShuffledPaper, getVisibleQuestions, computeSbtiResult } = require('../../utils/sbtiEngine')
const app = getApp()

const SBTI_TIME_SEC = 45 * 60

Page({
  data: {
    loading: true,
    currentIndex: 0,
    currentQuestion: null,
    answers: {},
    total: 0,
    progress: 0,
    timeRemaining: SBTI_TIME_SEC,
    _initialSeconds: SBTI_TIME_SEC,
    formatTime: '45:00',
    isSubmitting: false
  },

  timer: null,
  _paper: null,
  _advanceTimer: null,

  onLoad(options) {
    try {
      require('../../utils/thirdPartyContext.js').ingestThirdPartyOnPageLoad(options || {}, app)
    } catch (e) {}
    fetchQuestionBank('sbti', {})
      .then((all) => {
        if (!all || !all.length) {
          wx.showToast({ title: '暂无题目', icon: 'none' })
          this.setData({ loading: false })
          return
        }
        const paper = buildShuffledPaper(all)
        this._paper = paper
        const visible = getVisibleQuestions(paper.ordered, {}, paper.dg2)
        const total = visible.length
        if (!total) {
          wx.showToast({ title: '组卷失败', icon: 'none' })
          this.setData({ loading: false })
          return
        }
        this.setData({
          loading: false,
          currentIndex: 0,
          currentQuestion: visible[0],
          total,
          progress: total ? Math.round((1 / total) * 100) : 0,
          timeRemaining: SBTI_TIME_SEC,
          _initialSeconds: SBTI_TIME_SEC,
          formatTime: '45:00'
        })
        try {
          require('../../utils/analytics').track('test_start', { type: 'sbti', total })
        } catch (e) {}
        try {
          wx.showShareMenu({ withShareTicket: true, menus: ['shareAppMessage', 'shareTimeline'] })
        } catch (e) {}
        this.startTimer()
      })
      .catch((err) => {
        this.setData({ loading: false })
        wx.showToast({ title: (err && err.message) || '加载失败', icon: 'none' })
      })
  },

  onUnload() {
    if (this._advanceTimer) {
      clearTimeout(this._advanceTimer)
      this._advanceTimer = null
    }
    if (this.timer) {
      clearInterval(this.timer)
    }
  },

  startTimer() {
    this.timer = setInterval(() => {
      let time = this.data.timeRemaining - 1
      if (time <= 0) {
        clearInterval(this.timer)
        this.submitTest({ allowIncomplete: true })
        return
      }
      const minutes = Math.floor(time / 60)
      const seconds = time % 60
      this.setData({
        timeRemaining: time,
        formatTime: `${minutes}:${seconds < 10 ? '0' : ''}${seconds}`
      })
    }, 1000)
  },

  _rebuildVisible() {
    const paper = this._paper
    if (!paper) return []
    return getVisibleQuestions(paper.ordered, this.data.answers, paper.dg2)
  },

  selectAnswer(e) {
    if (this._advanceTimer) {
      clearTimeout(this._advanceTimer)
      this._advanceTimer = null
    }
    const value = e.currentTarget.dataset.value
    const cq = this.data.currentQuestion
    if (value == null || !cq || cq.id == null) return

    const questionId = cq.id
    const idx = this.data.currentIndex
    const answers = { ...this.data.answers, [questionId]: value }

    const visible = getVisibleQuestions(this._paper.ordered, answers, this._paper.dg2)
    const tot = visible.length
    const curPos = visible.findIndex((q) => q.id === questionId)
    const nextIdx = curPos >= 0 ? curPos + 1 : idx + 1

    this.setData({ answers }, () => {
      this._advanceTimer = setTimeout(() => {
        this._advanceTimer = null
        const d = this.data
        if (d.currentIndex !== idx || !d.currentQuestion || d.currentQuestion.id !== questionId) return
        if (nextIdx < visible.length) {
          const nq = visible[nextIdx]
          this.setData({
            currentIndex: nextIdx,
            currentQuestion: nq,
            total: tot,
            progress: tot ? Math.round(((nextIdx + 1) / tot) * 100) : 0
          })
        } else {
          this.submitTest()
        }
      }, 320)
    })
  },

  prevQuestion() {
    if (this._advanceTimer) {
      clearTimeout(this._advanceTimer)
      this._advanceTimer = null
    }
    if (this.data.currentIndex <= 0) return
    const visible = this._rebuildVisible()
    const newIndex = this.data.currentIndex - 1
    const nq = visible[newIndex]
    if (!nq) return
    const tot = visible.length
    this.setData({
      currentIndex: newIndex,
      currentQuestion: nq,
      total: tot,
      progress: tot ? Math.round(((newIndex + 1) / tot) * 100) : 0
    })
  },

  nextQuestion() {
    const visible = this._rebuildVisible()
    if (this.data.currentIndex < visible.length - 1) {
      const newIndex = this.data.currentIndex + 1
      const nq = visible[newIndex]
      const tot = visible.length
      this.setData({
        currentIndex: newIndex,
        currentQuestion: nq,
        total: tot,
        progress: tot ? Math.round(((newIndex + 1) / tot) * 100) : 0
      })
    }
  },

  finishTest() {
    const q = this.data.currentQuestion
    if (!q) return
    if (this.data.answers[q.id] == null) {
      wx.showToast({ title: '请先选择一项', icon: 'none' })
      return
    }
    const visible = this._rebuildVisible()
    const missing = visible.filter((x) => this.data.answers[x.id] == null || this.data.answers[x.id] === '')
    if (missing.length) {
      wx.showToast({ title: '还有题目未作答', icon: 'none' })
      return
    }
    this.submitTest()
  },

  /**
   * @param {{ allowIncomplete?: boolean }} opt 计时结束允许未答完也提交
   */
  submitTest(opt = {}) {
    if (this.data.isSubmitting) return
    const allowIncomplete = !!opt.allowIncomplete
    if (this.timer) {
      clearInterval(this.timer)
      this.timer = null
    }

    const visible = this._rebuildVisible()
    const answers = this.data.answers
    if (!allowIncomplete) {
      const missing = visible.filter((x) => answers[x.id] == null || answers[x.id] === '')
      if (missing.length) {
        wx.showToast({ title: `还有 ${missing.length} 题未作答`, icon: 'none' })
        this.startTimer()
        return
      }
    }

    this.setData({ isSubmitting: true })

    let result
    try {
      const paper = this._paper
      const qs = paper ? paper.ordered.slice() : []
      if (paper && paper.dg2 && !qs.some((q) => q.id === paper.dg2.id)) {
        qs.push(paper.dg2)
      }
      result = computeSbtiResult(qs, answers)
    } catch (err) {
      console.error('computeSbtiResult', err)
      wx.showToast({ title: '计算结果失败，请重试', icon: 'none' })
      this.setData({ isSubmitting: false })
      this.startTimer()
      return
    }

    const resultData = {
      ...result,
      answers,
      testDuration: (this.data._initialSeconds || SBTI_TIME_SEC) - this.data.timeRemaining,
      completedAt: new Date().toISOString(),
      timestamp: new Date().toISOString()
    }
    wx.setStorageSync('sbtiResult', resultData)
    app.saveTestResult('sbti', resultData)
    try {
      require('../../utils/analytics').track('test_complete', {
        type: 'sbti',
        result: result.sbtiType,
        duration: resultData.testDuration
      })
    } catch (e) {}

    wx.redirectTo({
      url: '/pages/result/sbti'
    })
  },

  onShareAppMessage() {
    const { getSharePath } = require('../../utils/share')
    return {
      title: '来测测你的 SBTI 类型',
      path: getSharePath('/pages/test/sbti')
    }
  },

  onShareTimeline() {
    const { buildShareQuery } = require('../../utils/share')
    return {
      title: '来测测你的 SBTI 类型',
      query: buildShareQuery()
    }
  }
})
