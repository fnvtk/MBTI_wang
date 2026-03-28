// pages/test/pdp.js
const { pdpQuestions, shuffleQuestions } = require('../../utils/questions')
const { pdpDescriptions } = require('../../utils/descriptions')
const app = getApp()

Page({
  data: {
    questions: [],
    currentIndex: 0,
    currentQuestion: null,
    answers: {},
    selectedAnswer: null,
    total: pdpQuestions.length,
    answeredCount: 0,
    progress: 0,
    timeRemaining: 15 * 60,
    formatTime: '15:00',
    isSubmitting: false
  },

  timer: null,

  onLoad() {
    const questions = shuffleQuestions(pdpQuestions)
    this.setData({ questions, currentQuestion: questions[0] })
    try { require('../../utils/analytics').track('test_start', { type: 'pdp', total: questions.length }) } catch (e) {}
    this.startTimer()
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

  getEmoji(value) {
    const emojis = { Tiger: '🐅', Peacock: '🦚', Koala: '🐨', Owl: '🦉', Chameleon: '🦎' }
    return emojis[value] || '🔹'
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

    const scores = { Tiger: 0, Peacock: 0, Koala: 0, Owl: 0, Chameleon: 0 }
    Object.values(this.data.answers).forEach(value => {
      if (scores.hasOwnProperty(value)) scores[value]++
    })

    const total = Object.values(scores).reduce((sum, v) => sum + v, 0)
    const percentages = {}
    Object.keys(scores).forEach(key => {
      percentages[key] = Math.round((scores[key] / total) * 100)
    })

    const dominantType = Object.entries(scores).sort((a, b) => b[1] - a[1])[0][0]
    const secondaryType = Object.entries(scores).sort((a, b) => b[1] - a[1])[1][0]

    const resultData = {
      scores,
      percentages,
      dominantType,
      secondaryType,
      description: pdpDescriptions[dominantType],
      testDuration: 15 * 60 - this.data.timeRemaining,
      completedAt: new Date().toISOString(),
      // 便于后端留存完整答题过程
      answers: this.data.answers
    }

    // 本地缓存 + 全局缓存
    tt.setStorageSync('pdpResult', resultData)
    try { require('../../utils/analytics').track('test_complete', { type: 'pdp', result: dominantType, duration: resultData.testDuration }) } catch (e) {}
    if (app && typeof app.saveTestResult === 'function') {
      app.saveTestResult('pdp', resultData)
    }

    tt.redirectTo({ url: '/pages/result/pdp' })
  }
})
