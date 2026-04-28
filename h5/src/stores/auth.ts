import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import http from '@/utils/request'

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(localStorage.getItem('token'))
  const userInfo = ref<any>(JSON.parse(localStorage.getItem('userInfo') || 'null'))

  const isLoggedIn = computed(() => !!token.value)
  const hasResume = computed(() => {
    const u = userInfo.value
    return u && u.phone && (u.testCount > 0 || u.mbtiResult)
  })

  // H5 版本用手机号+验证码登录，或者 token 直接注入（企业扫码入口）
  async function loginByPhone(phone: string, code: string) {
    const res = await http.post('/api/auth/phone', { phone, code })
    const data = res.data.data
    token.value = data.token
    userInfo.value = data.user
    localStorage.setItem('token', data.token)
    localStorage.setItem('userInfo', JSON.stringify(data.user))
    return data
  }

  async function sendSmsCode(phone: string) {
    await http.post('/api/auth/sms', { phone })
  }

  async function refreshUserInfo() {
    try {
      const res = await http.get('/api/user/me')
      userInfo.value = res.data.data
      localStorage.setItem('userInfo', JSON.stringify(res.data.data))
    } catch (e) {}
  }

  function setToken(t: string) {
    token.value = t
    localStorage.setItem('token', t)
  }

  function logout() {
    token.value = null
    userInfo.value = null
    localStorage.removeItem('token')
    localStorage.removeItem('userInfo')
  }

  return { token, userInfo, isLoggedIn, hasResume, loginByPhone, sendSmsCode, refreshUserInfo, setToken, logout }
})
