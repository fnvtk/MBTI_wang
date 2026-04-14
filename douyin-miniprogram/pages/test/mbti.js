// pages/test/mbti.js - MBTI测试页面逻辑（与微信端对齐：最后一题必提交结果）
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

  onLoad() {
    app.ensureLogin()
      .then((ok) => {
        if (!ok) {
          this.setData({ loading: false })
          tt.showToast({ title: '登录失败，请重试', icon: 'none' })
          return Promise.reject(new Error('login'))
        }
        return loadQuestions('mbti', {})
      })
      .then((questions) => {
        if (!questions) return
        const total = questions.length
        if (!total) {
          tt.showToast({ title: '暂无题目', icon: 'none' })
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
        if (err && err.message === 'login') return
        this.setData({ loading: false })
        tt.showToast({ title: (err && err.message) || '加载失败', icon: 'none' })
      })
  },

  checkAccess() {
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

  finishTest() {
    const q = this.data.currentQuestion
    if (!q) return
    if (this.data.answers[q.id] == null) {
      tt.showToast({ title: '请先选择一项', icon: 'none' })
      return
    }
    const tot = this.data.total
    if (Object.keys(this.data.answers).length < tot) {
      tt.showToast({ title: '还有题目未作答，请返回补答', icon: 'none' })
      return
    }
    this.submitTest()
  },

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
      tt.showToast({ title: `还有 ${tot - n} 题未作答`, icon: 'none' })
      this.startTimer()
      return
    }

    this.setData({ isSubmitting: true })

    let result
    try {
      result = this.calculateResult()
    } catch (err) {
      console.error('calculateResult', err)
      tt.showToast({ title: '计算结果失败，请重试', icon: 'none' })
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
    tt.setStorageSync('mbtiResult', resultData)
    try { require('../../utils/analytics').track('test_complete', { type: 'mbti', result: result.mbtiType, confidence: result.confidence, duration: resultData.testDuration }) } catch (e) {}

    app.saveTestResult('mbti', resultData).then((extra) => {
      const rid = extra && extra.id
      if (rid) {
        tt.redirectTo({ url: `/pages/result/mbti?id=${rid}&type=mbti` })
      } else {
        tt.redirectTo({ url: '/pages/result/mbti' })
      }
    })
  },

  calculateResult() {
    const answers = this.data.answers
    const scores = { E: 0, I: 0, S: 0, N: 0, T: 0, F: 0, J: 0, P: 0 }

    Object.values(answers).forEach(value => {
      if (scores.hasOwnProperty(value)) {
        scores[value]++
      }
    })

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
