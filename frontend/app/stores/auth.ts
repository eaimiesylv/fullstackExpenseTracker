import { defineStore } from 'pinia'
import type { AxiosError } from 'axios'
import { useApi } from '~/composables/useApi'
import { navigateTo, useCookie } from '#imports'

export interface AuthUser {
  id: string
  fullname: string
  email?: string | null
  phone_number?: string | null
  email_verified_at?: string | null
  phone_verified_at?: string | null
  status?: string
  profile_image?: string | null
  created_at?: string
  updated_at?: string
}

export interface NormalizedApiError {
  message: string
  errors?: Record<string, string[]> | null
  status?: number
}

function normalizeApiError(error: unknown): NormalizedApiError {
  if (!error || typeof error !== 'object') {
    return {
      message: 'Something went wrong. Please try again.',
      errors: null,
    }
  }

  if ((error as AxiosError).isAxiosError) {
    const axiosError = error as AxiosError

    if (!axiosError.response) {
      return {
        message: 'Network error. Check your connection and try again.',
        errors: null,
      }
    }

    const data = axiosError.response.data as Record<string, any>
    return {
      message: data?.message || axiosError.message || 'Something went wrong. Please try again.',
      errors: data?.errors || null,
      status: axiosError.response.status,
    }
  }

  const fallback = error as NormalizedApiError
  return {
    message: fallback.message || 'Something went wrong. Please try again.',
    errors: fallback.errors || null,
    status: fallback.status,
  }
}

const COOKIE_OPTIONS = {
  maxAge: 60 * 60 * 24 * 30,
  sameSite: 'lax',
} as const

function updateAuthToken(token: string | null) {
  if (import.meta.client) {
    const cookie = useCookie<string | null>('auth_token', COOKIE_OPTIONS)
    if (token) {
      window.localStorage.setItem('auth_token', token)
      cookie.value = token
    } else {
      window.localStorage.removeItem('auth_token')
      cookie.value = null
    }
  }
}

function readStoredToken() {
  if (import.meta.client) {
    const cookieToken = useCookie<string | null>('auth_token', COOKIE_OPTIONS).value
    if (cookieToken) {
      return cookieToken
    }

    return window.localStorage.getItem('auth_token') || ''
  }

  return ''
}

function extractAuthResponse(response: any) {
  const payload = response?.data ?? response
  const data = payload?.data ?? payload

  return {
    token: data?.token ?? payload?.token ?? null,
    user: data?.user ?? payload?.user ?? null,
  }
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null as AuthUser | null,
    token: readStoredToken(),
    passwordResetOtp: null as string | null,
    otpFlow: null as { email: string; purpose: 'register' | 'reset'; message?: string } | null,
  }),

  getters: {
    isAuthenticated: (state) => Boolean(state.token),
  },

  actions: {
    setAuth(user: AuthUser | null, token?: string | null) {
      this.user = user

      if (token !== undefined) {
        this.token = token || ''
        updateAuthToken(this.token)
      }
    },

    clearAuth() {
      this.user = null
      this.token = ''
      this.passwordResetOtp = null
      this.otpFlow = null
      updateAuthToken(null)
    },

    beginOtpFlow(email: string, purpose: 'register' | 'reset', message?: string) {
      this.otpFlow = { email, purpose, message }
    },

    clearOtpFlow() {
      this.otpFlow = null
    },

    async register(payload: {
      fullname: string
      email?: string
      phone_number: string
      password: string
      password_confirmation: string
    }) {
      try {
        const api = useApi()
        const response = await api.post('/auth/register', payload)
        const authData = extractAuthResponse(response)

        if (authData.user) {
          this.user = authData.user as AuthUser
        }

        return response
      } catch (error) {
        throw normalizeApiError(error)
      }
    },

    async login(payload: { email: string; password: string }) {
      try {
        const api = useApi()
        const response = await api.post('/auth/login', payload)
        const authData = extractAuthResponse(response)

        this.token = authData.token || ''
        this.user = (authData.user as AuthUser | null) || null
        this.otpFlow = null
        updateAuthToken(this.token)

        return response
      } catch (error) {
        throw normalizeApiError(error)
      }
    },

    async verifyEmail(payload: { email: string; otp: string }) {
      try {
        const api = useApi()
        const response = await api.post('/auth/verify-email', payload)
        const authData = extractAuthResponse(response)

        if (authData.token) {
          this.token = authData.token
          this.user = authData.user as AuthUser | null
          updateAuthToken(this.token)
        }

        this.otpFlow = null
        return response
      } catch (error) {
        throw normalizeApiError(error)
      }
    },

    async verifyPasswordResetOtp(payload: { email: string; otp: string }) {
      if (!payload.email || !payload.otp) {
        throw {
          message: 'Invalid reset code. Please request a new password reset code.',
          errors: null,
        }
      }

      this.passwordResetOtp = payload.otp
      return Promise.resolve({ message: 'OK' })
    },

    async resendVerificationOtp(payload: { email: string }) {
      try {
        const api = useApi()
        return await api.post('/auth/resend-verification-otp', payload)
      } catch (error) {
        throw normalizeApiError(error)
      }
    },

    async resendPasswordResetOtp(payload: { email: string }) {
      try {
        const api = useApi()
        return await api.post('/auth/resend-password-reset-otp', payload)
      } catch (error) {
        throw normalizeApiError(error)
      }
    },

    async forgotPassword(payload: { email: string }) {
      try {
        const api = useApi()
        const response = await api.post('/auth/forgot-password', payload)
        this.beginOtpFlow(payload.email, 'reset', response?.message)
        return response
      } catch (error) {
        throw normalizeApiError(error)
      }
    },

    async resetPassword(payload: {
      email: string
      otp?: string
      password: string
      password_confirmation: string
    }) {
      try {
        const api = useApi()
        const otp = payload.otp || this.passwordResetOtp

        if (!otp) {
          throw {
            message: 'Missing reset code. Please request a new password reset code.',
            errors: null,
          }
        }

        const response = await api.post('/auth/reset-password', {
          email: payload.email,
          otp,
          password: payload.password,
          password_confirmation: payload.password_confirmation,
        })

        this.passwordResetOtp = null
        this.otpFlow = null
        return response
      } catch (error) {
        throw normalizeApiError(error)
      }
    },

    async logoutApi() {
      try {
        const api = useApi()
        await api.post('/auth/logout')
      } catch (error) {
        // Ignore backend logout errors and still clear the client session.
      } finally {
        this.clearAuth()
        if (import.meta.client) {
          navigateTo('/login')
        }
      }
    },

    logout(redirect = true) {
      this.clearAuth()

      if (redirect && import.meta.client) {
        navigateTo('/login')
      }
    },
  },
})
