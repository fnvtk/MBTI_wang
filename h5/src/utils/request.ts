import axios, { type AxiosInstance, type AxiosRequestConfig, type AxiosResponse } from 'axios'
import { useAuthStore } from '@/stores/auth'
import router from '@/router'

const BASE_URL = import.meta.env.VITE_API_BASE_URL || '/api'

const http: AxiosInstance = axios.create({
  baseURL: BASE_URL,
  timeout: 20000,
  headers: { 'Content-Type': 'application/json' }
})

// 请求拦截：自动附加 token
http.interceptors.request.use((config) => {
  try {
    const auth = useAuthStore()
    if (auth.token) {
      config.headers['Authorization'] = `Bearer ${auth.token}`
    }
  } catch (e) {
    const token = localStorage.getItem('token')
    if (token) config.headers['Authorization'] = `Bearer ${token}`
  }
  return config
})

// 响应拦截：统一处理 401
http.interceptors.response.use(
  (res: AxiosResponse) => {
    if (res.data && res.data.code !== undefined && res.data.code !== 200) {
      return Promise.reject(new Error(res.data.message || '请求失败'))
    }
    return res
  },
  (error) => {
    if (error.response?.status === 401) {
      try {
        const auth = useAuthStore()
        auth.logout()
      } catch (e) {
        localStorage.removeItem('token')
        localStorage.removeItem('userInfo')
      }
      router.push('/login')
    }
    return Promise.reject(error)
  }
)

// 兼容命名导出（部分页面使用 import { request } 方式）
export async function request(config: { url: string; method?: string; data?: any; params?: any }) {
  const res = await http({
    url: config.url,
    method: (config.method || 'GET') as any,
    data: config.data,
    params: config.params
  })
  return res.data
}

export default http
