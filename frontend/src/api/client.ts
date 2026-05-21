import axios, { type AxiosError, type InternalAxiosRequestConfig } from 'axios'
import { useAuthStore } from '@/stores/auth'
import router from '@/router'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8000/api',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  timeout: 30000,
})

// Request interceptor — attach JWT
api.interceptors.request.use((config: InternalAxiosRequestConfig) => {
  const token = localStorage.getItem('flowforge_token')
  if (token && config.headers) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// Response interceptor — handle token expiry
api.interceptors.response.use(
  (response) => response,
  async (error: AxiosError) => {
    const originalRequest = error.config as InternalAxiosRequestConfig & { _retry?: boolean }

    if (error.response?.status === 401 && !originalRequest._retry) {
      originalRequest._retry = true

      try {
        const { data } = await axios.post(
          `${import.meta.env.VITE_API_URL || 'http://localhost:8000/api'}/auth/refresh`,
          {},
          { headers: { Authorization: `Bearer ${localStorage.getItem('flowforge_token')}` } }
        )
        localStorage.setItem('flowforge_token', data.token)
        if (originalRequest.headers) {
          originalRequest.headers.Authorization = `Bearer ${data.token}`
        }
        return api(originalRequest)
      } catch {
        const authStore = useAuthStore()
        authStore.logout()
        router.push('/login')
        return Promise.reject(error)
      }
    }

    return Promise.reject(error)
  }
)

export default api
