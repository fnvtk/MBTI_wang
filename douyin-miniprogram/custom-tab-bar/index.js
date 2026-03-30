// 自定义 tabBar（不使用微信内置）：灰线 + 三栏，中间为浮起圆钮
Component({
  data: {
    selected: 0,
    reviewMode: false,
    hideMiddleFab: false,
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
        const ep = gd.enterprisePermissions
        const hideMiddleFab = reviewMode || !!(ep && ep.face === false)
        let selected = 0
        if (url === 'pages/index/index' || url === 'pages/enterprise/index') {
          selected = 0
        } else if (url === 'pages/index/camera') {
          selected = hideMiddleFab ? 0 : 1
        } else if (url === 'pages/profile/index') {
          selected = 2
        }
        this.setData({ selected, reviewMode, hideMiddleFab })
      } catch (error) {
        console.error('updateSelected error:', error)
        this.setData({ selected: 0, reviewMode: false, hideMiddleFab: false })
      }
    },
    switchTab(e) {
      const index = parseInt(e.currentTarget.dataset.index, 10)
      let url = e.currentTarget.dataset.path

      const gd = getApp().globalData || {}
      const faceOff = !!(gd.enterprisePermissions && gd.enterprisePermissions.face === false)
      if (index === 1 && (!!(gd.reviewMode || gd.maintenanceMode) || faceOff)) {
        return
      }

      if (index === 0) {
        try {
          const app = getApp()
          const scope = (app && app.globalData && app.globalData.appScope) || 'personal'
          if (scope === 'enterprise') {
            tt.navigateTo({ url: '/pages/enterprise/index' })
            this.setData({ selected: index })
            return
          }
        } catch (e) {}
      }

      tt.switchTab({ url })
      this.setData({ selected: index })
    }
  }
})
