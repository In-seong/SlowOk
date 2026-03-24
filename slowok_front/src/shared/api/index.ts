import axios from 'axios'
import type { AxiosInstance, InternalAxiosRequestConfig, AxiosResponse } from 'axios'

function isAdminApp(): boolean {
  return window.location.port === '5175'
    || window.location.hostname.includes('admin')
    || window.location.hash.startsWith('#/')
}

const api: AxiosInstance = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/api',
  timeout: 10000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
})

api.interceptors.request.use(
  (config: InternalAxiosRequestConfig) => {
    const token = isAdminApp()
      ? localStorage.getItem('adminToken')
      : localStorage.getItem('userToken')

    if (token && config.headers) {
      config.headers.Authorization = `Bearer ${token}`
    }
    // MASTER가 선택한 기관 ID를 헤더로 전송
    const institutionId = localStorage.getItem('selectedInstitutionId')
    if (institutionId && config.headers) {
      config.headers['X-Institution-Id'] = institutionId
    }
    // User 활성 프로필 ID 헤더 전송
    const activeProfileId = localStorage.getItem('activeProfileId')
    if (activeProfileId && config.headers) {
      config.headers['X-Active-Profile-Id'] = activeProfileId
    }
    if (config.data instanceof FormData && config.headers) {
      delete config.headers['Content-Type']
    }
    return config
  },
  (error) => {
    return Promise.reject(error)
  }
)

api.interceptors.response.use(
  (response: AxiosResponse) => {
    return response
  },
  (error) => {
    if (error.response?.status === 401) {
      if (isAdminApp()) {
        localStorage.removeItem('adminToken')
        localStorage.removeItem('adminIsLoggedIn')
      } else {
        localStorage.removeItem('userToken')
        localStorage.removeItem('userIsLoggedIn')
        localStorage.removeItem('activeProfileId')
      }
    }
    return Promise.reject(error)
  }
)

export default api
