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
    // 须与 app.globalData.reviewMode 一致；误设为 true 会导致 wxml 整页 wx:if 不渲染而白屏
    reviewMode: false
  },

  onLoad() {
    const rm = !!(app.globalData && app.globalData.reviewMode)
    this.setData({ reviewMode: rm })
    this.cameraContext = wx.createCameraContext()
    const tc = app.globalData.textConfig
    if (tc && tc.aiAnalysisText) {
      this.setData({ aiAnalysisText: tc.aiAnalysisText })
    } else {
      app.getRuntimeConfig().then((cfg) => {
        if (cfg && cfg.textConfig) {
          app.globalData.textConfig = cfg.textConfig
          this.setData({ aiAnalysisText: cfg.textConfig.aiAnalysisText || '分析' })
        }
      }).catch(() => {})
    }
  },

  onShow() {
    const rm = !!(app.globalData && app.globalData.reviewMode)
    this.setData({ reviewMode: rm })
    // 审核模式下重定向到测试选择页
    if (rm) {
      wx.navigateTo({ url: '/pages/test-select/index' })
      return
    }
    if (!ensureProfileCompleteAndRedirect()) return
    if (typeof this.getTabBar === 'function' && this.getTabBar()) {
      this.getTabBar().setData({ selected: 1, reviewMode: !!app.globalData.reviewMode })
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

    this.cameraContext.takePhoto({
      quality: 'high',
      success: (res) => {
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
        wx.showToast({ title: '拍照失败', icon: 'none' })
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
        }
      }
    })
  },

  // 完成拍照：先上传 3 张图到服务器，拿到 URL 后再跳转结果页
  completeCapture() {
    if (!ensureProfileCompleteAndRedirect()) return
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
        wx.navigateTo({ url: '/pages/index/result' })
      })
      .catch((err) => {
        wx.hideLoading()
        this.setData({ uploading: false })
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
