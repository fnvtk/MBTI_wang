// pages/test/disc.js
const { loadQuestions } = require('../../utils/questionBank')
const { discDescriptions } = require('../../utils/descriptions')
const app = getApp()

const DISC_TIME_SEC = 15 * 60

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
    timeRemaining: DISC_TIME_SEC,
    _initialSeconds: DISC_TIME_SEC,
    formatTime: '15:00',
    isSubmitting: false
  },

  timer: null,

  onLoad() {
    loadQuestions('disc', {})
      .then((questions) => {
        if (!questions.length) {
          tt.showToast({ title: '暂无题目', icon: 'none' })
          this.setData({ loading: false })
          return
        }
        this.setData({
          loading: false,
          questions,
          currentQuestion: questions[0],
          total: questions.length,
          timeRemaining: DISC_TIME_SEC,
          _initialSeconds: DISC_TIME_SEC,
          formatTime: '15:00'
        })
        try {
          require('../../utils/analytics').track('test_start', { type: 'disc', total: questions.length })
        } catch (e) {}
        this.startTimer()
      })
      .catch((err) => {
        this.setData({ loading: false })
        tt.showToast({ title: (err && err.message) || '加载失败', icon: 'none' })
      })
  },

  onUnload() {
    if (this._advanceTimer) {
      clearTimeout(this._advanceTimer)
      this._advanceTimer = null
    }
    if (this.timer) clearInterval(this.timer)
  },

  startTimer() {
    this.timer = setInterval(() => {
      let time = this.data.timeRemaining - 1
      if (time <= 0) {
        clearInterval(this.timer)
        this.submitTest()
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

    this.setData(
      {
        selectedAnswer: value,
        answers,
        answeredCount,
        progress: tot ? (answeredCount / tot) * 100 : 0
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
        }, 300)
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
      this.setData({
        currentIndex: newIndex,
        currentQuestion: newQuestion,
        selectedAnswer: this.data.answers[newQuestion.id] || null
      })
    }
  },

  nextQuestion() {
    if (this.data.currentIndex < this.data.total - 1) {
      const newIndex = this.data.currentIndex + 1
      const newQuestion = this.data.questions[newIndex]
      this.setData({
        currentIndex: newIndex,
        currentQuestion: newQuestion,
        selectedAnswer: this.data.answers[newQuestion.id] || null
      })
    }
  },

  submitTest() {
    if (this.data.isSubmitting) return
    this.setData({ isSubmitting: true })

    const scores = { D: 0, I: 0, S: 0, C: 0 }
    Object.values(this.data.answers).forEach(value => {
      if (scores.hasOwnProperty(value)) scores[value]++
    })

    const total = Object.values(scores).reduce((sum, v) => sum + v, 0)
    const percentages = {
      D: Math.round((scores.D / total) * 100),
      I: Math.round((scores.I / total) * 100),
      S: Math.round((scores.S / total) * 100),
      C: Math.round((scores.C / total) * 100)
    }

    const dominantType = Object.entries(scores).sort((a, b) => b[1] - a[1])[0][0]
    const secondaryType = Object.entries(scores).sort((a, b) => b[1] - a[1])[1][0]

    const resultData = {
      scores,
      percentages,
      dominantType,
      secondaryType,
      description: discDescriptions[dominantType],
      testDuration: (this.data._initialSeconds || DISC_TIME_SEC) - this.data.timeRemaining,
      completedAt: new Date().toISOString(),
      // 便于后端留存完整答题过程
      answers: this.data.answers
    }

    // 本地缓存 + 全局缓存
    tt.setStorageSync('discResult', resultData)
    try { require('../../utils/analytics').track('test_complete', { type: 'disc', result: dominantType, duration: resultData.testDuration }) } catch (e) {}
    if (app && typeof app.saveTestResult === 'function') {
      app.saveTestResult('disc', resultData)
    }

    tt.redirectTo({ url: '/pages/result/disc' })
  }
})
