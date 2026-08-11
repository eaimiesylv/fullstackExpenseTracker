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
  title: `Sign up — ${BRAND_NAME}`,
})

const router = useRouter()
const authStore = useAuthStore()
const brandHref = BRAND_HREF
const brandName = BRAND_NAME

const form = reactive({
  fullname: '',
  email: '',
  phone_number: '',
  password: '',
  password_confirmation: '',
})
const errors = ref<{
  fullname?: string
  email?: string
  phone_number?: string
  password?: string
  password_confirmation?: string
}>({})
const loading = ref(false)
const googleLoading = ref(false)

function validate() {
  errors.value = {}

  if (!form.fullname.trim()) {
    errors.value.fullname = 'Enter your full name'
  }

  if (!form.email.trim()) {
    errors.value.email = 'Enter your email address or phone number'
  
  }

  //  if (!form.phone_number.trim()) {
   
  //   errors.value.phone_number = 'Enter your phone number or email address'
  // }

  if (form.email.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) {
    errors.value.email = 'Enter a valid email address'
  }

  // if (!form.phone_number.trim() && form.email.trim()) {
  //   // Phone is optional when email is provided.
  // } else if (!form.phone_number.trim()) {
  //   errors.value.phone_number = 'Enter your phone number'
  // }

  if (!form.password) {
    errors.value.password = 'Enter your password'
  } else if (form.password.length < 8) {
    errors.value.password = 'Password must be at least 8 characters'
  }

  if (!form.password_confirmation) {
    errors.value.password_confirmation = 'Confirm your password'
  } else if (form.password_confirmation !== form.password) {
    errors.value.password_confirmation = 'Passwords do not match'
  }

  return Object.keys(errors.value).length === 0
}

async function handleSubmit() {
  if (!validate()) return

  loading.value = true
  try {
    const response = await authStore.register({
      fullname: form.fullname,
      email: form.email || undefined,
      phone_number: form.phone_number,
      password: form.password,
      password_confirmation: form.password_confirmation,
    })

    const email = response?.data?.user?.email || form.email || form.phone_number
    authStore.beginOtpFlow(email, 'register', response?.message)
    await router.push({
      path: '/verify-otp',
      query: { email, purpose: 'register' },
    })
  } catch (error: any) {
    errors.value = {}

    if (error?.errors) {
      for (const key of Object.keys(error.errors)) {
        if (Object.prototype.hasOwnProperty.call(errors.value, key)) {
          errors.value[key as keyof typeof errors.value] = error.errors[key]?.[0] ?? ''
        }
      }
    }

    if (!Object.keys(errors.value).length) {
      errors.value.fullname = error?.message || 'Unable to register. Please try again.'
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

        <h1 class="font-display text-2xl font-bold text-slate-900">Create your account</h1>
        <p class="mt-2 text-sm text-slate-600">Start tracking budgets, managing expenses, and collaborating with your team.</p>

        <form class="mt-8 space-y-5" novalidate @submit.prevent="handleSubmit">
          <AuthInput
            id="fullname"
            v-model="form.fullname"
            label="Full name"
            type="text"
            placeholder="Jane Doe"
            autocomplete="name"
            :error="errors.fullname"
          />

          <AuthInput
            id="email"
            v-model="form.email"
            label="Email address"
            type="email"
            placeholder="you@example.com"
            autocomplete="email"
            :error="errors.email"
          />

          <!-- <AuthInput
            id="phone_number"
            v-model="form.phone_number"
            label="Phone number"
            type="tel"
            placeholder="(555) 123-4567"
            autocomplete="tel"
            :error="errors.phone_number"
          /> -->

          <AuthInput
            id="password"
            v-model="form.password"
            label="Password"
            type="password"
            placeholder="••••••••"
            autocomplete="new-password"
            :error="errors.password"
          />

          <AuthInput
            id="password_confirmation"
            v-model="form.password_confirmation"
            label="Confirm password"
            type="password"
            placeholder="••••••••"
            autocomplete="new-password"
            :error="errors.password_confirmation"
          />

          <AuthButton :loading="loading">Create account</AuthButton>
        </form>

        <div class="my-6">
          <AuthDivider />
        </div>

        <GoogleButton :loading="googleLoading" @click="handleGoogleLogin" />

        <p class="mt-8 text-center text-sm text-slate-600">
          Already have an account?
          <NuxtLink to="/login" class="font-semibold text-emerald-600 hover:text-emerald-700">Sign in</NuxtLink>
        </p>
      </div>
    </div>
  </div>
</template>