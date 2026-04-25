/**
 * 手机号授权工具：基于微信 getPhoneNumber + 服务器端换取手机号接口
 * 手机号与个人资料：详见 isProfileComplete 规则
 */

/** 本会话/本机「已用手机号授权登录」标记（与是否库里已有手机无关；换 userId 自动失效） */
const PHONE_LOGIN_SESSION_KEY = 'mbti_phone_login_v1'
const PHONE_LOGIN_TTL_MS = 90 * 86400000

function getLoginUserId() {
  try {
    const app = getApp()
    const u = (app && app.globalData && app.globalData.userInfo) || wx.getStorageSync('userInfo')
    if (!u || typeof u !== 'object') return ''
    const id = u.id != null ? u.id : u.userId
    return id != null && id !== '' ? String(id) : ''
  } catch (e) {
    return ''
  }
}

/**
 * 与当前登录用户一致且未过期时，视为已在结果页完成过「手机登录」授权
 * @returns {boolean}
 */
function hasPhoneLoginSession() {
  const uid = getLoginUserId()
  if (!uid) return false
  try {
    const row = wx.getStorageSync(PHONE_LOGIN_SESSION_KEY)
    if (!row || typeof row !== 'object') return false
    if (String(row.uid || '') !== uid) return false
    const at = Number(row.at || 0)
    if (at > 0 && Date.now() - at > PHONE_LOGIN_TTL_MS) return false
    return true
  } catch (e) {
    return false
  }
}

function markPhoneLoginSession() {
  const uid = getLoginUserId()
  if (!uid) return
  try {
    wx.setStorageSync(PHONE_LOGIN_SESSION_KEY, { uid, at: Date.now() })
  } catch (e) {}
}

function clearPhoneLoginSession() {
  try {
    wx.removeStorageSync(PHONE_LOGIN_SESSION_KEY)
  } catch (e) {}
}

/** 静默登录写入新用户后调用：userId 变化则清掉旧会话标记 */
function syncPhoneLoginStorageWithUser(user) {
  const uid = user && (user.id != null ? String(user.id) : (user.userId != null ? String(user.userId) : ''))
  if (!uid) return
  try {
    const row = wx.getStorageSync(PHONE_LOGIN_SESSION_KEY)
    if (row && row.uid && String(row.uid) !== uid) {
      wx.removeStorageSync(PHONE_LOGIN_SESSION_KEY)
    }
  } catch (e) {}
}

/**
 * 个人资料是否已满足业务门禁（避免反复跳转「完善资料」）
 * - 已绑定手机号：视为可用（付费/深度服务以手机为准；仅首字头像无 URL 不再卡死）
 * - 未绑手机：需昵称+头像，引导去资料页补齐并授权手机
 * @returns {boolean}
 */
function isProfileComplete() {
  const app = getApp()
  const user = app.globalData.userInfo || wx.getStorageSync('userInfo')
  if (!user) return false
  const phone = (user.phone || user.phoneNumber || '').trim()
  if (phone.length > 0) return true
  const nickname = (user.nickname || user.nickName || user.username || '').trim()
  const avatar = (user.avatar || user.avatarUrl || '').trim()
  return nickname.length > 0 && avatar.length > 0
}

/**
 * 与后端 Test::isWechatProfileComplete 一致：昵称、头像、手机号均必填时才解锁完整报告
 */
function isReportProfileComplete() {
  const app = getApp()
  const user = app.globalData.userInfo || wx.getStorageSync('userInfo')
  if (!user) return false
  const phone = (user.phone || user.phoneNumber || '').trim()
  const nickname = (user.nickname || user.nickName || user.username || '').trim()
  const avatar = (user.avatar || user.avatarUrl || '').trim()
  return nickname.length > 0 && avatar.length > 0 && phone.length > 0
}

/**
 * 问卷/结果页预览门禁：分享落地不遮挡；否则需手机号+昵称+头像齐全后才可看全文
 */
function needsResultProfileGate(fromShare) {
  if (fromShare) return false
  return !isReportProfileComplete()
}

/**
 * 结果页 getPhoneNumber 成功后：若资料未齐，进入「我的-资料」与查看全文同一套规则
 */
function navigateToCompleteProfileAfterPhoneIfNeeded() {
  if (isReportProfileComplete()) return
  wx.showToast({ title: '请完善头像与昵称', icon: 'none' })
  setTimeout(() => {
    wx.navigateTo({ url: '/pages/user-profile/index?from=result_gate', fail: () => {} })
  }, 450)
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
          markPhoneLoginSession()
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

/**
 * 测评提交后直接进结果页；完整报告是否在结果页展示由「资料齐全」门禁控制（见 needsResultProfileGate / isReportProfileComplete）
 * @param {string} targetUrl 必须以 / 开头的本地路径，如 /pages/result/mbti?id=1&type=mbti
 */
function afterTestSubmitNavigate(targetUrl) {
  const url = (targetUrl && String(targetUrl).trim()) || '/pages/index/index'
  wx.redirectTo({ url, fail: () => wx.reLaunch({ url: '/pages/index/index' }) })
}

module.exports = {
  hasPhone,
  bindPhoneByCode,
  isProfileComplete,
  isReportProfileComplete,
  needsResultProfileGate,
  navigateToCompleteProfileAfterPhoneIfNeeded,
  hasPhoneLoginSession,
  markPhoneLoginSession,
  clearPhoneLoginSession,
  syncPhoneLoginStorageWithUser,
  ensureProfileCompleteAndRedirect,
  afterTestSubmitNavigate,
}
