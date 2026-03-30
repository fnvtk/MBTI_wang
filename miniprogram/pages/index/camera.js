// pages/index/camera.js - 拍照页，拍完后上传到服务器再跳转结果页
const app = getApp()
const { hasPhone, bindPhoneByCode, ensureProfileCompleteAndRedirect } = require('../../utils/phoneAuth.js')

Page({
  data: {
    photos: [],
    photoIndex: 0,
    guideTexts: ['请正对镜头', '请向左转45°', '请向右转45°'],
    guideText: '请正对镜头',
    uploading: false,
    needPhoneAuth: false,
    aiAnalysisText: '分析',
    reviewMode: true
  },

  _audit() {
    return !!(app.globalData.reviewMode || app.globalData.maintenanceMode)
  },

  onLoad() {
    this.setData({ reviewMode: this._audit() })
    const tc = app.globalData.textConfig
    if (tc && tc.aiAnalysisText) {
      this.setData({ aiAnalysisText: tc.aiAnalysisText })
    }
    app.getRuntimeConfig().then((cfg) => {
      if (cfg) {
        if (cfg.reviewMode !== undefined) app.globalData.reviewMode = !!cfg.reviewMode
        else if (cfg.maintenanceMode !== undefined) app.globalData.reviewMode = !!cfg.maintenanceMode
        if (cfg.maintenanceMode !== undefined) app.globalData.maintenanceMode = !!cfg.maintenanceMode
        if (cfg.textConfig) {
          app.globalData.textConfig = cfg.textConfig
          this.setData({
            aiAnalysisText: cfg.textConfig.aiAnalysisText || '分析',
            reviewMode: this._audit()
          })
          try {
            const tb = typeof this.getTabBar === 'function' ? this.getTabBar() : null
            if (tb && typeof tb.updateSelected === 'function') tb.updateSelected()
          } catch (e) {}
          return
        }
      }
      this.setData({ reviewMode: this._audit() })
    }).catch(() => {
      this.setData({ reviewMode: this._audit() })
    })
  },

  /** 非审核模式且相机在页上时再创建上下文 */
  onReady() {
    if (!this._audit()) {
      this.initCameraContext()
    }
  },

  goToQuestionnaire() {
    wx.navigateTo({ url: '/pages/test-select/index' })
  },

  initCameraContext() {
    try {
      if (typeof wx.createCameraContext === 'function') {
        this.cameraContext = wx.createCameraContext()
      }
    } catch (e) {
      console.error('initCameraContext', e)
      this.cameraContext = null
    }
  },

  onShow() {
    const rm = this._audit()
    const faceOff = !!(app.globalData.enterprisePermissions && app.globalData.enterprisePermissions.face === false)
    this.setData({ reviewMode: rm })

    if (typeof this.getTabBar === 'function' && this.getTabBar()) {
      const tb = this.getTabBar()
      if (typeof tb.updateSelected === 'function') tb.updateSelected()
    }

    // 审核模式：只展示本页引导，不再强跳 navigateTo（失败时曾导致白屏且无拍摄区）
    if (rm) {
      return
    }
    if (faceOff) {
      wx.navigateTo({ url: '/pages/test-select/index' })
      return
    }

    if (!ensureProfileCompleteAndRedirect()) {
      return
    }

    if (this.data.photos.length < 3) {
      this.initCameraContext()
    }

    this.setData({ needPhoneAuth: !hasPhone() })
    const tc = app.globalData.textConfig
    if (tc && tc.aiAnalysisText) {
      this.setData({ aiAnalysisText: tc.aiAnalysisText })
    }
  },

  // 本页就地授权手机号
  onGetPhoneNumber(e) {
    const { code, errMsg } = e.detail || {}
    if (errMsg && errMsg.indexOf('getPhoneNumber:fail') === 0) {
      wx.showToast({ title: '需要授权手机号才能继续', icon: 'none' })
      return
    }
    if (!code && !hasPhone()) {
      wx.showToast({ title: '获取手机号失败', icon: 'none' })
      return
    }
    if (!code && hasPhone()) {
      // 已有手机号，无需重复请求
      this.setData({ needPhoneAuth: false })
      return
    }
    bindPhoneByCode(code).then(() => {
      this.setData({ needPhoneAuth: false })
    }).catch(() => {
      // 失败时保持 needPhoneAuth 为 true，等待用户重新授权
      this.setData({ needPhoneAuth: !hasPhone() })
    })
  },

  // 拍照
  takePhoto() {
    if (this.data.photos.length >= 3) {
      wx.showToast({ title: '已拍满3张', icon: 'none' })
      return
    }

    if (!this.cameraContext) {
      this.initCameraContext()
    }
    if (!this.cameraContext || typeof this.cameraContext.takePhoto !== 'function') {
      wx.showToast({ title: '相机未就绪，请稍候再试', icon: 'none' })
      return
    }

    this.cameraContext.takePhoto({
      quality: 'high',
      success: (res) => {
        try { require('../../utils/analytics').track('take_photo', { index: this.data.photos.length + 1 }) } catch (e) {}
        const photos = [...this.data.photos, res.tempImagePath]
        const photoIndex = photos.length
        const guideText = this.data.guideTexts[photoIndex] || '拍摄完成'
        
        this.setData({
          photos,
          photoIndex,
          guideText
        })

        if (photos.length === 3) {
          wx.showToast({ title: '拍摄完成', icon: 'success' })
        }
      },
      fail: (err) => {
        const msg = (err && (err.errMsg || err.message)) ? String(err.errMsg || err.message) : ''
        if (msg.indexOf('auth deny') >= 0 || msg.indexOf('authorize') >= 0) {
          wx.showModal({
            title: '需要相机权限',
            content: '请在设置中允许使用摄像头',
            confirmText: '去设置',
            success: (r) => { if (r.confirm) wx.openSetting() }
          })
        } else {
          wx.showToast({ title: '拍照失败，请重试', icon: 'none' })
        }
        console.error('拍照失败:', err)
      }
    })
  },

  // 重新拍摄全部照片
  retakeAll() {
    wx.showModal({
      title: '重新拍摄',
      content: '确定要重新拍摄所有照片吗？',
      confirmText: '确定',
      cancelText: '取消',
      success: (res) => {
        if (res.confirm) {
          this.setData({
            photos: [],
            photoIndex: 0,
            guideText: '请正对镜头'
          })
          wx.showToast({ title: '已清空，请重新拍摄', icon: 'success' })
          setTimeout(() => this.initCameraContext(), 200)
        }
      }
    })
  },

  // 完成拍照：先上传 3 张图到服务器，拿到 URL 后再跳转结果页
  completeCapture() {
    if (!hasPhone()) {
      wx.showToast({ title: '请先授权手机号', icon: 'none' })
      this.setData({ needPhoneAuth: true })
      return
    }
    const photos = this.data.photos
    if (!photos || photos.length === 0) {
      wx.showToast({ title: '请先拍摄照片', icon: 'none' })
      return
    }
    this.setData({ uploading: true })
    wx.showLoading({ title: '上传中...', mask: true })
    const apiBase = (app.globalData && app.globalData.apiBase) ? app.globalData.apiBase.replace(/\/$/, '') : ''
    const token = (app.globalData && app.globalData.token) || wx.getStorageSync('token') || ''
    const uploadUrl = apiBase + '/api/upload/image'

    const uploadOne = (path) => {
      return new Promise((resolve, reject) => {
        wx.uploadFile({
          url: uploadUrl,
          filePath: path,
          name: 'file',
          header: token ? { Authorization: 'Bearer ' + token } : {},
          success: (res) => {
            try {
              const data = JSON.parse(res.data)
              if (data.code === 200 && data.data && data.data.url) {
                resolve(data.data.url)
              } else {
                reject(new Error(data.message || '上传失败'))
              }
            } catch (e) {
              reject(new Error('解析上传结果失败'))
            }
          },
          fail: (err) => reject(err)
        })
      })
    }

    Promise.all(photos.map(uploadOne))
      .then((urls) => {
        wx.hideLoading()
        wx.setStorageSync('aiPhotos', urls)
        this.setData({ uploading: false })
        try { require('../../utils/analytics').track('photo_upload_success', { count: urls.length }) } catch (e) {}
        wx.navigateTo({ url: '/pages/index/result' })
      })
      .catch((err) => {
        wx.hideLoading()
        this.setData({ uploading: false })
        try { require('../../utils/analytics').track('photo_upload_fail', { error: (err && err.message) || '' }) } catch (e) {}
        wx.showToast({ title: err.message || '上传失败', icon: 'none' })
      })
  },

  // 从相册选择：跳转到上传页（三角度上传），不改变当前 scope/企业上下文
  goToUpload() {
    wx.navigateTo({
      url: '/pages/index/upload'
    })
  },

  // 相机错误
  onCameraError(e) {
    console.error('相机错误:', e)
    wx.showModal({
      title: '相机权限',
      content: '请允许使用相机权限以进行性格分析',
      confirmText: '去设置',
      success: (res) => {
        if (res.confirm) {
          wx.openSetting()
        }
      }
    })
  }
})
