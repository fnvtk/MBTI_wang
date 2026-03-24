// pages/test/disc.js
const { discQuestions, shuffleQuestions } = require('../../utils/questions')
const { discDescriptions } = require('../../utils/descriptions')
const app = getApp()

Page({
  data: {
    questions: [],
    currentIndex: 0,
    currentQuestion: null,
    answers: {},
    selectedAnswer: null,
    total: discQuestions.length,
    answeredCount: 0,
    progress: 0,
    timeRemaining: 15 * 60,
    formatTime: '15:00',
    isSubmitting: false
  },

  timer: null,

  onLoad() {
    const questions = shuffleQuestions(discQuestions)
    this.setData({ questions, currentQuestion: questions[0] })
    this.startTimer()
  },

  onUnload() {
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
    const value = e.currentTarget.dataset.value
    const questionId = this.data.currentQuestion.id
    let answers = { ...this.data.answers }
    answers[questionId] = value
    
    this.setData({
      selectedAnswer: value,
      answers: answers,
      answeredCount: Object.keys(answers).length,
      progress: (Object.keys(answers).length / this.data.total) * 100
    })

    setTimeout(() => {
      if (this.data.currentIndex < this.data.total - 1) {
        this.nextQuestion()
      }
    }, 300)
  },

  prevQuestion() {
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
      testDuration: 15 * 60 - this.data.timeRemaining,
      completedAt: new Date().toISOString(),
      // 便于后端留存完整答题过程
      answers: this.data.answers
    }

    // 本地缓存 + 全局缓存
    wx.setStorageSync('discResult', resultData)
    if (app && typeof app.saveTestResult === 'function') {
      app.saveTestResult('disc', resultData)
    }

    wx.redirectTo({ url: '/pages/result/disc' })
  }
})
