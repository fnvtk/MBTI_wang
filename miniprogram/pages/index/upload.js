// pages/index/upload.js - 上传照片引导页
const app = getApp()
const { hasPhone, bindPhoneByCode, ensureProfileCompleteAndRedirect } = require('../../utils/phoneAuth.js')

Page({
  data: {
    sampleImages: [
      'https://karuosiyujzk.oss-cn-shenzhen.aliyuncs.com/mbti/20260312/img_69b22c5b77dd72.66364633.png',
      'https://karuosiyujzk.oss-cn-shenzhen.aliyuncs.com/mbti/20260312/img_69b22d71b312f8.14596541.png',
      'https://karuosiyujzk.oss-cn-shenzhen.aliyuncs.com/mbti/20260312/img_69b22d78d52b94.76297015.png'
    ],
    photos: ['', '', ''],
    uploadedUrls: [],
    photoIndex: 0,
    guideTexts: ['请正对镜头', '请向左转45°', '请向右转45°'],
    guideText: '请正对镜头',
    uploading: false,
    needPhoneAuth: false,
    aiAnalysisText: '分析'
  },

  onLoad() {
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
    // 审核模式下重定向到测试选择页
    if (app.globalData.reviewMode) {
      wx.navigateTo({ url: '/pages/test-select/index' })
      return
    }
    if (!ensureProfileCompleteAndRedirect()) return
    if (typeof this.getTabBar === 'function' && this.getTabBar()) {
      this.getTabBar().setData({ selected: 1 })
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

  // 选择/拍摄照片通用方法（index: 0 正面 / 1 左侧 / 2 右侧）
  choosePhoto(index) {
    wx.chooseImage({
      count: 1,
      sizeType: ['compressed'],
      sourceType: ['camera', 'album'],
      success: (res) => {
        const tempPath = res.tempFilePaths[0]
        const photos = this.data.photos.slice()
        photos[index] = tempPath
        const filledCount = photos.filter(Boolean).length
        const guideText = this.data.guideTexts[filledCount] || '拍摄完成'
        this.setData({ photos, photoIndex: filledCount, guideText })
        // 选完图后立即上传当前这张到服务器
        this.uploadSinglePhoto(index, tempPath)
      },
      fail: () => {}
    })
  },
  // 单张上传到服务器，成功后记录 URL
  uploadSinglePhoto(index, localPath) {
    const apiBase = (app.globalData && app.globalData.apiBase) ? app.globalData.apiBase.replace(/\/$/, '') : ''
    if (!apiBase) {
      wx.showToast({ title: '网络未就绪，请稍后再试', icon: 'none' })
      return
    }
    const token = (app.globalData && app.globalData.token) || wx.getStorageSync('token') || ''
    const uploadUrl = apiBase + '/api/upload/image'

    wx.showLoading({ title: '上传中...', mask: true })
    wx.uploadFile({
      url: uploadUrl,
      filePath: localPath,
      name: 'file',
      header: token ? { Authorization: 'Bearer ' + token } : {},
      success: (res) => {
        try {
          const data = JSON.parse(res.data)
          if (data.code === 200 && data.data && data.data.url) {
            const url = data.data.url
            const uploadedUrls = this.data.uploadedUrls.slice()
            uploadedUrls[index] = url
            const photos = this.data.photos.slice()
            photos[index] = url
            this.setData({ uploadedUrls, photos })
          } else {
            wx.showToast({ title: data.message || '上传失败', icon: 'none' })
          }
        } catch (e) {
          wx.showToast({ title: '解析上传结果失败', icon: 'none' })
        }
      },
      fail: (err) => {
        console.error('[upload] 单张上传失败:', err)
        wx.showToast({ title: '上传失败，请重试', icon: 'none' })
      },
      complete: () => {
        wx.hideLoading()
      }
    })
  },

  // 三个角度对应的上传按钮
  onUploadFront() {
    this.choosePhoto(0)
  },
  onUploadLeft() {
    this.choosePhoto(1)
  },
  onUploadRight() {
    this.choosePhoto(2)
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
            photos: ['', '', ''],
            uploadedUrls: [],
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
    const urls = (this.data.uploadedUrls || []).filter(Boolean)
    if (!urls.length) {
      wx.showToast({ title: '请先上传至少一张照片', icon: 'none' })
      return
    }
    // 此时 URL 已在选择时上传完成，这里只负责保存和跳转
    wx.setStorageSync('aiPhotos', urls)
    wx.navigateTo({ url: '/pages/index/result' })
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
