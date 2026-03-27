// pages/enterprise/resume-history.js - 简历上传记录（历史）
const app = getApp()
const { request } = require('../../utils/request')

Page({
  data: {
    list: [],
    loading: true,
    empty: false
  },

  onLoad() {
    this.loadList()
  },

  onShow() {
    if (this.data.list.length > 0 || !this.data.loading) {
      this.loadList()
    }
  },

  loadList() {
    this.setData({ loading: true })
    const eid = (app.globalData && app.globalData.enterpriseIdFromScene) || (app.globalData && app.globalData.userInfo && app.globalData.userInfo.enterpriseId) || (tt.getStorageSync('userInfo') || {}).enterpriseId || null
    const query = eid ? `?enterpriseId=${eid}&pageSize=100` : '?pageSize=100'
    request({
      url: '/api/enterprise/resume-uploads' + query,
      method: 'GET',
      needAuth: true,
      success: (res) => {
        this.setData({ loading: false })
        if (res.statusCode === 200 && res.data && res.data.code === 200 && Array.isArray(res.data.data && res.data.data.list)) {
          const list = (res.data.data.list || []).map((item) => ({
            id: item.id,
            url: item.url || '',
            fileName: item.fileName || '',
            uploadedAt: item.uploadedAt || 0,
            uploadedAtStr: item.uploadedAtStr || this._formatTime(item.uploadedAt),
            isDefault: !!item.isDefault
          }))
          this.setData({ list, empty: list.length === 0 })
        } else {
          this.setData({ list: [], empty: true })
        }
      },
      fail: () => this.setData({ loading: false, list: [], empty: true })
    })
  },

  uploadResume() {
    const eid = (app.globalData && app.globalData.enterpriseIdFromScene) || (app.globalData && app.globalData.userInfo && app.globalData.userInfo.enterpriseId) || (tt.getStorageSync('userInfo') || {}).enterpriseId || null
    tt.chooseImage({
      count: 1,
      sizeType: ['original', 'compressed'],
      sourceType: ['album', 'camera'],
      success: (fileRes) => {
        const files = fileRes.tempFilePaths || []
        if (!files.length) {
          tt.showToast({ title: '请选择文件', icon: 'none' })
          return
        }
        const filePath = files[0]
        const fileName = '简历文件'
        const apiBase = (app.globalData && app.globalData.apiBase) ? app.globalData.apiBase.replace(/\/$/, '') : ''
        const token = (app.globalData && app.globalData.token) || tt.getStorageSync('token') || ''
        const uploadUrl = apiBase + '/api/upload/file'
        tt.showLoading({ title: '上传中...', mask: true })
        tt.uploadFile({
          url: uploadUrl,
          filePath,
          name: 'file',
          header: token ? { Authorization: 'Bearer ' + token } : {},
          success: (res) => {
            tt.hideLoading()
            try {
              const data = JSON.parse(res.data)
              if (data.code === 200 && data.data && data.data.url) {
                const url = data.data.url
                request({
                  url: '/api/enterprise/resume-uploads',
                  method: 'POST',
                  needAuth: true,
                  data: { url, fileName, enterpriseId: eid },
                  success: (r) => {
                    if (r.statusCode === 200 && r.data && r.data.code === 200) {
                      tt.showToast({ title: '已上传并记录', icon: 'success' })
                      this.loadList()
                    } else {
                      tt.showToast({ title: r.data && r.data.message || '记录失败', icon: 'none' })
                    }
                  },
                  fail: () => tt.showToast({ title: '记录失败，请重试', icon: 'none' })
                })
              } else {
                tt.showToast({ title: data.message || '上传失败', icon: 'none' })
              }
            } catch (e) {
              tt.showToast({ title: '解析上传结果失败', icon: 'none' })
            }
          },
          fail: () => {
            tt.hideLoading()
            tt.showToast({ title: '上传失败，请稍后重试', icon: 'none' })
          }
        })
      },
      fail: () => tt.showToast({ title: '已取消选择', icon: 'none' })
    })
  },

  _formatTime(ts) {
    if (!ts) return ''
    const d = new Date(ts)
    const y = d.getFullYear()
    const m = String(d.getMonth() + 1).padStart(2, '0')
    const day = String(d.getDate()).padStart(2, '0')
    const h = String(d.getHours()).padStart(2, '0')
    const min = String(d.getMinutes()).padStart(2, '0')
    return `${y}-${m}-${day} ${h}:${min}`
  },

  previewResume(e) {
    const url = e.currentTarget.dataset.url
    const fileName = e.currentTarget.dataset.fileName || ''
    if (!url) return
    const fullUrl = url.startsWith('http') ? url : ((app.globalData && app.globalData.apiBase) || '').replace(/\/$/, '') + url
    const ext = (fileName.split('.').pop() || '').toLowerCase()
    const imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp']
    const isImage = imageExts.indexOf(ext) !== -1

    tt.downloadFile({
      url: fullUrl,
      success: (res) => {
        if (res.statusCode !== 200 || !res.tempFilePath) {
          tt.showToast({ title: '打开失败', icon: 'none' })
          return
        }
        const filePath = res.tempFilePath
        if (isImage) {
          tt.previewImage({ current: filePath, urls: [filePath] })
        } else {
          const docTypes = ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'pdf']
          const fileType = docTypes.indexOf(ext) !== -1 ? ext : 'pdf'
          tt.openDocument({
            filePath,
            fileType,
            showMenu: true,
            fail: () => tt.showToast({ title: '该格式暂不支持预览', icon: 'none' })
          })
        }
      },
      fail: () => tt.showToast({ title: '打开失败', icon: 'none' })
    })
  },

  setDefault(e) {
    const id = e.currentTarget.dataset.id
    if (!id) return
    request({
      url: '/api/enterprise/resume-uploads/set-default',
      method: 'POST',
      needAuth: true,
      data: { id },
      success: (res) => {
        if (res.statusCode === 200 && res.data && res.data.code === 200) {
          tt.showToast({ title: '已设为默认简历', icon: 'success' })
          this.loadList()
        } else {
          tt.showToast({ title: res.data && res.data.message || '设置失败', icon: 'none' })
        }
      },
      fail: () => tt.showToast({ title: '设置失败', icon: 'none' })
    })
  },

  deleteResume(e) {
    const id = e.currentTarget.dataset.id
    if (!id) return
    tt.showModal({
      title: '确认删除',
      content: '删除后不可恢复，确定删除该简历记录？',
      confirmText: '删除',
      confirmColor: '#f43f5e',
      success: (res) => {
        if (!res.confirm) return
        request({
          url: '/api/enterprise/resume-uploads/delete',
          method: 'POST',
          needAuth: true,
          data: { id },
          success: (r) => {
            if (r.statusCode === 200 && r.data && r.data.code === 200) {
              tt.showToast({ title: '已删除', icon: 'success' })
              this.loadList()
            } else {
              tt.showToast({ title: r.data && r.data.message || '删除失败', icon: 'none' })
            }
          },
          fail: () => tt.showToast({ title: '删除失败', icon: 'none' })
        })
      }
    })
  },

  goBack() {
    tt.navigateBack({ fail: () => tt.switchTab({ url: '/pages/profile/index' }) })
  }
})
