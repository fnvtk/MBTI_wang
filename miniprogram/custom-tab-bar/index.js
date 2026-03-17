// 自定义 tabBar（不使用微信内置）：灰线 + 三栏，中间为浮起圆钮
Component({
  data: {
    selected: 0,
    list: [
      { pagePath: '/pages/index/index', text: '首页', textKey: 'home', icon: 'home' },
      { pagePath: '/pages/index/camera', text: '查看报告', textKey: 'camera', icon: 'camera' },
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
        if (!pages || pages.length === 0) {
          return
        }
        
        const currentPage = pages[pages.length - 1]
        if (!currentPage || !currentPage.route) {
          return
        }
        
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
        // 默认选中第一个
        this.setData({ selected: 0 })
      }
    },
    switchTab(e) {
      const index = parseInt(e.currentTarget.dataset.index, 10)
      let url = e.currentTarget.dataset.path

      // 点击"首页"（index=0）时，根据当前 scope 跳到企业版或个人版首页
      if (index === 0) {
        try {
          const app = getApp()
          const scope = (app && app.globalData && app.globalData.appScope) || 'personal'
          if (scope === 'enterprise') {
            // 企业版：navigateTo（不是 tabBar 页面，不能用 switchTab）
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
