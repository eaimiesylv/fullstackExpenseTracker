import { defineStore } from 'pinia'
import type { AxiosError } from 'axios'
import { useApi } from '~/composables/useApi'
import { useCookie, navigateTo } from '#imports'

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
  const authToken = useCookie<string>('auth_token', COOKIE_OPTIONS)
  authToken.value = token || null
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null as AuthUser | null,
    token: useCookie<string>('auth_token', COOKIE_OPTIONS).value ?? '',
    passwordResetOtp: null as string | null,
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
      updateAuthToken(null)
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

        if (response?.data?.user) {
          this.user = response.data.user
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

        this.token = response?.data?.token
        this.user = response?.data?.user
        updateAuthToken(this.token)

        return response
      } catch (error) {
        throw normalizeApiError(error)
      }
    },

    async verifyEmail(payload: { email: string; otp: string }) {
      try {
        const api = useApi()
        return await api.post('/auth/verify-email', payload)
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

      // The backend currently verifies password-reset OTPs as part of reset-password,
      // so this action preserves the client flow until the user submits a new password.
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
        return await api.post('/auth/forgot-password', payload)
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
        return response
      } catch (error) {
        throw normalizeApiError(error)
      }
    },

    logout(redirect = true) {
      this.clearAuth()

      if (redirect && process.client) {
        navigateTo('/login')
      }
    },
  },
})
