// pages/user-profile/index.js - 个人资料页
const app = getApp()
const { request } = require('../../utils/request')
const { bindPhoneByCode } = require('../../utils/phoneAuth')

Page({
  data: {
    userInfo: null,
    nickname: '',
    avatar: '',
    birthday: '',
    gender: 0,
    genderIndex: 0,
    genderText: '',
    genderOptions: ['保密', '男', '女'],
    phone: '',
    avatarLetter: '我',
    avatarBgColor: '#6366f1',
    nicknameFocused: false,
    saving: false
  },

  onLoad() {
    this.loadUserInfo()
  },

  onShow() {
    // 从其他页返回时刷新
    if (app.globalData.userInfo) {
      this.loadUserInfo()
    }
  },

  loadUserInfo() {
    const userInfo = app.globalData.userInfo || tt.getStorageSync('userInfo')
    if (!userInfo) {
      tt.showToast({ title: '请先登录', icon: 'none' })
      setTimeout(() => tt.navigateBack(), 1500)
      return
    }

    const nickname = (userInfo.nickname || userInfo.nickName || '').trim()
    const avatar = userInfo.avatar || userInfo.avatarUrl || ''
    const birthday = userInfo.birthday || ''
    const gender = (userInfo.gender !== undefined && userInfo.gender !== null) ? Number(userInfo.gender) : 0
    const genderText = this._genderText(gender)
    const genderIndex = Math.min(Math.max(0, gender), 2)
    const phone = (userInfo.phone || userInfo.phoneNumber || '').trim()

    const { avatarLetter, avatarBgColor } = this._avatarFromNickname(nickname || '我')

    this.setData({
      userInfo,
      nickname,
      avatar,
      birthday,
      gender,
      genderIndex,
      genderText,
      phone,
      avatarLetter,
      avatarBgColor
    })
  },

  _genderText(g) {
    const map = { 0: '保密', 1: '男', 2: '女' }
    return map[g] || '保密'
  },

  _avatarFromNickname(name) {
    const str = (name && String(name).trim()) || '我'
    const letter = str.charAt(0).toUpperCase() || '我'
    const palette = ['#6366f1', '#8b5cf6', '#ec4899', '#f43f5e', '#14b8a6', '#0ea5e9', '#3b82f6', '#eab308']
    let hash = 0
    for (let i = 0; i < str.length; i++) hash += str.charCodeAt(i)
    const bgColor = palette[Math.abs(hash) % palette.length]
    return { avatarLetter: letter, avatarBgColor: bgColor }
  },

  onChooseAvatar(e) {
    const { avatarUrl } = e.detail
    if (!avatarUrl || !app.globalData.token) {
      tt.showToast({ title: '请先登录', icon: 'none' })
      return
    }
    tt.showLoading({ title: '上传中...' })
    const token = app.globalData.token
    const apiBase = app.globalData.apiBase
    tt.uploadFile({
      url: `${apiBase}/api/upload/image`,
      filePath: avatarUrl,
      name: 'file',
      header: { 'Authorization': `Bearer ${token}` },
      success: (res) => {
        const data = res.data
        let json = {}
        try { json = typeof data === 'string' ? JSON.parse(data) : data } catch (_) {}
        if (json.code === 200 && json.data && json.data.url) {
          const avatar = json.data.url
          this.setData({ avatar })
          this._saveProfile({ avatar })
        } else {
          tt.hideLoading()
          tt.showToast({ title: json.message || '上传失败', icon: 'none' })
        }
      },
      fail: () => {
        tt.hideLoading()
        tt.showToast({ title: '上传失败', icon: 'none' })
      }
    })
  },

  onGetPhoneNumber(e) {
    const { errMsg, code } = e.detail || {}
    if (errMsg && errMsg.indexOf('getPhoneNumber:fail') === 0) {
      tt.showToast({ title: '需要授权手机号才能获取', icon: 'none' })
      return
    }
    if (!code) {
      tt.showToast({ title: '获取手机号失败', icon: 'none' })
      return
    }
    bindPhoneByCode(code).then((user) => {
      const phone = ((user && (user.phone || user.phoneNumber)) || '').trim()
      this.setData({ phone })
    }).catch(() => {})
  },

  onNicknameRowTap() {
    this.setData({ nicknameFocused: true })
  },

  onNicknameBlur() {
    this.setData({ nicknameFocused: false })
  },

  onNicknameChange(e) {
    const nickname = (e?.detail?.value || '').trim()
    const { avatarLetter, avatarBgColor } = this._avatarFromNickname(nickname || '我')
    this.setData({ nickname, avatarLetter, avatarBgColor })
  },

  onBirthdayChange(e) {
    const birthday = (e?.detail?.value || '').trim()
    this.setData({ birthday })
  },

  onGenderChange(e) {
    const idx = parseInt(e?.detail?.value, 10) || 0
    const gender = idx
    const genderText = this.data.genderOptions[idx] || '保密'
    this.setData({ gender, genderIndex: idx, genderText })
  },

  /** 保存前校验：头像、昵称、手机号均必填 */
  _validateRequiredForSave() {
    const avatar = (this.data.avatar || '').trim()
    const nickname = (this.data.nickname || '').trim()
    const phone = (this.data.phone || this.data.userInfo?.phone || this.data.userInfo?.phoneNumber || '').trim()
    const missing = []
    if (!avatar) missing.push('头像')
    if (!nickname) missing.push('昵称')
    if (!phone) missing.push('手机号')
    if (missing.length === 0) return ''
    return missing.length === 1
      ? (missing[0] === '头像' ? '请先上传头像' : missing[0] === '昵称' ? '请填写昵称' : '请先授权绑定手机号')
      : `请完善：${missing.join('、')}`
  },

  onSave() {
    const tip = this._validateRequiredForSave()
    if (tip) {
      tt.showToast({ title: tip, icon: 'none' })
      return
    }

    const { nickname, birthday, gender, userInfo } = this.data
    const profile = {}
    const origNickname = (userInfo?.nickname || userInfo?.nickName || '').trim()
    const origBirthday = userInfo?.birthday || ''
    const origGender = (userInfo?.gender !== undefined && userInfo?.gender !== null) ? Number(userInfo.gender) : 0
    if (nickname !== origNickname) profile.nickname = nickname
    if (birthday !== origBirthday) profile.birthday = birthday
    if (gender !== origGender) profile.gender = gender
    if (Object.keys(profile).length === 0) {
      tt.showToast({ title: '暂无修改', icon: 'none' })
      return
    }
    this._saveProfile(profile)
  },

  _saveProfile(profile) {
    if (!app.globalData.token || !profile || Object.keys(profile).length === 0) return
    if (this.data.saving) return
    this.setData({ saving: true })
    tt.showLoading({ title: '保存中...' })
    request({
      url: '/api/auth/douyin/profile',
      method: 'PUT',
      data: profile,
      success: (res) => {
        tt.hideLoading()
        this.setData({ saving: false })
        const payload = res && res.data
        if (payload && payload.code === 200) {
          const updated = { ...(app.globalData.userInfo || {}), ...(payload.data || {}), ...profile }
          if (profile.nickname !== undefined) updated.nickname = profile.nickname
          if (profile.avatar !== undefined) updated.avatar = profile.avatar
          if (profile.birthday !== undefined) updated.birthday = profile.birthday
          if (profile.gender !== undefined) updated.gender = profile.gender
          app.globalData.userInfo = updated
          tt.setStorageSync('userInfo', updated)
          tt.showToast({ title: '已保存', icon: 'success' })
        } else {
          tt.showToast({ title: payload?.message || '保存失败', icon: 'none' })
        }
      },
      fail: () => {
        tt.hideLoading()
        this.setData({ saving: false })
        tt.showToast({ title: '网络错误', icon: 'none' })
      }
    })
  }
})
