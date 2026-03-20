// pages/result/resume.js - 简历综合分析结果页
const app = getApp()
const payment = require('../../utils/payment')

Page({
  data: {
    loading: true,
    error: '',
    content: '',
    sections: [],
    resumeData: null,
    fileUrl: '',
    progress: 0,
    analyzingTip: '正在准备分析数据...',
    analyzingTitle: '综合分析生成中',
    // 支付相关
    payInfo: {
      requiresPayment: false,
      isPaid: false,
      amountYuan: 0
    },
    testResultId: 0,
    paying: false
  },

  onLoad(options) {
    const fileUrl = options.fileUrl ? decodeURIComponent(options.fileUrl) : ''
    this.setData({ fileUrl })

    // 从历史记录进入：直接读已存的数据，不重新生成
    if (options.id && options.type === 'resume') {
      this.loadFromHistory(options.id)
    } else {
      this.fetchResumeAnalysis()
    }
  },

  loadFromHistory(id) {
    this.setData({ loading: true, error: '', content: '', sections: [], resumeData: null })
    const apiBase = (app.globalData && app.globalData.apiBase) || wx.getStorageSync('apiBase') || ''
    const token = (app.globalData && app.globalData.token) || wx.getStorageSync('token') || ''
    wx.request({
      url: `${apiBase}/api/test/detail?id=${id}`,
      method: 'GET',
      timeout: 15000,
      header: { 'Authorization': token ? `Bearer ${token}` : '' },
      success: (res) => {
        if (res.statusCode === 200 && res.data && res.data.data) {
          const payload = res.data.data
          let data = payload.data || payload

          // 兼容：历史数据可能是字符串，或包在 content 里的 JSON 字符串
          data = this.normalizeStructuredData(data)

          const structured = !!data && data._structured === true

          let content = ''
          let sections = []

          if (structured) {
            const built = this.buildSectionsFromStructured(data)
            content = built.content
            sections = built.sections
          } else {
            const raw = data.content || ''
            content = raw
            sections = this.parseContent(raw)
          }
          const requiresPayment = !!payload.requiresPayment
          const isPaid = !!payload.isPaid
          const amountYuan = payload.paidAmount ? payload.paidAmount / 100 : 0
          this.setData({
            loading: false,
            testResultId: payload.id || parseInt(id) || 0,
            content: (!requiresPayment || isPaid) ? content : '',
            sections: (!requiresPayment || isPaid) ? sections : [],
            resumeData: structured ? data : null,
            payInfo: { requiresPayment: requiresPayment && !isPaid, isPaid, amountYuan }
          })
        } else {
          this.setData({ loading: false, error: '加载历史记录失败，请返回重试。' })
        }
      },
      fail: () => {
        this.setData({ loading: false, error: '网络请求失败，请检查网络后重试。' })
      }
    })
  },

  fetchResumeAnalysis() {
    this.setData({ loading: true, error: '', content: '', sections: [], resumeData: null, progress: 0 })
    
    const tips = [
      '正在汇总各项测评结果...',
      '正在解析简历内容...',
      '正在进行深度匹配分析...',
      '正在生成综合评估报告...',
      '分析即将完成，请稍候...'
    ]
    let progress = 0
    let tipIndex = 0

    // 进度动画
    const timer = setInterval(() => {
      progress += 2
      if (progress > 98) progress = 98
      if (progress > (tipIndex + 1) * 18 && tipIndex < tips.length - 1) tipIndex++
      this.setData({ progress: Math.floor(progress), analyzingTip: tips[tipIndex] })
    }, 200)

    const apiBase = (app.globalData && app.globalData.apiBase) || wx.getStorageSync('apiBase') || ''
    const token = (app.globalData && app.globalData.token) || wx.getStorageSync('token') || ''

    // 获取 enterpriseId
    const gd = app.globalData || {}
    const storedUser = wx.getStorageSync('userInfo') || null
    const enterpriseId = gd.enterpriseIdFromScene
      || (gd.userInfo && gd.userInfo.enterpriseId)
      || (storedUser && storedUser.enterpriseId)
      || null

    const postData = {}
    if (this.data.fileUrl) postData.fileUrl = this.data.fileUrl
    if (enterpriseId) postData.enterpriseId = enterpriseId

    wx.request({
      url: `${apiBase}/api/resume/analyze`,
      method: 'POST',
      timeout: 120000,
      header: {
        'Content-Type': 'application/json',
        'Authorization': token ? `Bearer ${token}` : ''
      },
      data: postData,
      success: (res) => {
        clearInterval(timer)
        if (res.statusCode === 200 && res.data) {
          if (res.data.code === 200) {
            let d = res.data.data || {}
            const p = d._payment || {}
            const requiresPayment = !!p.requiresPayment
            const amountYuan = p.amountYuan || 0
            const resultId = d._testResultId || 0

            // 兼容：API 直接返回结构化对象，或把 JSON 放在 content 里
            d = this.normalizeStructuredData(d)

            const structured = !!d && d._structured === true

            let content = ''
            let sections = []

            if (structured) {
              const built = this.buildSectionsFromStructured(d)
              content = built.content
              sections = built.sections
            } else {
              const raw = d.content || ''
              content = raw || '分析已完成，但未返回可展示的内容。'
              sections = this.parseContent(raw)
            }

            this.setData({
              loading: false,
              progress: 100,
              testResultId: resultId,
              content: requiresPayment ? '' : content,
              sections: requiresPayment ? [] : sections,
              resumeData: structured ? d : null,
              payInfo: { requiresPayment, isPaid: !requiresPayment, amountYuan }
            })
          } else {
            this.setData({
              loading: false,
              error: res.data.message || res.data.msg || '综合分析失败，请稍后重试。'
            })
          }
        } else {
          this.setData({
            loading: false,
            error: '服务器返回异常，请稍后重试。'
          })
        }
      },
      fail: (err) => {
        clearInterval(timer)
        const isTimeout = err && (err.errMsg || '').indexOf('timeout') !== -1
        this.setData({
          loading: false,
          error: isTimeout ? '分析耗时较长，请点击「重新生成」再试。' : '网络请求失败，请检查网络后重试。'
        })
      }
    })
  },

  // 支付解锁
  doPay() {
    if (this.data.paying) return
    this.setData({ paying: true })
    const testResultId = this.data.testResultId || 0
    payment.purchaseResumeAnalysis({
      testResultId: testResultId > 0 ? testResultId : undefined,
      success: () => {
        this.setData({ paying: false, 'payInfo.requiresPayment': false, 'payInfo.isPaid': true })
        // 支付成功后重新拉取完整内容
        if (testResultId > 0) {
          this.loadFromHistory(testResultId)
        }
      },
      fail: (err) => {
        this.setData({ paying: false })
        wx.showToast({ title: (err && err.message) || '支付失败，请重试', icon: 'none' })
      }
    })
  },

  goHome() {
    const scope = (app.globalData && app.globalData.appScope) || 'personal'
    if (scope === 'enterprise') {
      wx.navigateTo({ url: '/pages/enterprise/index' })
    } else {
      wx.switchTab({ url: '/pages/index/index' })
    }
  },

  onShareAppMessage() {
    return {
      title: '我刚刚完成了一份人才简历综合分析报告，快来看看吧！',
      path: '/pages/index/index'
    }
  },

  onPullDownRefresh() {
    this.fetchResumeAnalysis()
    wx.stopPullDownRefresh()
  },

  // 智能解析 AI 输出的长文本，按标题分块
  parseContent(content) {
    if (!content) return [];
    
    // 1. 预处理：去掉首尾空行
    content = content.trim();
    
    // 2. 尝试分割
    // 使用正则匹配头部标记：## Title 或 ### Title 或 **一、Title** 或 一、**Title**
    const parts = content.split(/\n(?=#{2,4}\s+|\*\*?[\d一二三四五六七八九十]+[、\.].*?\*\*?|【.*?】)/);
    const sections = [];

    parts.forEach(part => {
      let title = '';
      let body = '';

      // 匹配 ## 标题
      const hMatch = part.match(/^(?:#{2,4}\s+)(.*?)\n([\s\S]*)$/m);
      // 匹配 **一、标题** 或 一、**标题**
      const bMatch = part.match(/^(?:\*\*?[\d一二三四五六七八九十]+[、\.].*?\*\*?)([\s\S]*)$/m);
      // 匹配 【标题】
      const kMatch = part.match(/^(?:【(.*?)】)\n?([\s\S]*)$/m);

      if (hMatch) {
        title = hMatch[1].replace(/[\*#]/g, '').trim();
        body = hMatch[2].trim();
      } else if (kMatch) {
        title = kMatch[1].trim();
        body = kMatch[2].trim();
      } else if (bMatch) {
        const rawTitleMatch = part.match(/^(\*\*?.*?\*\*?)/);
        title = rawTitleMatch ? rawTitleMatch[1].replace(/[\*#]/g, '').trim() : '';
        body = bMatch[1].trim();
      } else {
        body = part.trim();
      }

      // 清洗正文中的 Markdown 符号
      body = this.cleanMarkdown(body);

      if (title || body) {
        sections.push({
          title: title || '报告详情',
          icon: this.getSectionIcon(title),
          body: body
        });
      }
    });

    return sections;
  },

  // 兼容各种后端返回形态，统一整理为结构化对象或普通文本
  normalizeStructuredData(raw) {
    let data = raw

    // 字符串：尝试直接当 JSON 解析
    if (typeof data === 'string') {
      try {
        const parsed = JSON.parse(data)
        if (parsed && typeof parsed === 'object') {
          data = parsed
        }
      } catch (e) {}
    }

    // 包在 content 里的 JSON 字符串
    if (data && typeof data === 'object' && typeof data.content === 'string') {
      const text = data.content.trim()
      if (text.startsWith('{')) {
        try {
          const parsedInner = JSON.parse(text)
          if (parsedInner && typeof parsedInner === 'object') {
            // 把外层 fileUrl 等透传下去
            if (data.fileUrl && !parsedInner.fileUrl) {
              parsedInner.fileUrl = data.fileUrl
            }
            data = parsedInner
          }
        } catch (e) {}
      }
    }

    // 标记是否为结构化 JSON
    if (
      data &&
      typeof data === 'object' &&
      (data.version === 2 ||
        !!data.overview ||
        !!data.portrait ||
        !!data.hrView ||
        !!data.bossView)
    ) {
      data._structured = true
    }

    return data
  },

  // 从结构化 JSON 构建前端展示区块
  buildSectionsFromStructured(data) {
    const sections = []

    if (data.overview) {
      sections.push({
        title: '整体人才画像',
        icon: '👤',
        body: this.cleanMarkdown(data.overview)
      })
    }

    if (data.resumeHighlights) {
      sections.push({
        title: '简历要点',
        icon: '📄',
        body: this.cleanMarkdown(data.resumeHighlights)
      })
    }

    const portrait = data.portrait || {}
    if (
      (portrait.coreStrengths && portrait.coreStrengths.length) ||
      (portrait.coreRisks && portrait.coreRisks.length) ||
      portrait.workStyle
    ) {
      const lines = []
      if (portrait.coreStrengths && portrait.coreStrengths.length) {
        lines.push('【核心优势】')
        lines.push(...portrait.coreStrengths.map((s) => '- ' + s))
      }
      if (portrait.coreRisks && portrait.coreRisks.length) {
        lines.push('')
        lines.push('【潜在风险】')
        lines.push(...portrait.coreRisks.map((s) => '- ' + s))
      }
      if (portrait.workStyle) {
        lines.push('')
        lines.push('【工作风格】')
        lines.push(portrait.workStyle)
      }
      sections.push({
        title: '人才画像',
        icon: '🌈',
        body: this.cleanMarkdown(lines.join('\n'))
      })
    }

    const hrView = data.hrView || {}
    const role = hrView.roleRecommend || {}
    if ((role.bestFit && role.bestFit.length) || (role.notSuitable && role.notSuitable.length)) {
      const lines = []
      if (role.bestFit && role.bestFit.length) {
        lines.push('【推荐岗位】')
        lines.push(...role.bestFit.map((s) => '- ' + s))
      }
      if (role.notSuitable && role.notSuitable.length) {
        lines.push('')
        lines.push('【不适合场景】')
        lines.push(...role.notSuitable.map((s) => '- ' + s))
      }
      sections.push({
        title: '岗位匹配建议（HR视角）',
        icon: '🧭',
        body: this.cleanMarkdown(lines.join('\n'))
      })
    }

    if (hrView.lifecycle) {
      const lc = hrView.lifecycle
      const parts = []
      if (lc.onboarding) parts.push('【入职】' + lc.onboarding)
      if (lc.probation) parts.push('【试用期】' + lc.probation)
      if (lc.growth) parts.push('【成长】' + lc.growth)
      if (lc.retention) parts.push('【留存】' + lc.retention)
      if (parts.length) {
        sections.push({
          title: '员工全生命周期预测',
          icon: '📈',
          body: this.cleanMarkdown(parts.join('\n\n'))
        })
      }
    }

    if (hrView.performance || hrView.teamFit || hrView.complianceRisk) {
      const lines = []
      if (hrView.performance) {
        lines.push('【绩效潜力】' + (hrView.performance.potential || ''))
        if (hrView.performance.drivers && hrView.performance.drivers.length) {
          lines.push('驱动因素：' + hrView.performance.drivers.join('；'))
        }
        if (hrView.performance.risks && hrView.performance.risks.length) {
          lines.push('风险提示：' + hrView.performance.risks.join('；'))
        }
        lines.push('')
      }
      if (hrView.teamFit) {
        if (hrView.teamFit.bestTeam) {
          lines.push('【适配团队】' + hrView.teamFit.bestTeam)
        }
        if (hrView.teamFit.manageAdvice) {
          lines.push('【管理建议】' + hrView.teamFit.manageAdvice)
        }
        lines.push('')
      }
      if (hrView.complianceRisk) {
        const level = hrView.complianceRisk.level || ''
        const notes = hrView.complianceRisk.notes || ''
        lines.push('【合规风险】' + level + (notes ? '；' + notes : ''))
      }
      if (lines.length) {
        sections.push({
          title: '绩效 · 团队 · 合规',
          icon: '📊',
          body: this.cleanMarkdown(lines.join('\n'))
        })
      }
    }

    const bossView = data.bossView || {}
    if (bossView.headline || (bossView.metrics && bossView.metrics.length) || bossView.costInsight) {
      const lines = []
      if (bossView.headline) {
        lines.push('【一句话结论】' + bossView.headline)
      }
      if (bossView.metrics && bossView.metrics.length) {
        lines.push('')
        lines.push('【关键指标】')
        bossView.metrics.forEach((m) => {
          if (!m) return
          lines.push(`- ${m.label}：${m.value || ''}`)
        })
      }
      if (bossView.costInsight) {
        lines.push('')
        lines.push('【用人成本与产出】' + bossView.costInsight)
      }
      sections.push({
        title: '给老板看的摘要',
        icon: '💼',
        body: this.cleanMarkdown(lines.join('\n'))
      })
    }

    const mainContent =
      data.overview ||
      data.resumeHighlights ||
      (data.portrait && data.portrait.workStyle) ||
      ''

    return {
      content: this.cleanMarkdown(mainContent),
      sections
    }
  },

  // 清洗正文中的 Markdown 符号
  cleanMarkdown(text) {
    if (!text) return '';
    return text
      .replace(/\*\*\*(.+?)\*\*\*/g, '$1')   // ***bold italic***
      .replace(/\*\*(.+?)\*\*/g, '$1')        // **bold**
      .replace(/\*(.+?)\*/g, '$1')            // *italic*
      .replace(/^#{1,6}\s+/gm, '')            // ## 标题符号
      .replace(/^[-*]\s+/gm, '• ')            // - 无序列表 → 圆点
      .replace(/^\d+\.\s+/gm, (m) => m)      // 保留有序列表编号
      .replace(/`(.+?)`/g, '$1')              // `code`
      .replace(/\[(.+?)\]\(.+?\)/g, '$1')    // [链接](url) → 链接文字
      .trim();
  },

  // 根据标题关键词匹配图标
  getSectionIcon(title) {
    if (!title) return '📝';
    const map = {
      '评估': '📊', '综述': '📊', '评价': '📊', '总评': '📊',
      '优势': '🌟', '特质': '🌟', '性格': '🌟',
      '岗位': '🎯', '适配': '🎯', '匹配': '🎯', '核心': '🎯',
      '职业': '💼', '发展': '💼', '建议': '💡', '提升': '💡',
      '面试': '🗣️', '录用': '✅', '风险': '⚠️'
    };
    for (let key in map) {
      if (title.indexOf(key) !== -1) return map[key];
    }
    return '📝';
  }
})

