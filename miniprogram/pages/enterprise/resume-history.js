// pages/enterprise/resume-history.js - 简历上传记录（历史）
const app = getApp()
const { getEffectiveEnterpriseId } = require('../../utils/enterpriseContext.js')
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
    const eid = getEffectiveEnterpriseId()
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
    const eid = getEffectiveEnterpriseId()
    wx.chooseMessageFile({
      count: 1,
      type: 'file',
      extension: ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx'],
      success: (fileRes) => {
        const files = fileRes.tempFiles || []
        if (!files.length) {
          wx.showToast({ title: '请选择文件', icon: 'none' })
          return
        }
        const file = files[0]
        const filePath = file.path || file.tempFilePath
        const fileName = file.name || '简历文件'
        const apiBase = (app.globalData && app.globalData.apiBase) ? app.globalData.apiBase.replace(/\/$/, '') : ''
        const token = (app.globalData && app.globalData.token) || wx.getStorageSync('token') || ''
        const uploadUrl = apiBase + '/api/upload/file'
        wx.showLoading({ title: '上传中...', mask: true })
        wx.uploadFile({
          url: uploadUrl,
          filePath,
          name: 'file',
          header: token ? { Authorization: 'Bearer ' + token } : {},
          success: (res) => {
            wx.hideLoading()
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
                      wx.showToast({ title: '已上传并记录', icon: 'success' })
                      this.loadList()
                    } else {
                      wx.showToast({ title: r.data && r.data.message || '记录失败', icon: 'none' })
                    }
                  },
                  fail: () => wx.showToast({ title: '记录失败，请重试', icon: 'none' })
                })
              } else {
                wx.showToast({ title: data.message || '上传失败', icon: 'none' })
              }
            } catch (e) {
              wx.showToast({ title: '解析上传结果失败', icon: 'none' })
            }
          },
          fail: () => {
            wx.hideLoading()
            wx.showToast({ title: '上传失败，请稍后重试', icon: 'none' })
          }
        })
      },
      fail: () => wx.showToast({ title: '已取消选择', icon: 'none' })
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

    if (imageExts.indexOf(ext) !== -1) {
      wx.previewImage({ current: fullUrl, urls: [fullUrl] })
      return
    }

    const docTypes = ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'pdf']
    const fileType = docTypes.indexOf(ext) !== -1 ? ext : 'pdf'

    wx.showLoading({ title: '正在打开...', mask: true })
    wx.downloadFile({
      url: fullUrl,
      success: (res) => {
        wx.hideLoading()
        if (res.statusCode !== 200 || !res.tempFilePath) {
          wx.showToast({ title: '下载失败', icon: 'none' })
          return
        }
        // 将临时文件复制为带正确扩展名的文件，实体机 openDocument 需要扩展名识别格式
        const fs = wx.getFileSystemManager()
        const dest = `${wx.env.USER_DATA_PATH}/resume_preview.${fileType}`
        try { fs.unlinkSync(dest) } catch (_) {}
        fs.copyFile({
          srcPath: res.tempFilePath,
          destPath: dest,
          success: () => {
            wx.openDocument({
              filePath: dest,
              fileType,
              showMenu: true,
              fail: (err) => {
                console.error('openDocument fail:', err)
                wx.showToast({ title: '该格式暂不支持预览', icon: 'none' })
              }
            })
          },
          fail: () => {
            // 复制失败时直接用临时文件打开
            wx.openDocument({
              filePath: res.tempFilePath,
              fileType,
              showMenu: true,
              fail: () => wx.showToast({ title: '打开失败', icon: 'none' })
            })
          }
        })
      },
      fail: (err) => {
        wx.hideLoading()
        console.error('downloadFile fail:', err)
        wx.showToast({ title: '下载失败，请重试', icon: 'none' })
      }
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
          wx.showToast({ title: '已设为默认简历', icon: 'success' })
          this.loadList()
        } else {
          wx.showToast({ title: res.data && res.data.message || '设置失败', icon: 'none' })
        }
      },
      fail: () => wx.showToast({ title: '设置失败', icon: 'none' })
    })
  },

  deleteResume(e) {
    const id = e.currentTarget.dataset.id
    if (!id) return
    wx.showModal({
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
              wx.showToast({ title: '已删除', icon: 'success' })
              this.loadList()
            } else {
              wx.showToast({ title: r.data && r.data.message || '删除失败', icon: 'none' })
            }
          },
          fail: () => wx.showToast({ title: '删除失败', icon: 'none' })
        })
      }
    })
  },

  goBack() {
    wx.navigateBack({ fail: () => wx.switchTab({ url: '/pages/profile/index' }) })
  }
})
