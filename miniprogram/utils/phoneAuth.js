/**
 * 手机号授权工具：基于微信 getPhoneNumber + 服务器端换取手机号接口
 * 个人资料完整性检查：头像、昵称、手机号为必填，生日和性别选填
 */

/**
 * 个人资料是否已完善（头像、昵称、手机号必填，生日和性别选填）
 * @returns {boolean}
 */
function isProfileComplete() {
  const app = getApp()
  const user = app.globalData.userInfo || wx.getStorageSync('userInfo')
  if (!user) return false
  const avatar = (user.avatar || user.avatarUrl || '').trim()
  const nickname = (user.nickname || user.nickName || '').trim()
  const phone = (user.phone || user.phoneNumber || '').trim()
  return avatar.length > 0 && nickname.length > 0 && phone.length > 0
}

/**
 * 若资料未完善则跳转到个人资料页，需登录
 * @returns {boolean} true=已完善可继续，false=已跳转
 */
function ensureProfileCompleteAndRedirect() {
  const app = getApp()
  const token = app.globalData.token || wx.getStorageSync('token')
  if (!token) return true
  if (isProfileComplete()) return true
  wx.showToast({ title: '请先完善个人资料', icon: 'none' })
  wx.navigateTo({ url: '/pages/user-profile/index' })
  return false
}

/**
 * 当前用户是否已有手机号（从 globalData.userInfo 或 storage 读取）
 * @returns {boolean}
 */
function hasPhone() {
  const app = getApp()
  const user = app.globalData.userInfo || wx.getStorageSync('userInfo')
  const phone = (user && (user.phone || user.phoneNumber)) ? String(user.phone || user.phoneNumber).trim() : ''
  return phone.length > 0
}

/**
 * 使用 getPhoneNumber 回调里的 code 调用后端接口换取手机号，并写回 userInfo
 * @param {string} code
 * @returns {Promise<object>} resolve 为更新后的 userInfo
 */
function bindPhoneByCode(code) {
  return new Promise((resolve, reject) => {
    if (!code) {
      wx.showToast({ title: '获取手机号失败', icon: 'none' })
      reject(new Error('empty code'))
      return
    }

    const app = getApp()
    const token = app.globalData.token || wx.getStorageSync('token')
    if (!token) {
      wx.showToast({ title: '请先登录', icon: 'none' })
      reject(new Error('no token'))
      return
    }

    const apiBase = app.globalData.apiBase || ''
    if (!apiBase) {
      wx.showToast({ title: '服务未配置', icon: 'none' })
      reject(new Error('no api base'))
      return
    }

    wx.showLoading({ title: '处理中...', mask: true })
    wx.request({
      url: `${apiBase.replace(/\/$/, '')}/api/auth/wechat/phone`,
      method: 'POST',
      header: {
        'Authorization': 'Bearer ' + token,
        'Content-Type': 'application/json',
      },
      data: { code },
      success: (res) => {
        wx.hideLoading()
        if (res.statusCode === 200 && res.data && res.data.code === 200) {
          const data = res.data.data || {}
          const user = data.user || app.globalData.userInfo || {}
          const phone = data.phone || user.phone || ''
          const newUser = { ...user, phone }
          app.globalData.userInfo = newUser
          wx.setStorageSync('userInfo', newUser)
          wx.showToast({ title: '授权成功', icon: 'success' })
          resolve(newUser)
        } else {
          const msg = res.data && res.data.message ? res.data.message : '获取手机号失败'
          wx.showToast({ title: msg, icon: 'none' })
          reject(new Error(msg))
        }
      },
      fail: () => {
        wx.hideLoading()
        wx.showToast({ title: '网络请求失败', icon: 'none' })
        reject(new Error('network error'))
      },
    })
  })
}

module.exports = {
  hasPhone,
  bindPhoneByCode,
  isProfileComplete,
  ensureProfileCompleteAndRedirect,
}
