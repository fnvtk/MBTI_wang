import { defineStore } from 'pinia'
import { ref } from 'vue'
import { request } from '@/utils/request'
import {
  migrateLegacyAuthStorage,
  ADMIN_TOKEN_KEY,
  ADMIN_ROLE_KEY,
  ADMIN_USER_ID_KEY,
  SUPERADMIN_TOKEN_KEY,
  SUPERADMIN_ROLE_KEY,
  SUPERADMIN_USER_ID_KEY,
  clearAdminAuthKeys,
  clearSuperadminAuthKeys
} from '@/utils/authStorage'

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
  const adminLoggedIn = ref(false)
  const superAdminLoggedIn = ref(false)
  const currentUser = ref<any>(null)

  function initAuth() {
    if (typeof window === 'undefined') return
    migrateLegacyAuthStorage()

    const adminToken = localStorage.getItem(ADMIN_TOKEN_KEY)
    const adminRole = localStorage.getItem(ADMIN_ROLE_KEY)
    if (adminToken && adminRole && ['admin', 'enterprise_admin'].includes(adminRole)) {
      adminLoggedIn.value = true
    }

    const saToken = localStorage.getItem(SUPERADMIN_TOKEN_KEY)
    const saRole = localStorage.getItem(SUPERADMIN_ROLE_KEY)
    if (saToken && saRole === 'superadmin') {
      superAdminLoggedIn.value = true
    }
  }

  initAuth()

  async function adminLogin(username: string, password: string): Promise<boolean> {
    try {
      const response = await request.post<LoginResponse>('/auth/admin/login', {
        username,
        password
      })

      if (response.code === 200 && response.data) {
        localStorage.setItem(ADMIN_TOKEN_KEY, response.data.token)
        localStorage.setItem(ADMIN_ROLE_KEY, response.data.user.role)
        localStorage.setItem(ADMIN_USER_ID_KEY, String(response.data.user.id))
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

  async function superAdminLogin(username: string, password: string): Promise<boolean> {
    try {
      const response = await request.post<LoginResponse>('/auth/superadmin/login', {
        username,
        password
      })

      if (response.code === 200 && response.data) {
        localStorage.setItem(SUPERADMIN_TOKEN_KEY, response.data.token)
        localStorage.setItem(SUPERADMIN_ROLE_KEY, response.data.user.role)
        localStorage.setItem(SUPERADMIN_USER_ID_KEY, String(response.data.user.id))
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

  async function adminLogout() {
    try {
      await request.post('/auth/logout')
    } catch (error) {
      console.error('退出登录失败:', error)
    } finally {
      clearAdminAuthKeys()
      adminLoggedIn.value = false
      currentUser.value = null
    }
  }

  async function superAdminLogout() {
    try {
      await request.post('/auth/logout')
    } catch (error) {
      console.error('退出登录失败:', error)
    } finally {
      clearSuperadminAuthKeys()
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
