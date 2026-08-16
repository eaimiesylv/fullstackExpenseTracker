import { useAuthStore } from '~/stores/auth'
import { useCookie } from '#imports'

export default defineNuxtRouteMiddleware(() => {
  const authStore = useAuthStore()
  const token = authStore.token || useCookie<string | null>('auth_token').value

  if (!token) {
    return navigateTo('/login')
  }
})
