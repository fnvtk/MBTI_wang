import axios from 'axios'
import type { AxiosInstance, AxiosRequestConfig, AxiosResponse } from 'axios'
import { ElMessage } from 'element-plus'
import {
  getBearerTokenForCurrentApp,
  clearAdminAuthKeys,
  clearSuperadminAuthKeys
} from '@/utils/authStorage'

let lastBizErrorKey = ''
let lastBizErrorAt = 0
function showBizErrorOnce(message: string) {
  const key = message || '请求失败'
  const now = Date.now()
  if (key === lastBizErrorKey && now - lastBizErrorAt < 1800) return
  lastBizErrorKey = key
  lastBizErrorAt = now
  ElMessage.error(key)
}

// 获取API基础URL（开发环境留空 VITE_API_BASE_URL 时走同源 /api/v1，由 Vite 代理到本机后端）
export const getApiV1BaseURL = (): string => {
  const raw = import.meta.env.VITE_API_BASE_URL as string | undefined
  const envURL = typeof raw === 'string' ? raw.trim() : ''
  if (envURL) {
    return envURL.endsWith('/') ? `${envURL}api/v1` : `${envURL}/api/v1`
  }
  return '/api/v1'
}

const getBaseURL = getApiV1BaseURL

// 本地连云库时接口可能较慢：开发环境默认放宽；可用 VITE_REQUEST_TIMEOUT_MS 覆盖
const requestTimeoutMs = (() => {
  const raw = import.meta.env.VITE_REQUEST_TIMEOUT_MS
  const n = typeof raw === 'string' ? Number(raw.trim()) : Number(raw)
  if (Number.isFinite(n) && n > 0) return n
  return import.meta.env.DEV ? 180000 : 15000
})()

// 创建 axios 实例
const service: AxiosInstance = axios.create({
  baseURL: getBaseURL(),
  timeout: requestTimeoutMs,
  headers: {
    'Content-Type': 'application/json'
  }
})

// 请求拦截器
service.interceptors.request.use(
  (config) => {
    // 可以在这里添加 token
    const token = getBearerTokenForCurrentApp()
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  },
  (error) => {
    console.error('请求错误:', error)
    return Promise.reject(error)
  }
)

// 响应拦截器
service.interceptors.response.use(
  (response: AxiosResponse) => {
    const res = response.data

    // 如果返回的状态码不是 200，则认为是错误
    if (res.code && res.code !== 200) {
      showBizErrorOnce(res.message || '请求失败')
      return Promise.reject(new Error(res.message || '请求失败'))
    }

    return res
  },
  (error) => {
    console.error('响应错误:', error)
    
    if (error.response) {
      const { status, data } = error.response
      
      if (status === 401) {
        ElMessage.error('未授权，请重新登录')
        if (window.location.pathname.startsWith('/superadmin')) {
          clearSuperadminAuthKeys()
          window.location.href = '/superadmin/login'
        } else {
          clearAdminAuthKeys()
          window.location.href = '/admin/login'
        }
      } else if (status === 403) {
        showBizErrorOnce(data?.message || '拒绝访问')
      } else if (status === 404) {
        ElMessage.error('请求地址不存在')
      } else if (status === 500) {
        ElMessage.error('服务器错误')
      } else {
        ElMessage.error(data?.message || '请求失败')
      }
    } else if (error.request) {
      ElMessage.error('网络错误，请检查网络连接')
    } else {
      ElMessage.error('请求配置错误')
    }

    return Promise.reject(error)
  }
)

// 导出请求方法
export const request = {
  get<T = any>(url: string, config?: AxiosRequestConfig): Promise<T> {
    return service.get(url, config)
  },

  post<T = any>(url: string, data?: any, config?: AxiosRequestConfig): Promise<T> {
    return service.post(url, data, config)
  },

  put<T = any>(url: string, data?: any, config?: AxiosRequestConfig): Promise<T> {
    return service.put(url, data, config)
  },

  delete<T = any>(url: string, config?: AxiosRequestConfig): Promise<T> {
    return service.delete(url, config)
  },

  patch<T = any>(url: string, data?: any, config?: AxiosRequestConfig): Promise<T> {
    return service.patch(url, data, config)
  }
}

export default service

