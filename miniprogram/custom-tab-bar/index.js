// 自定义 TabBar：完全由后台配置驱动
// 数据来源：app.globalData.tabBar.items（GET /api/mp/tabbar）
// 兜底：首页 · 拍摄(第2项凸起) · 神仙AI · 我

const { isAuditHideAiMode, shouldHideTabBarHighlightFab } = require('../utils/miniprogramAuditGate.js')

const DEFAULT_ITEMS = [
  { pagePath: 'pages/index/index',   text: '首页',   iconKey: 'home',    iconUrl: '', highlight: false },
  { pagePath: 'pages/index/camera',  text: '拍摄',   iconKey: 'camera',  iconUrl: '', highlight: true  },
  { pagePath: 'pages/ai-chat/index', text: '神仙AI', iconKey: 'ai', iconUrl: '', iconUrlActive: '', highlight: false },
  { pagePath: 'pages/profile/index', text: '我',     iconKey: 'profile', iconUrl: '', highlight: false }
]

Component({
  data: {
    selected: 0,
    reviewMode: false,
    hideMiddleFab: false,
    items: [],
  },

  lifetimes: {
    attached() {
      this.refreshFromConfig()
    }
  },

  pageLifetimes: {
    show() {
      this.refreshFromConfig()
    }
  },

  methods: {
    refreshFromConfig() {
      try {
        const app = getApp() || {}
        const gd = app.globalData || {}
        const cfg = gd.tabBar && Array.isArray(gd.tabBar.items) ? gd.tabBar.items : null
        const rawItems = (cfg && cfg.length >= 2) ? cfg.slice() : DEFAULT_ITEMS.slice()
        const items = rawItems.map((it) => {
          if (it.iconKey === 'ai') {
            const u = (it.iconUrl && String(it.iconUrl).trim()) || ''
            const raster = /\.(png|jpg|jpeg|webp)$/i.test(u)
            if (raster) {
              return {
                ...it,
                iconUrl: u,
                iconUrlActive: (it.iconUrlActive && String(it.iconUrlActive).trim()) || '/images/tab-ai-active.png'
              }
            }
            return { ...it, iconUrl: '', iconUrlActive: '' }
          }
          return { ...it, iconUrl: it.iconUrl || '' }
        })

        const reviewMode = !!(gd.reviewMode || gd.maintenanceMode)
        const hideAiTab = isAuditHideAiMode(gd)
        const hideMiddleFab = shouldHideTabBarHighlightFab(gd)

        // 提审或面相审核：去掉神仙 AI Tab（与 miniprogramAuditGate 一致）
        // 审核模式 / face 关闭时：隐藏所有 highlight=true 的 Tab
        const effectiveItems = items.filter((it) => {
          const path = (it.pagePath || '').replace(/^\//, '')
          if (hideAiTab && (it.iconKey === 'ai' || /ai-chat/.test(path))) return false
          if (hideMiddleFab && it.highlight) return false
          return true
        })

        this.setData({
          items: effectiveItems,
          reviewMode,
          hideMiddleFab,
        }, () => this.updateSelected())
      } catch (e) {
        this.setData({ items: DEFAULT_ITEMS, reviewMode: false, hideMiddleFab: false }, () => this.updateSelected())
      }
    },

    updateSelected() {
      try {
        const pages = getCurrentPages()
        if (!pages || pages.length === 0) return
        const currentPage = pages[pages.length - 1]
        if (!currentPage || !currentPage.route) return
        const url = currentPage.route
        const items = this.data.items || []

        let selected = 0
        for (let i = 0; i < items.length; i++) {
          const path = (items[i].pagePath || '').replace(/^\//, '')
          if (url === path) { selected = i; break }
          // 企业首页归一到"首页"Tab
          if (url === 'pages/enterprise/index' && path === 'pages/index/index') { selected = i; break }
          if (url === 'pages/index/camera' && items[i].highlight) { selected = i; break }
        }
        this.setData({ selected })
      } catch (err) {
        this.setData({ selected: 0 })
      }
    },

    switchTab(e) {
      const index = parseInt(e.currentTarget.dataset.index, 10)
      const it = (this.data.items || [])[index]
      if (!it) return

      // 中间浮钮在审核模式下被 refreshFromConfig 过滤掉了，不会到这里

      // 神仙 AI Tab 的轻量埋点
      if ((it.iconKey === 'ai' || /ai-chat/.test(it.pagePath))) {
        try {
          const analytics = require('../utils/analytics.js')
          if (analytics && typeof analytics.track === 'function') {
            analytics.track('tap_tab_ai_chat', {})
          }
        } catch (e) {}
      }

      // 首页点击：企业作用域走企业首页（保留旧行为）
      if (it.pagePath === 'pages/index/index' || it.iconKey === 'home') {
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

      const url = '/' + it.pagePath.replace(/^\//, '')
      wx.switchTab({
        url,
        fail: () => {
          // 非 tabBar 页面 fallback 到 navigateTo
          wx.navigateTo({ url })
        }
      })
      this.setData({ selected: index })
    }
  }
})
