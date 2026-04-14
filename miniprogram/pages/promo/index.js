// pages/promo/index.js
const app = getApp()
const { request } = require('../../utils/request')

Page({
  data: {
    balance: '0.00',
    totalEarned: '0.00',
    pendingAmount: '0.00',
    bindingCount: 0,
    paidCount: 0,
    expiringCount: 0,
    activeTab: 0,
    userList: [],
    listTotal: 0,
    listPage: 1,
    listLoading: false,
    listFinished: false,
    commissionRate: '',
    bindingDays: '',
    testCommissionType: '',
    testCommissionRate: '',
    testCommissionAmount: '',
    testNoPayment: false,
    withdrawMinYuan: '1.00',
    withdrawMaxYuan: '',
    withdrawFeePct: 0,
    requireWithdrawAudit: true,
    showWithdrawDialog: false,
    withdrawAmountInput: '',
    withdrawError: '',
    withdrawFeeYuan: '0.00',
    withdrawActualYuan: '0.00'
  },

  onLoad() {
    // 分享直达时 silentLogin 可能未完成，先 ensureLogin 再拉推广数据，避免 401 空白
    app.ensureLogin()
      .then((ok) => {
        if (!ok) {
          wx.showToast({ title: '请先登录后查看推广中心', icon: 'none' })
          return
        }
        this.loadStats()
        this.loadBindings(true)
      })
      .catch(() => {
        wx.showToast({ title: '登录失败，请重试', icon: 'none' })
      })
  },

  onShow() {
    const token = app.globalData.token || wx.getStorageSync('token')
    if (token) this.loadStats()
  },

  /** 加载推广统计数据 */
  loadStats() {
    request({
      url: '/api/distribution/stats',
      method: 'GET',
      success: (res) => {
        const payload = res && res.data
        if (payload && payload.code === 200 && payload.data) {
          const d = payload.data
          const title = d.promoCenterTitle || '推广中心'
          wx.setNavigationBarTitle({ title })
          this.setData({
            balance: d.walletBalance,
            totalEarned: d.totalEarned,
            pendingAmount: d.pendingAmount,
            bindingCount: d.bindingCount,
            paidCount: d.paidCount,
            expiringCount: d.expiringCount,
            totalInvite: d.totalInvite,
            commissionRate: d.commissionRate,
            bindingDays: d.bindingDays,
            testCommissionType: d.testCommissionType,
            testCommissionRate: d.testCommissionRate,
            testCommissionAmount: d.testCommissionAmount,
            testNoPayment: d.testNoPayment,
            withdrawMinYuan: d.withdrawMinYuan != null ? d.withdrawMinYuan : '1.00',
            withdrawMaxYuan: d.withdrawMaxYuan != null && d.withdrawMaxYuan !== '' ? d.withdrawMaxYuan : '',
            withdrawFeePct: d.withdrawFeePct != null ? d.withdrawFeePct : 0,
            requireWithdrawAudit: d.requireWithdrawAudit !== false,
          })
        }
      }
    })
  },

  /** 加载绑定用户列表（tab: 0=绑定中 1=已付款 2=已过期） */
  loadBindings(reset = false) {
    if (this.data.listLoading) return
    if (!reset && this.data.listFinished) return

    const page = reset ? 1 : this.data.listPage
    this.setData({ listLoading: true })

    request({
      url: `/api/distribution/bindings?tab=${this.data.activeTab}&page=${page}&pageSize=10`,
      method: 'GET',
      success: (res) => {
        const payload = res && res.data
        if (payload && payload.code === 200 && payload.data) {
          const { list, total } = payload.data
          const formatted = (list || []).map(item => ({
            ...item,
            createdAtStr: item.createdAt ? this._fmtTimestamp(item.createdAt) : ''
          }))
          const newList = reset ? formatted : [...this.data.userList, ...formatted]
          this.setData({
            userList: newList,
            listTotal: total,
            listPage: page + 1,
            listFinished: newList.length >= total,
          })
        }
      },
      complete: () => {
        this.setData({ listLoading: false })
      }
    })
  },

  /** 时间戳格式化为 YYYY-MM-DD */
  _fmtTimestamp(ts) {
    if (!ts) return ''
    const d = new Date(ts * 1000)
    if (isNaN(d.getTime())) return ''
    const y = d.getFullYear()
    const m = String(d.getMonth() + 1).padStart(2, '0')
    const day = String(d.getDate()).padStart(2, '0')
    return `${y}-${m}-${day}`
  },

  /** 切换用户列表 Tab */
  switchTab(e) {
    const index = parseInt(e.currentTarget.dataset.index)
    if (index === this.data.activeTab) return
    this.setData({ activeTab: index, userList: [], listPage: 1, listFinished: false })
    this.loadBindings(true)
  },

  /** 上拉加载更多 */
  onReachBottom() {
    this.loadBindings(false)
  },

  /** 申请提现：打开自定义金额弹框 */
  handleWithdraw() {
    const balance = parseFloat(this.data.balance)
    if (balance < 1) {
      wx.showToast({ title: '余额不足1元，暂无法提现', icon: 'none' })
      return
    }
    const pct = this.data.withdrawFeePct || 0
    const feeYuan = (balance * pct / 100).toFixed(2)
    const actualYuan = (balance - balance * pct / 100).toFixed(2)
    this.setData({
      showWithdrawDialog: true,
      withdrawAmountInput: this.data.balance,
      withdrawFeeYuan: feeYuan,
      withdrawActualYuan: actualYuan,
      withdrawError: ''
    })
  },

  /** 关闭提现弹框 */
  closeWithdrawDialog() {
    this.setData({
      showWithdrawDialog: false,
      withdrawError: ''
    })
  },

  /** 输入金额：实时计算手续费与实际到账 */
  onWithdrawInput(e) {
    const raw = e.detail.value
    const val = parseFloat(raw)
    const pct = this.data.withdrawFeePct || 0
    let feeYuan = '0.00'
    let actualYuan = '0.00'
    if (raw !== '' && !isNaN(val) && val >= 0) {
      const fee = val * pct / 100
      feeYuan = fee.toFixed(2)
      actualYuan = (val - fee).toFixed(2)
    }
    this.setData({
      withdrawAmountInput: raw,
      withdrawFeeYuan: feeYuan,
      withdrawActualYuan: actualYuan,
      withdrawError: ''
    })
  },

  /** 确认提现（使用用户填写的金额） */
  confirmWithdraw() {
    const balance = parseFloat(this.data.balance)
    const val = parseFloat(this.data.withdrawAmountInput)
    const minYuan = parseFloat(this.data.withdrawMinYuan) || 1
    const pct = this.data.withdrawFeePct || 0

    if (isNaN(val)) {
      this.setData({ withdrawError: '请输入正确的金额' })
      return
    }
    if (val < 1) {
      this.setData({ withdrawError: '单次提现金额至少 1 元' })
      return
    }
    if (val > balance) {
      this.setData({ withdrawError: '不可超过当前可提现金额' })
      return
    }
    const actualYuan = val - val * pct / 100
    if (actualYuan < minYuan) {
      this.setData({ withdrawError: `实际到账金额不得低于最低提现金额 ¥${minYuan.toFixed(2)}` })
      return
    }

    const amountFen = Math.floor(val * 100)
    this.setData({ withdrawError: '' })

    request({
      url: '/api/distribution/withdraw',
      method: 'POST',
      data: { amountFen },
      success: (r) => {
        const payload = r && r.data
        if (payload && payload.code === 200) {
          const msg = payload.msg || payload.message || ''
          wx.showToast({ title: '申请已提交', icon: 'success' })
          this.setData({ showWithdrawDialog: false })
          this.loadStats()
          // 免审核且已自动发起微信转账时，自动进入提现记录页
          if (msg.indexOf('已自动发起') !== -1) {
            setTimeout(() => {
              wx.navigateTo({ url: '/pages/promo/withdrawals' })
            }, 500)
          }
        } else {
          const errMsg = (payload && (payload.msg || payload.message)) || '申请失败，请稍后重试'
          this.setData({ withdrawError: errMsg })
        }
      }
    })
  },

  /** 查看提现记录 */
  goToWithdrawHistory() {
    wx.navigateTo({ url: '/pages/promo/withdrawals' })
  },

  /** 生成海报 */
  generatePoster() {
    wx.navigateTo({ url: '/pages/promo/poster' })
  },

  /** 分享到朋友圈：引导用户使用右上角菜单 */
  shareToTimeline() {
    wx.showToast({ title: '请点击右上角 ··· 选择「分享到朋友圈」', icon: 'none', duration: 2500 })
  },

  /** 分享给好友 */
  onShareAppMessage() {
    const { getSharePathByScope } = require('../../utils/share')
    return {
      title: '神仙团队性格测试 - 发现你的内在潜能',
      path: getSharePathByScope('/pages/index/index')
    }
  },

  /** 分享到朋友圈 */
  onShareTimeline() {
    const { buildShareQuery } = require('../../utils/share')
    return {
      title: '神仙团队性格测试 - 发现你的内在潜能',
      query: buildShareQuery()
    }
  }
})
