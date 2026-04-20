// pages/match-job/index.js - 匹配工作（与「我的」同源拉取性格画像 + 简历 + AI 综合分析，含面相）
const app = getApp()
const { request } = require('../../utils/request')
const { getTypeOnly } = require('../../utils/resultFormat')

/** 与 profile 页 /api/test/recent 解析一致 */
function summaryFromRecentRecord(rec, testType) {
  if (!rec) return ''
  const meta = rec.resultMeta
  if (meta && typeof meta === 'object') {
    const t = getTypeOnly(meta, testType)
    if (t) return t
  }
  return String(rec.resultText || '').trim()
}

const DEFAULT_MBTI_JOB = {
  ISTJ: '适合财务、审计、行政、项目经理等结构化岗位。',
  ISFJ: '护理、教师、客户服务、HR 等以关怀为主的岗位。',
  INFJ: '心理咨询、人力资源、作家、战略策划等职业。',
  INTJ: '战略顾问、架构师、产品总监、科研等。',
  ISTP: '工程师、技术员、运维、维修、运动相关。',
  ISFP: '设计师、艺术家、摄影师、UX 等。',
  INFP: '作家、心理咨询、社工、内容策划等。',
  INTP: '程序员、研究员、数据分析师、系统架构师。',
  ESTP: '销售、创业、运动员、媒体、现场管理。',
  ESFP: '演员、销售、主持、公关、活动策划。',
  ENFP: '市场营销、新媒体、咨询、品牌。',
  ENTP: '律师、企业家、创投、产品、战略咨询。',
  ESTJ: '管理者、项目经理、运营总监、律师。',
  ESFJ: '教师、护士、HR、运营管理、社区。',
  ENFJ: '培训师、HR、教师、销售总监、咨询。',
  ENTJ: 'CEO、创业者、咨询合伙人、业务负责人。'
}
const DEFAULT_PDP_JOB = {
  Tiger: '老虎型适合担任决策者/领导者岗位：CEO、业务负责人、销售总监。',
  Peacock: '孔雀型适合表达/影响岗位：市场、公关、培训、销售、主播。',
  Koala: '无尾熊型适合支持/稳定岗位：HR、客户服务、行政、项目协作。',
  Owl: '猫头鹰型适合分析/精准岗位：数据分析、研发、审计、质量管理。',
  Chameleon: '变色龙型适合协调/多面手岗位：顾问、项目经理、咨询、创业。'
}
const DEFAULT_DISC_JOB = {
  D: 'D 型适合业务开拓/领导类岗位：销售、创业、管理。',
  I: 'I 型适合表达影响类岗位：市场、公关、培训、社群。',
  S: 'S 型适合服务支持类岗位：HR、客服、运营、项目协作。',
  C: 'C 型适合分析严谨类岗位：研发、数据、财务、质量控制。'
}

