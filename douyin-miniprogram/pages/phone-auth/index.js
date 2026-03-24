// 手机号授权页：用户点击按钮授权后，用 code 换手机号并写回 userInfo，再返回或跳转 next
const app = getApp()

Page({
  data: {
    next: '',
  },

  onLoad(options) {
    this.setData({
      next: options.next ? decodeURIComponent(options.next) : '',
    })
  },

  onGetPhoneNumber(e) {
    const { code, errMsg } = e.detail || {}
    if (errMsg && errMsg.indexOf('getPhoneNumber:fail') === 0) {
      tt.showToast({ title: '需要授权手机号才能继续', icon: 'none' })
      return
    }
    if (!code) {
      tt.showToast({ title: '获取手机号失败', icon: 'none' })
      return
    }
    const token = app.globalData.token || tt.getStorageSync('token')
    if (!token) {
      tt.showToast({ title: '请先登录', icon: 'none' })
      return
    }
    tt.showLoading({ title: '处理中...', mask: true })
    tt.request({
      url: `${app.globalData.apiBase.replace(/\/$/, '')}/api/auth/douyin/phone`,
      method: 'POST',
      header: {
        'Authorization': 'Bearer ' + token,
        'Content-Type': 'application/json',
      },
      data: { code },
      success: (res) => {
        tt.hideLoading()
        if (res.statusCode === 200 && res.data && res.data.code === 200) {
          const data = res.data.data || {}
          const user = data.user || app.globalData.userInfo || {}
          const phone = data.phone || user.phone || ''
          const newUser = { ...user, phone }
          app.globalData.userInfo = newUser
          tt.setStorageSync('userInfo', newUser)
          tt.showToast({ title: '授权成功', icon: 'success' })
          const nextPath = this.data.next && this.data.next.startsWith('/') ? this.data.next : ''
          const tabBarPaths = ['/pages/index/index', '/pages/index/camera', '/pages/profile/index']
          const isTabBar = tabBarPaths.some(p => nextPath === p || nextPath.startsWith(p + '?'))
          if (nextPath) {
            setTimeout(() => {
              if (isTabBar) {
                const pathOnly = nextPath.split('?')[0]
                tt.switchTab({ url: pathOnly, fail: () => tt.navigateBack() })
              } else {
                tt.redirectTo({ url: nextPath, fail: () => tt.navigateBack() })
              }
            }, 500)
          } else {
            setTimeout(() => tt.navigateBack(), 500)
          }
        } else {
          tt.showToast({ title: res.data && res.data.message ? res.data.message : '获取手机号失败', icon: 'none' })
        }
      },
      fail: () => {
        tt.hideLoading()
        tt.showToast({ title: '网络请求失败', icon: 'none' })
      },
    })
  },
})
