// 自定义 tabBar（不使用微信内置）：灰线 + 三栏，中间为浮起圆钮
Component({
  data: {
    selected: 0,
    reviewMode: false,
    list: [
      { pagePath: '/pages/index/index', text: '首页', textKey: 'home', icon: 'home' },
      { pagePath: '/pages/index/camera', text: '查看报告', textKey: 'camera', icon: 'camera' },
      { pagePath: '/pages/profile/index', text: '我的', textKey: 'profile', icon: 'user' }
    ]
  },
  lifetimes: {
    attached() {
      this.updateSelected()
      this.checkReviewMode()
    }
  },
  pageLifetimes: {
    show() {
      this.updateSelected()
      this.checkReviewMode()
    }
  },
  methods: {
    checkReviewMode() {
      try {
        const app = getApp()
        const rm = !!(app && app.globalData && app.globalData.reviewMode)
        if (this.data.reviewMode !== rm) {
          this.setData({ reviewMode: rm })
        }
        // 首次加载时 getRuntimeConfig 可能尚未返回，延迟再检查一次
        if (!rm && !this._retried) {
          this._retried = true
          setTimeout(() => {
            try {
              const rmLater = !!(getApp() && getApp().globalData && getApp().globalData.reviewMode)
              if (rmLater && !this.data.reviewMode) {
                this.setData({ reviewMode: true })
              }
            } catch (e) {}
          }, 1500)
        }
      } catch (e) {}
    },
    updateSelected() {
      try {
        const pages = getCurrentPages()
        if (!pages || pages.length === 0) return
        const currentPage = pages[pages.length - 1]
        if (!currentPage || !currentPage.route) return
        const url = currentPage.route
        let selected = 0
        if (url === 'pages/index/index' || url === 'pages/enterprise/index') {
          selected = 0
        } else if (url === 'pages/index/camera') {
          selected = 1
        } else if (url === 'pages/profile/index') {
          selected = 2
        }
        this.setData({ selected })
      } catch (error) {
        console.error('updateSelected error:', error)
        this.setData({ selected: 0 })
      }
    },
    switchTab(e) {
      const index = parseInt(e.currentTarget.dataset.index, 10)
      let url = e.currentTarget.dataset.path

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

      // 审核模式：中间按钮跳转到测试选择页而非相机
      if (index === 1 && this.data.reviewMode) {
        wx.navigateTo({ url: '/pages/test-select/index' })
        this.setData({ selected: index })
        return
      }

      wx.switchTab({ url })
      this.setData({ selected: index })
    }
  }
})