Page({
  data: {
    mbtiType: '',
    pdpType: '',
    discType: '',
    sbtiType: '',
    faceType: '',
    hasFaceReport: false,
    mbtiJobHint: '',
    pdpJobHint: '',
    discJobHint: '',
    hasAnyTest: false,
    showRuleDetail: false,
    resumeUrl: '',
    resumeFileName: '',
    uploading: false,
    analyzing: false,
    analyzed: false,
    analyzeResult: null
  },

  onLoad() {
    try {
      require('../../utils/analytics').track('page_view', { path: 'pages/match-job/index' })
    } catch (e) {}
    app.ensureLogin &&
      app.ensureLogin().then(() => {
        this.loadPortraitFromRecent()
        this.loadDefaultResume()
      })
  },

  onShow() {
    this.loadPortraitFromRecent()
  },

  toggleRuleDetail() {
    this.setData({ showRuleDetail: !this.data.showRuleDetail })
  },

  loadPortraitFromRecent() {
    request({
      url: '/api/test/recent?scope=all',
      method: 'GET',
      needAuth: true,
      success: (res) => {
        const payload = res && res.data
        if (!payload || payload.code !== 200 || !payload.data) return
        const { records = {} } = payload.data
        const r = records
        const mbti = summaryFromRecentRecord(r.mbti, 'mbti')
        const pdp = summaryFromRecentRecord(r.pdp, 'pdp')
        const disc = summaryFromRecentRecord(r.disc, 'disc')
        const sbti = summaryFromRecentRecord(r.sbti, 'sbti')
        const faceText = r.ai ? String(r.ai.resultText || '').trim() : ''
        const hasFaceReport = !!r.ai
        const hasAnyTest = !!(r.mbti || r.sbti || r.disc || r.pdp || r.ai)
        this.setData({
          mbtiType: mbti,
          pdpType: pdp,
          discType: disc,
          sbtiType: sbti,
          faceType: faceText,
          hasFaceReport,
          hasAnyTest,
          mbtiJobHint: mbti ? DEFAULT_MBTI_JOB[mbti] || '结合所属类别的典型岗位方向。' : '',
          pdpJobHint: pdp ? DEFAULT_PDP_JOB[pdp] || '' : '',
          discJobHint: disc ? DEFAULT_DISC_JOB[(disc || '').charAt(0).toUpperCase()] || '' : ''
        })
      },
      fail: () => {}
    })
  },

  loadDefaultResume() {
    request({
      url: '/api/enterprise/resume-uploads?pageSize=1',
      method: 'GET',
      needAuth: true,
      success: (res) => {
        const list =
          (res && res.data && res.data.code === 200 && res.data.data && res.data.data.list) || []
        const def = list.find((x) => x.isDefault) || list[0]
        if (def) {
          this.setData({ resumeUrl: def.url || '', resumeFileName: def.fileName || '' })
        }
      },
      fail: () => {}
    })
  },

  goToTestSelect() {
    wx.navigateTo({ url: '/pages/test-select/index' })
  },

  goCamera() {
    wx.switchTab({ url: '/pages/index/camera' })
  },

  chooseResume() {
    try {
      require('../../utils/analytics').track('tap_match_upload_resume', {})
    } catch (e) {}
    wx.chooseMessageFile({
      count: 1,
      type: 'file',
      extension: ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'doc', 'docx'],
      success: (fileRes) => {
        const files = fileRes.tempFiles || []
        if (!files.length) return
        const file = files[0]
        const filePath = file.path || file.tempFilePath
        const fileName = file.name || '简历文件'
        this._doUpload(filePath, fileName)
      }
    })
  },

  _doUpload(filePath, fileName) {
    this.setData({ uploading: true })
    const apiBase = app.globalData && app.globalData.apiBase ? app.globalData.apiBase.replace(/\/$/, '') : ''
    const token = (app.globalData && app.globalData.token) || wx.getStorageSync('token') || ''
    wx.showLoading({ title: '上传中...', mask: true })
    wx.uploadFile({
      url: apiBase + '/api/upload/file',
      filePath,
      name: 'file',
      header: token ? { Authorization: 'Bearer ' + token } : {},
      success: (res) => {
        wx.hideLoading()
        try {
          const data = JSON.parse(res.data)
          if (data.code === 200 && data.data && data.data.url) {
            const url = data.data.url
            request({
              url: '/api/enterprise/resume-uploads',
              method: 'POST',
              needAuth: true,
              data: { url, fileName },
              success: () => {},
              fail: () => {}
            })
            this.setData({ resumeUrl: url, resumeFileName: fileName })
            wx.showToast({ title: '上传成功', icon: 'success' })
          } else {
            wx.showToast({ title: data.message || '上传失败', icon: 'none' })
          }
        } catch (e) {
          wx.showToast({ title: '解析上传结果失败', icon: 'none' })
        }
      },
      fail: () => {
        wx.hideLoading()
        wx.showToast({ title: '上传失败', icon: 'none' })
      },
      complete: () => this.setData({ uploading: false })
    })
  },

  previewResume() {
    const url = this.data.resumeUrl
    if (!url) return
    const apiBase = app.globalData && app.globalData.apiBase ? app.globalData.apiBase.replace(/\/$/, '') : ''
    const full = url.startsWith('http') ? url : apiBase + url
    wx.downloadFile({
      url: full,
      success: (res) => {
        if (res.statusCode === 200 && res.tempFilePath) {
          wx.openDocument({ filePath: res.tempFilePath, showMenu: true })
        } else {
          wx.showToast({ title: '打开失败', icon: 'none' })
        }
      },
      fail: () => wx.showToast({ title: '打开失败', icon: 'none' })
    })
  },

  runAnalyze() {
    if (!this.data.hasAnyTest) {
      wx.showToast({ title: '请先完成问卷或面相拍摄', icon: 'none' })
      return
    }
    if (!this.data.resumeUrl) {
      wx.showToast({ title: '请先上传简历', icon: 'none' })
      return
    }
    this.setData({ analyzing: true })
    try {
      require('../../utils/analytics').track('tap_match_analyze', {
        mbti: this.data.mbtiType,
        pdp: this.data.pdpType,
        face: this.data.hasFaceReport
      })
    } catch (e) {}
    wx.showLoading({ title: 'AI 分析中...', mask: true })
    const fileUrl = this.data.resumeUrl
    request({
      url: '/api/resume/analyze',
      method: 'POST',
      needAuth: true,
      timeout: 120000,
      data: { fileUrl, resumeUrl: fileUrl },
      success: (res) => {
        wx.hideLoading()
        if (res.statusCode === 200 && res.data && res.data.code === 200) {
          const d = res.data.data || {}
          this.setData({
            analyzeResult: this._normalizeAnalyzeResult(d),
            analyzed: true
          })
        } else {
          wx.showToast({ title: (res.data && res.data.message) || '分析失败', icon: 'none' })
        }
      },
      fail: () => {
        wx.hideLoading()
        wx.showToast({ title: '网络错误，请重试', icon: 'none' })
      },
      complete: () => this.setData({ analyzing: false })
    })
  },

  _normalizeAnalyzeResult(d) {
    const portrait = d.portrait && typeof d.portrait === 'object' ? d.portrait : {}
    const hrView = d.hrView && typeof d.hrView === 'object' ? d.hrView : {}
    const roleRec = hrView.roleRecommend && typeof hrView.roleRecommend === 'object' ? hrView.roleRecommend : {}

    let fit = Array.isArray(d.fitRoles) ? d.fitRoles : []
    if (!fit.length && Array.isArray(roleRec.bestFit)) fit = roleRec.bestFit
    if (!fit.length && Array.isArray(portrait.bestFit)) fit = portrait.bestFit

    let strengths = Array.isArray(d.strengths) ? d.strengths : []
    if (!strengths.length && Array.isArray(portrait.coreStrengths)) strengths = portrait.coreStrengths
    if (!strengths.length && Array.isArray(d.resumeHighlights)) strengths = d.resumeHighlights.slice(0, 8)

    let weaknesses = Array.isArray(d.weaknesses) ? d.weaknesses : []
    if (!weaknesses.length && Array.isArray(portrait.coreRisks)) weaknesses = portrait.coreRisks

    const summary =
      d.summary ||
      d.overview ||
      portrait.workStyle ||
      (typeof d.content === 'string' ? d.content : '') ||
      ''

    const advice = d.advice || d.nextStep || (hrView.lifecycle && hrView.lifecycle.growth) || ''

    let score = typeof d.score === 'number' ? d.score : null
    if (score == null && d.matchScore != null) score = Number(d.matchScore)
    if (score == null && typeof d.hrScore === 'number') score = d.hrScore

    return {
      score,
      fitRoles: fit.slice(0, 5),
      summary,
      strengths: strengths.slice(0, 8),
      weaknesses: weaknesses.slice(0, 6),
      advice: typeof advice === 'string' ? advice : ''
    }
  }
})
