// 自定义 tabBar（不使用微信内置）：灰线 + 三栏，中间为浮起圆钮
Component({
  data: {
    selected: 0,
    /** 审核模式：为 true 时隐藏中间「拍摄」入口（与 globalData.reviewMode / maintenanceMode 一致） */
    reviewMode: false,
    list: [
      { pagePath: '/pages/index/index', text: '首页', textKey: 'home', icon: 'home' },
      { pagePath: '/pages/index/camera', text: '拍摄', textKey: 'camera', icon: 'camera' },
      { pagePath: '/pages/profile/index', text: '我的', textKey: 'profile', icon: 'user' }
    ]
  },
  lifetimes: {
    attached() {
      this.updateSelected()
    }
  },
  pageLifetimes: {
    show() {
      this.updateSelected()
    }
  },
  methods: {
    updateSelected() {
      try {
        const pages = getCurrentPages()
        if (!pages || pages.length === 0) return
        const currentPage = pages[pages.length - 1]
        if (!currentPage || !currentPage.route) return
        const url = currentPage.route
        const gd = getApp().globalData || {}
        const reviewMode = !!(gd.reviewMode || gd.maintenanceMode)
        let selected = 0
        if (url === 'pages/index/index' || url === 'pages/enterprise/index') {
          selected = 0
        } else if (url === 'pages/index/camera') {
          selected = 1
        } else if (url === 'pages/profile/index') {
          selected = 2
        }
        this.setData({ selected, reviewMode })
      } catch (error) {
        console.error('updateSelected error:', error)
        this.setData({ selected: 0, reviewMode: false })
      }
    },
    switchTab(e) {
      const index = parseInt(e.currentTarget.dataset.index, 10)
      let url = e.currentTarget.dataset.path

      const gd = getApp().globalData || {}
      if (index === 1 && !!(gd.reviewMode || gd.maintenanceMode)) {
        return
      }

      if (index === 0) {
        try {
          const app = getApp()
          const scope = (app && app.globalData && app.globalData.appScope) || 'personal'
          if (scope === 'enterprise') {
            wx.navigateTo({ url: '/pages/enterprise/index' })
            this.setData({ selected: index })
            return
          }
        } catch (e) {}
      }

      wx.switchTab({ url })
      this.setData({ selected: index })
    }
  }
})
