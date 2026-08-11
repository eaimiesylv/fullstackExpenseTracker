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
  title: `Forgot password — ${BRAND_NAME}`,
})

const brandHref = BRAND_HREF
const brandName = BRAND_NAME

const router = useRouter()
const authStore = useAuthStore()

const email = ref('')
const error = ref('')
const loading = ref(false)

function validate() {
  error.value = ''

  if (!email.value) {
    error.value = 'Enter your email address'
  } else if (!/^\S+@\S+\.\S+$/.test(email.value)) {
    error.value = 'Enter a valid email address'
  }

  return !error.value
}

async function handleSubmit() {
  if (!validate()) return

  loading.value = true
  try {
    await authStore.forgotPassword({ email: email.value })

    router.push({
      path: '/verify-otp',
      query: { email: email.value, purpose: 'reset' },
    })
  } catch (apiError: any) {
    error.value = apiError?.errors?.email?.[0] || apiError?.message || 'Unable to send reset code. Please try again.'
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

        <h1 class="font-display text-2xl font-bold text-slate-900">Forgot your password?</h1>
        <p class="mt-2 text-sm text-slate-600">
          Enter the email address on your account and we'll send you a 6-digit code to reset it.
        </p>

        <form class="mt-8 space-y-5" novalidate @submit.prevent="handleSubmit">
          <AuthInput
            id="email"
            v-model="email"
            label="Email address"
            type="email"
            placeholder="you@example.com"
            autocomplete="email"
            :error="error"
          />

          <AuthButton :loading="loading">Send code</AuthButton>
        </form>

        <p class="mt-8 text-center text-sm text-slate-600">
          Remembered your password?
          <NuxtLink to="/login" class="font-semibold text-emerald-600 hover:text-emerald-700">Log in</NuxtLink>
        </p>
      </div>
    </div>
  </div>
</template>