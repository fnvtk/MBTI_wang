// pages/test/mbti.js - MBTI测试页面逻辑
const { loadQuestions } = require('../../utils/questionBank')
const { mbtiDescriptions } = require('../../utils/descriptions')
const payment = require('../../utils/payment')
const app = getApp()

const MBTI_TIME_SEC = 30 * 60 // 30 分钟

Page({
  data: {
    loading: true,
    questions: [],
    currentIndex: 0,
    currentQuestion: null,
    answers: {},
    selectedAnswer: null,
    total: 0,
    answeredCount: 0,
    progress: 0,
    timeRemaining: MBTI_TIME_SEC,
    _initialSeconds: MBTI_TIME_SEC,
    formatTime: '30:00',
    isSubmitting: false,
    canAccess: false
  },

  timer: null,

  onLoad(options) {
    try {
      require('../../utils/thirdPartyContext.js').ingestThirdPartyOnPageLoad(options || {}, app)
    } catch (e) {}
    loadQuestions('mbti', {})
      .then((questions) => {
        const total = questions.length
        if (!total) {
          wx.showToast({ title: '暂无题目', icon: 'none' })
          this.setData({ loading: false })
          return
        }
        this.setData({
          loading: false,
          questions,
          currentQuestion: questions[0],
          canAccess: true,
          total,
          progress: Math.round((1 / total) * 100),
          timeRemaining: MBTI_TIME_SEC,
          _initialSeconds: MBTI_TIME_SEC,
          formatTime: '30:00'
        })
        try {
          require('../../utils/analytics').track('test_start', { type: 'mbti', total })
        } catch (e) {}
        this.startTimer()
      })
      .catch((err) => {
        this.setData({ loading: false })
        wx.showToast({ title: (err && err.message) || '加载失败', icon: 'none' })
      })
  },

  // 检查访问权限
  checkAccess() {
    // 当前策略：所有测试免费开放，直接允许访问
    // 若后续恢复收费，可重新启用 payment.canTakeTest 等校验逻辑
    return true
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

  // 启动计时器
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

  // 选择答案：防抖 + 过期回调校验，避免连点导致重复 next 漏题
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
    const tot = this.data.total

    const answers = { ...this.data.answers }
    answers[questionId] = value
    const answeredCount = Object.keys(answers).length
    const progress = tot ? Math.round(((idx + 1) / tot) * 100) : 0

    this.setData(
      {
        selectedAnswer: value,
        answers,
        answeredCount,
        progress
      },
      () => {
        this._advanceTimer = setTimeout(() => {
          this._advanceTimer = null
          const d = this.data
          if (d.currentIndex !== idx || !d.currentQuestion || d.currentQuestion.id !== questionId) return
          if (idx < tot - 1) {
            this.nextQuestion()
          } else {
            this.submitTest()
          }
        }, 320)
      }
    )
  },

  // 上一题
  prevQuestion() {
    if (this._advanceTimer) {
      clearTimeout(this._advanceTimer)
      this._advanceTimer = null
    }
    if (this.data.currentIndex > 0) {
      const newIndex = this.data.currentIndex - 1
      const newQuestion = this.data.questions[newIndex]
      const tot = this.data.total
      this.setData({
        currentIndex: newIndex,
        currentQuestion: newQuestion,
        selectedAnswer: this.data.answers[newQuestion.id] || null,
        progress: tot ? Math.round(((newIndex + 1) / tot) * 100) : 0
      })
    }
  },

  // 下一题（跳过：不记录答案）
  nextQuestion() {
    if (this.data.currentIndex < this.data.total - 1) {
      const newIndex = this.data.currentIndex + 1
      const newQuestion = this.data.questions[newIndex]
      const tot = this.data.total
      this.setData({
        currentIndex: newIndex,
        currentQuestion: newQuestion,
        selectedAnswer: this.data.answers[newQuestion.id] || null,
        progress: tot ? Math.round(((newIndex + 1) / tot) * 100) : 0
      })
    }
  },

  /** 最后一题手动提交（自动提交失败时点这里） */
  finishTest() {
    const q = this.data.currentQuestion
    if (!q) return
    if (this.data.answers[q.id] == null) {
      wx.showToast({ title: '请先选择一项', icon: 'none' })
      return
    }
    const tot = this.data.total
    if (Object.keys(this.data.answers).length < tot) {
      wx.showToast({ title: '还有题目未作答，请返回补答', icon: 'none' })
      return
    }
    this.submitTest()
  },

  /**
   * @param {{ allowIncomplete?: boolean }} opt 计时结束允许未答完也出结果
   */
  submitTest(opt = {}) {
    if (this.data.isSubmitting) return
    const allowIncomplete = !!opt.allowIncomplete
    if (this.timer) {
      clearInterval(this.timer)
      this.timer = null
    }

    const tot = this.data.total
    const n = Object.keys(this.data.answers).length
    if (!allowIncomplete && n < tot) {
      wx.showToast({ title: `还有 ${tot - n} 题未作答`, icon: 'none' })
      this.startTimer()
      return
    }

    this.setData({ isSubmitting: true })

    let result
    try {
      result = this.calculateResult()
    } catch (err) {
      console.error('calculateResult', err)
      wx.showToast({ title: '计算结果失败，请重试', icon: 'none' })
      this.setData({ isSubmitting: false })
      this.startTimer()
      return
    }

    const resultData = {
      ...result,
      answers: this.data.answers,
      testDuration: (this.data._initialSeconds || MBTI_TIME_SEC) - this.data.timeRemaining,
      completedAt: new Date().toISOString(),
      timestamp: new Date().toISOString()
    }
    wx.setStorageSync('mbtiResult', resultData)
    app.saveTestResult('mbti', resultData)
    try { require('../../utils/analytics').track('test_complete', { type: 'mbti', result: result.mbtiType, confidence: result.confidence, duration: resultData.testDuration }) } catch (e) {}

    wx.redirectTo({
      url: '/pages/result/mbti'
    })
  },

  // 计算MBTI结果
  calculateResult() {
    const answers = this.data.answers
    const scores = { E: 0, I: 0, S: 0, N: 0, T: 0, F: 0, J: 0, P: 0 }

    // 统计各维度得分
    Object.values(answers).forEach(value => {
      if (scores.hasOwnProperty(value)) {
        scores[value]++
      }
    })

    // 确定MBTI类型
    const mbtiType = [
      scores.E >= scores.I ? 'E' : 'I',
      scores.S >= scores.N ? 'S' : 'N',
      scores.T >= scores.F ? 'T' : 'F',
      scores.J >= scores.P ? 'J' : 'P'
    ].join('')

    const pct = (a, b) => {
      const s = a + b
      if (!s) return 50
      return Math.round((Math.max(a, b) / s) * 100)
    }

    const dimensionScores = {
      EI: { E: scores.E, I: scores.I, dominant: scores.E >= scores.I ? 'E' : 'I', percentage: pct(scores.E, scores.I) },
      SN: { S: scores.S, N: scores.N, dominant: scores.S >= scores.N ? 'S' : 'N', percentage: pct(scores.S, scores.N) },
      TF: { T: scores.T, F: scores.F, dominant: scores.T >= scores.F ? 'T' : 'F', percentage: pct(scores.T, scores.F) },
      JP: { J: scores.J, P: scores.P, dominant: scores.J >= scores.P ? 'J' : 'P', percentage: pct(scores.J, scores.P) }
    }

    let confidence = Math.round(
      (dimensionScores.EI.percentage + dimensionScores.SN.percentage +
        dimensionScores.TF.percentage + dimensionScores.JP.percentage) / 4
    )
    if (!Number.isFinite(confidence)) confidence = 0

    return {
      mbtiType,
      scores,
      dimensionScores,
      confidence,
      description: mbtiDescriptions[mbtiType] || {}
    }
  }
})
