
<script setup lang="ts">
import AuthInput from '~/components/auth/AuthInput.vue'
import AuthButton from '~/components/auth/AuthButton.vue'
import AuthDivider from '~/components/auth/AuthDivider.vue'
import GoogleButton from '~/components/auth/GoogleButton.vue'
import BrandPanel from '~/components/auth/BrandPanel.vue'
import { BRAND_HREF, BRAND_NAME } from '~/config/brand'
import { useAuthStore } from '~/stores/auth'
definePageMeta({
  layout: false,
})
useHead({
  title: `Log in — ${BRAND_NAME}`,
})

const router = useRouter()
const authStore = useAuthStore()
const brandHref = BRAND_HREF
const brandName = BRAND_NAME

const email = ref('')
const password = ref('')
const errors = ref<{ email?: string; password?: string }>({})
const loading = ref(false)
const googleLoading = ref(false)

function validate() {
  errors.value = {}

  if (!email.value) {
    errors.value.email = 'Enter your email address'
  } else if (!/^\S+@\S+\.\S+$/.test(email.value)) {
    errors.value.email = 'Enter a valid email address'
  }

  if (!password.value) {
    errors.value.password = 'Enter your password'
  }

  return Object.keys(errors.value).length === 0
}

async function handleSubmit() {
  if (!validate()) return

  loading.value = true
  try {
    await authStore.login({
      email: email.value,
      password: password.value,
    })

    await router.push('/dashboard')
  } catch (error: any) {
    errors.value = {}

    if (error?.errors) {
      if (error.errors.email) {
        errors.value.email = error.errors.email[0]
      }
      if (error.errors.password) {
        errors.value.password = error.errors.password[0]
      }
    }

    if (!Object.keys(errors.value).length) {
      errors.value.email = error?.message || 'Unable to log in. Please try again.'
    }
  } finally {
    loading.value = false
  }
}

async function handleGoogleLogin() {
  googleLoading.value = true
  try {
    // TODO: redirect to your Google OAuth flow, e.g.
    // window.location.href = '/api/auth/google'
    await new Promise((resolve) => setTimeout(resolve, 900))
  } finally {
    googleLoading.value = false
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

        <h1 class="font-display text-2xl font-bold text-slate-900">Welcome back</h1>
        <p class="mt-2 text-sm text-slate-600">Log in to keep track of your budgets and group money.</p>

        <form class="mt-8 space-y-5" novalidate @submit.prevent="handleSubmit">
          <AuthInput
            id="email"
            v-model="email"
            label="Email address"
            type="email"
            placeholder="you@example.com"
            autocomplete="email"
            :error="errors.email"
          />

          <div>
            <AuthInput
              id="password"
              v-model="password"
              label="Password"
              type="password"
              placeholder="••••••••"
              autocomplete="current-password"
              :error="errors.password"
            />
            <div class="mt-2 text-right">
              <NuxtLink to="/forgot-password" class="text-xs font-medium text-emerald-600 hover:text-emerald-700">
                Forgot password?
              </NuxtLink>
            </div>
          </div>

          <AuthButton :loading="loading">Log in</AuthButton>
        </form>

        <div class="my-6">
          <AuthDivider />
        </div>

        <GoogleButton :loading="googleLoading" @click="handleGoogleLogin" />

        <p class="mt-8 text-center text-sm text-slate-600">
          Don't have an account?
          <NuxtLink to="/register" class="font-semibold text-emerald-600 hover:text-emerald-700">Sign up</NuxtLink>
        </p>
      </div>
    </div>
  </div>
</template>