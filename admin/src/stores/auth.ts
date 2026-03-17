import { defineStore } from 'pinia'
import { ref } from 'vue'
import { request } from '@/utils/request'

interface LoginResponse {
  code: number
  message: string
  data: {
    token: string
    expires_in: number
    user: {
      id: number
      username: string
      role: string
      enterprise_id?: number | null
    }
  }
}

export const useAuthStore = defineStore('auth', () => {
  // 管理员登录状态
  const adminLoggedIn = ref(false)
  const superAdminLoggedIn = ref(false)
  const currentUser = ref<any>(null)

  // 初始化登录状态（自动执行）
  function initAuth() {
    if (typeof window !== 'undefined') {
      const token = localStorage.getItem('authToken')
      const userRole = localStorage.getItem('userRole')
      
      if (token && userRole) {
        if (userRole === 'superadmin') {
          superAdminLoggedIn.value = true
        } else if (['admin', 'enterprise_admin'].includes(userRole)) {
          adminLoggedIn.value = true
        }
      }
    }
  }

  // 自动初始化
  initAuth()

  // 管理员登录
  async function adminLogin(username: string, password: string): Promise<boolean> {
    try {
      const response = await request.post<LoginResponse>('/auth/admin/login', {
        username,
        password
      })

      if (response.code === 200 && response.data) {
        // 存储Token和用户信息
        localStorage.setItem('authToken', response.data.token)
        localStorage.setItem('userRole', response.data.user.role)
        localStorage.setItem('userId', String(response.data.user.id))
        localStorage.setItem('adminLoggedIn', 'true')
        
        currentUser.value = response.data.user
        adminLoggedIn.value = true
        
        return true
      }
      return false
    } catch (error: any) {
      console.error('登录失败:', error)
      throw error
    }
  }

  // 超级管理员登录
  async function superAdminLogin(username: string, password: string): Promise<boolean> {
    try {
      const response = await request.post<LoginResponse>('/auth/superadmin/login', {
        username,
        password
      })

      if (response.code === 200 && response.data) {
        // 存储Token和用户信息
        localStorage.setItem('authToken', response.data.token)
        localStorage.setItem('userRole', response.data.user.role)
        localStorage.setItem('userId', String(response.data.user.id))
        localStorage.setItem('superAdminLoggedIn', 'true')
        
        currentUser.value = response.data.user
        superAdminLoggedIn.value = true
        
        return true
      }
      return false
    } catch (error: any) {
      console.error('登录失败:', error)
      throw error
    }
  }

  // 管理员登出
  async function adminLogout() {
    try {
      await request.post('/auth/logout')
    } catch (error) {
      console.error('退出登录失败:', error)
    } finally {
      // 清除本地存储
      localStorage.removeItem('authToken')
      localStorage.removeItem('userRole')
      localStorage.removeItem('userId')
      localStorage.removeItem('adminLoggedIn')
      localStorage.removeItem('superAdminLoggedIn')
      
      adminLoggedIn.value = false
      currentUser.value = null
    }
  }

  // 超级管理员登出
  async function superAdminLogout() {
    try {
      await request.post('/auth/logout')
    } catch (error) {
      console.error('退出登录失败:', error)
    } finally {
      // 清除本地存储
      localStorage.removeItem('authToken')
      localStorage.removeItem('userRole')
      localStorage.removeItem('userId')
      localStorage.removeItem('adminLoggedIn')
      localStorage.removeItem('superAdminLoggedIn')
      
      superAdminLoggedIn.value = false
      currentUser.value = null
    }
  }

  return {
    adminLoggedIn,
    superAdminLoggedIn,
    currentUser,
    initAuth,
    adminLogin,
    superAdminLogin,
    adminLogout,
    superAdminLogout
  }
})

