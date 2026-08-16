import axios, { type AxiosError } from 'axios'
import { useAuthStore } from '~/stores/auth'
import { useCookie, useRuntimeConfig, navigateTo } from '#imports'

interface NormalizedApiError {
  message: string
  errors?: Record<string, string[]> | null
  status?: number
}

const COOKIE_OPTIONS = {
  maxAge: 60 * 60 * 24 * 30,
  sameSite: 'lax',
} as const

function normalizeAxiosError(error: AxiosError): NormalizedApiError {
  if (!error.response) {
    return {
      message: 'Network error. Check your connection and try again.',
      errors: null,
      status: undefined,
    }
  }

  const data = error.response.data as Record<string, any>
  const message = data?.message || error.message || 'Something went wrong. Please try again.'
  const errors = data?.errors || null

  return {
    message,
    errors,
    status: error.response.status,
  }
}

export default defineNuxtPlugin(() => {
  const config = useRuntimeConfig()
  const api = axios.create({
    baseURL: config.public.apiBase,
    headers: {
      'Content-Type': 'application/json',
    },
  })

  api.interceptors.request.use((request) => {
    const authStore = useAuthStore()
    const token = authStore.token || useCookie<string>('auth_token', COOKIE_OPTIONS).value

    if (token && request.headers) {
      request.headers.Authorization = `Bearer ${token}`
    }

    return request
  })

  api.interceptors.response.use(
    (response) => response.data,
    (error: AxiosError) => {
      const normalized = normalizeAxiosError(error)

      if (error.response?.status === 401) {
        const authStore = useAuthStore()
        const request = error.config as { headers?: Record<string, any> }
        const hasAuthHeader = !!request?.headers?.Authorization

        if (hasAuthHeader) {
          authStore.logout(false)

          if (import.meta.client) {
            navigateTo('/login')
          }
        }
      }

      return Promise.reject(normalized)
    }
  )

  return {
    provide: {
      api,
    },
  }
})
