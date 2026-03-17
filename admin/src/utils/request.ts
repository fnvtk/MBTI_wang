import axios from 'axios'
import type { AxiosInstance, AxiosRequestConfig, AxiosResponse } from 'axios'
import { ElMessage } from 'element-plus'

// 获取API基础URL
const getBaseURL = (): string => {
  const envURL = import.meta.env.VITE_API_BASE_URL
  if (envURL) {
    // 如果环境变量是完整URL，拼接 /api/v1
    return envURL.endsWith('/') ? `${envURL}api/v1` : `${envURL}/api/v1`
  }
  // 默认使用相对路径
  return '/api/v1'
}

// 创建 axios 实例
const service: AxiosInstance = axios.create({
  baseURL: getBaseURL(),
  timeout: 15000,
  headers: {
    'Content-Type': 'application/json'
  }
})

// 请求拦截器
service.interceptors.request.use(
  (config) => {
    // 可以在这里添加 token
    const token = localStorage.getItem('authToken')
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
      ElMessage.error(res.message || '请求失败')
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
        // 清除所有登录状态和Token
        localStorage.removeItem('authToken')
        localStorage.removeItem('userRole')
        localStorage.removeItem('userId')
        localStorage.removeItem('adminLoggedIn')
        localStorage.removeItem('superAdminLoggedIn')
        
        // 根据当前路径跳转到对应登录页
        if (window.location.pathname.startsWith('/superadmin')) {
          window.location.href = '/superadmin/login'
        } else {
          window.location.href = '/admin/login'
        }
      } else if (status === 403) {
        ElMessage.error('拒绝访问')
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

