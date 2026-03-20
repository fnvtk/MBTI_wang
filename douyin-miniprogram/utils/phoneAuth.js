/**
 * 手机号授权工具（抖音版）
 * 抖音小程序同样支持 <button open-type="getPhoneNumber"> 获取手机号
 * 回调中拿到 encryptedData + iv（或 code），传给后端解密
 */

function isProfileComplete() {
  const app = getApp()
  const user = app.globalData.userInfo || tt.getStorageSync('userInfo')
  if (!user) return false
  const avatar = (user.avatar || user.avatarUrl || '').trim()
  const nickname = (user.nickname || user.nickName || '').trim()
  const phone = (user.phone || user.phoneNumber || '').trim()
  return avatar.length > 0 && nickname.length > 0 && phone.length > 0
}

function ensureProfileCompleteAndRedirect() {
  const app = getApp()
  const token = app.globalData.token || tt.getStorageSync('token')
  if (!token) return true
  if (isProfileComplete()) return true
  tt.showToast({ title: '请先完善个人资料', icon: 'none' })
  tt.navigateTo({ url: '/pages/user-profile/index' })
  return false
}

function hasPhone() {
  const app = getApp()
  const user = app.globalData.userInfo || tt.getStorageSync('userInfo')
  const phone = (user && (user.phone || user.phoneNumber)) ? String(user.phone || user.phoneNumber).trim() : ''
  return phone.length > 0
}

/**
 * 使用 getPhoneNumber 回调的数据调用后端换取手机号
 * 抖音版：回调返回 encryptedData + iv，或 code
 */
function bindPhoneByCode(code) {
  return new Promise((resolve, reject) => {
    if (!code) {
      tt.showToast({ title: '获取手机号失败', icon: 'none' })
      reject(new Error('empty code'))
      return
    }

    const app = getApp()
    const token = app.globalData.token || tt.getStorageSync('token')
    if (!token) {
      tt.showToast({ title: '请先登录', icon: 'none' })
      reject(new Error('no token'))
      return
    }

    const apiBase = app.globalData.apiBase || ''
    if (!apiBase) {
      tt.showToast({ title: '服务未配置', icon: 'none' })
      reject(new Error('no api base'))
      return
    }

    tt.showLoading({ title: '处理中...', mask: true })
    tt.request({
      url: `${apiBase.replace(/\/$/, '')}/api/auth/douyin/phone`,
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
          resolve(newUser)
        } else {
          const msg = res.data && res.data.message ? res.data.message : '获取手机号失败'
          tt.showToast({ title: msg, icon: 'none' })
          reject(new Error(msg))
        }
      },
      fail: () => {
        tt.hideLoading()
        tt.showToast({ title: '网络请求失败', icon: 'none' })
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
