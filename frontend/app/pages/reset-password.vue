<script setup lang="ts">
import AuthInput from '~/components/auth/AuthInput.vue'
import AuthButton from '~/components/auth/AuthButton.vue'
import BrandPanel from '~/components/auth/BrandPanel.vue'
import { BRAND_HREF, BRAND_NAME } from '~/config/brand'
import { useAuthStore } from '~/stores/auth'

definePageMeta({
  layout: false,
})

useHead({
  title: `Reset password — ${BRAND_NAME}`,
})

const brandHref = BRAND_HREF
const brandName = BRAND_NAME

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const email = computed(() => (route.query.email as string) || '')

function validateResetAccess() {
  if (!email.value || !authStore.passwordResetOtp) {
    router.replace('/forgot-password')
  }
}

onMounted(() => {
  validateResetAccess()
})

const password = ref('')
const confirmPassword = ref('')
const errors = ref<{ password?: string; confirmPassword?: string }>({})
const loading = ref(false)

function validate() {
  errors.value = {}

  if (!password.value) {
    errors.value.password = 'Enter a new password'
  } else if (password.value.length < 8) {
    errors.value.password = 'Password must be at least 8 characters'
  }

  if (!confirmPassword.value) {
    errors.value.confirmPassword = 'Confirm your new password'
  } else if (confirmPassword.value !== password.value) {
    errors.value.confirmPassword = 'Passwords do not match'
  }

  return Object.keys(errors.value).length === 0
}

async function handleSubmit() {
  if (!validate()) return

  loading.value = true
  try {
    await authStore.resetPassword({
      email: email.value,
      password: password.value,
      password_confirmation: confirmPassword.value,
    })

    router.push('/login')
  } catch (error: any) {
    errors.value = {}

    if (error?.errors) {
      if (error.errors.password) {
        errors.value.password = error.errors.password[0]
      }
      if (error.errors.password_confirmation) {
        errors.value.confirmPassword = error.errors.password_confirmation[0]
      }
    }

    if (!Object.keys(errors.value).length) {
      errors.value.password = error?.message || 'Unable to reset password. Please try again.'
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="grid min-h-screen lg:grid-cols-2">
    <BrandPanel />

    <div class="flex items-center justify-center px-6 py-12">
      <div class="w-full max-w-sm">
        <NuxtLink :to="brandHref" class="mb-8 flex items-center gap-2 lg:hidden">
          <span class="flex h-8 w-8 items-center justify-center rounded-lg" style="background: var(--emerald)">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
              <path d="M4 4h16M4 12h10M4 20h16" stroke="white" stroke-width="2.4" stroke-linecap="round" />
            </svg>
          </span>
          <span class="font-display text-lg font-bold tracking-tight text-slate-900">{{ brandName }}</span>
        </NuxtLink>

        <h1 class="font-display text-2xl font-bold text-slate-900">Set a new password</h1>
        <p class="mt-2 text-sm text-slate-600">
          Choose a new password for <span class="font-medium text-slate-900">{{ email }}</span>.
        </p>

        <form class="mt-8 space-y-5" novalidate @submit.prevent="handleSubmit">
          <AuthInput
            id="password"
            v-model="password"
            label="New password"
            type="password"
            placeholder="At least 8 characters"
            autocomplete="new-password"
            :error="errors.password"
          />

          <AuthInput
            id="confirm-password"
            v-model="confirmPassword"
            label="Confirm new password"
            type="password"
            placeholder="Re-enter your password"
            autocomplete="new-password"
            :error="errors.confirmPassword"
          />

          <AuthButton :loading="loading">Reset password</AuthButton>
        </form>

        <p class="mt-8 text-center text-sm text-slate-600">
          Remembered your password?
          <NuxtLink to="/login" class="font-semibold text-emerald-600 hover:text-emerald-700">Log in</NuxtLink>
        </p>
      </div>
    </div>
  </div>
</template>