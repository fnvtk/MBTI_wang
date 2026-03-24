// pages/test/mbti.js - MBTI测试页面逻辑
const { mbtiQuestions, shuffleQuestions } = require('../../utils/questions')
const { mbtiDescriptions } = require('../../utils/descriptions')
const payment = require('../../utils/payment')
const app = getApp()

Page({
  data: {
    questions: [],
    currentIndex: 0,
    currentQuestion: null,
    answers: {},
    selectedAnswer: null,
    total: mbtiQuestions.length,
    answeredCount: 0,
    progress: 0,
    timeRemaining: 30 * 60, // 30分钟
    formatTime: '30:00',
    isSubmitting: false,
    canAccess: false
  },

  timer: null,

  onLoad() {
    const questions = shuffleQuestions(mbtiQuestions)
    this.setData({
      questions,
      currentQuestion: questions[0],
      canAccess: true
    })
    this.startTimer()
  },

  // 检查访问权限
  checkAccess() {
    // 当前策略：所有测试免费开放，直接允许访问
    // 若后续恢复收费，可重新启用 payment.canTakeTest 等校验逻辑
    return true
  },

  onUnload() {
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

  // 选择答案
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

    // 自动跳转下一题
    setTimeout(() => {
      if (this.data.currentIndex < this.data.total - 1) {
        this.nextQuestion()
      }
    }, 300)
  },

  // 上一题
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

  // 下一题
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

  // 提交测试
  submitTest() {
    if (this.data.isSubmitting) return
    this.setData({ isSubmitting: true })

    const result = this.calculateResult()
    
    // 保存结果
    const resultData = {
      ...result,
      testDuration: 30 * 60 - this.data.timeRemaining,
      completedAt: new Date().toISOString(),
      timestamp: new Date().toISOString()
    }
    tt.setStorageSync('mbtiResult', resultData)
    app.saveTestResult('mbti', resultData)

    // 跳转到结果页
    tt.redirectTo({
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

    // 计算各维度百分比
    const dimensionScores = {
      EI: { E: scores.E, I: scores.I, dominant: scores.E >= scores.I ? 'E' : 'I', percentage: Math.round((Math.max(scores.E, scores.I) / (scores.E + scores.I)) * 100) },
      SN: { S: scores.S, N: scores.N, dominant: scores.S >= scores.N ? 'S' : 'N', percentage: Math.round((Math.max(scores.S, scores.N) / (scores.S + scores.N)) * 100) },
      TF: { T: scores.T, F: scores.F, dominant: scores.T >= scores.F ? 'T' : 'F', percentage: Math.round((Math.max(scores.T, scores.F) / (scores.T + scores.F)) * 100) },
      JP: { J: scores.J, P: scores.P, dominant: scores.J >= scores.P ? 'J' : 'P', percentage: Math.round((Math.max(scores.J, scores.P) / (scores.J + scores.P)) * 100) }
    }

    // 计算置信度
    const confidence = Math.round(
      (dimensionScores.EI.percentage + dimensionScores.SN.percentage + 
       dimensionScores.TF.percentage + dimensionScores.JP.percentage) / 4
    )

    return {
      mbtiType,
      scores,
      dimensionScores,
      confidence,
      description: mbtiDescriptions[mbtiType] || {}
    }
  }
})
