import axios, { AxiosInstance, AxiosError, InternalAxiosRequestConfig } from 'axios'
import { useAuthStore } from '@/stores/auth'
import type { ApiResponse } from '@/types'

const API_BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000/api'

class ApiClient {
  private axiosInstance: AxiosInstance

  constructor() {
    this.axiosInstance = axios.create({
      baseURL: API_BASE_URL,
      headers: {
        'Content-Type': 'application/json'
      }
    })

    // Intercepteur pour ajouter le token
    this.axiosInstance.interceptors.request.use(
      (config: InternalAxiosRequestConfig) => {
        const authStore = useAuthStore()
        if (authStore.token) {
          config.headers.Authorization = `Bearer ${authStore.token}`
        }
        return config
      },
      (error) => Promise.reject(error)
    )

    // Intercepteur pour gérer les erreurs
    this.axiosInstance.interceptors.response.use(
      (response) => response.data,
      (error: AxiosError<ApiResponse>) => {
        const authStore = useAuthStore()
        
        if (error.response?.status === 401) {
          authStore.logout()
          window.location.href = '/login'
        }

        return Promise.reject(error.response?.data || error)
      }
    )
  }

  async get<T>(url: string, config?: any): Promise<ApiResponse<T>> {
    return this.axiosInstance.get<ApiResponse<T>>(url, config)
  }

  async post<T>(url: string, data?: any, config?: any): Promise<ApiResponse<T>> {
    return this.axiosInstance.post<ApiResponse<T>>(url, data, config)
  }

  async put<T>(url: string, data?: any, config?: any): Promise<ApiResponse<T>> {
    return this.axiosInstance.put<ApiResponse<T>>(url, data, config)
  }

  async delete<T>(url: string, config?: any): Promise<ApiResponse<T>> {
    return this.axiosInstance.delete<ApiResponse<T>>(url, config)
  }
}

export const apiClient = new ApiClient()
