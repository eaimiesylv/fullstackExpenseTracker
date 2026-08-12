<script setup lang="ts">
import OtpInput from '~/components/auth/OtpInput.vue'
import AuthButton from '~/components/auth/AuthButton.vue'
import BrandPanel from '~/components/auth/BrandPanel.vue'
import { BRAND_HREF, BRAND_NAME } from '~/config/brand'
import { useAuthStore } from '~/stores/auth'

definePageMeta({
  layout: false,
})

useHead({
  title: `Verify your email — ${BRAND_NAME}`,
})

const brandHref = BRAND_HREF
const brandName = BRAND_NAME

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const email = computed(() => (route.query.email as string) || 'your email')
const actualEmail = computed(() => (route.query.email as string) || '')
// 'register' -> new account verification, lands on the dashboard
// 'reset' -> forgot-password flow, lands on the reset-password screen
const purpose = computed(() => (route.query.purpose as string) === 'reset' ? 'reset' : 'register')
const flowMessage = computed(() => authStore.otpFlow?.message || (purpose.value === 'reset' ? 'If an account exists, a password reset code has been sent.' : 'Verify your email address using the verification code sent to your email.'))

function validateFlow() {
  if (!authStore.otpFlow || authStore.otpFlow.email !== actualEmail.value || authStore.otpFlow.purpose !== purpose.value) {
    router.replace(purpose.value === 'reset' ? '/forgot-password' : '/login')
  }
}

const RESEND_SECONDS = 120

const digits = ref<string[]>(Array(6).fill(''))
const error = ref('')
const loading = ref(false)
const resending = ref(false)

const secondsLeft = ref(RESEND_SECONDS)
let timer: ReturnType<typeof setInterval> | null = null

const formattedTime = computed(() => {
  const minutes = Math.floor(secondsLeft.value / 60)
  const seconds = secondsLeft.value % 60
  return `${minutes}:${seconds.toString().padStart(2, '0')}`
})

const canResend = computed(() => secondsLeft.value <= 0)

function startTimer() {
  secondsLeft.value = RESEND_SECONDS
  if (timer) clearInterval(timer)
  timer = setInterval(() => {
    if (secondsLeft.value > 0) {
      secondsLeft.value -= 1
    } else if (timer) {
      clearInterval(timer)
    }
  }, 1000)
}

onMounted(() => {
  validateFlow()
  startTimer()
})
onUnmounted(() => {
  if (timer) clearInterval(timer)
})

async function verifyCode(code: string) {
  error.value = ''
  loading.value = true
  try {
    if (purpose.value === 'reset') {
      await authStore.verifyPasswordResetOtp({
        email: actualEmail.value,
        otp: code,
      })
    } else {
      await authStore.verifyEmail({
        email: actualEmail.value,
        otp: code,
      })
    }

    if (purpose.value === 'reset') {
      router.push({ path: '/reset-password', query: { email: actualEmail.value } })
    } else {
      router.push('/dashboard')
    }
  } catch (apiError: any) {
    const message = apiError?.errors?.otp?.[0] || apiError?.message || 'That code is incorrect. Try again.'
    error.value = message
    digits.value = Array(6).fill('')
  } finally {
    loading.value = false
  }
}

function handleSubmit() {
  const code = digits.value.join('')
  if (code.length !== 6) {
    error.value = 'Enter all 6 digits'
    return
  }
  verifyCode(code)
}

async function handleResend() {
  if (!canResend.value || resending.value) return

  resending.value = true
  try {
    if (purpose.value === 'reset') {
      await authStore.resendPasswordResetOtp({ email: actualEmail.value })
    } else {
      await authStore.resendVerificationOtp({ email: actualEmail.value })
    }

    digits.value = Array(6).fill('')
    error.value = ''
    startTimer()
  } catch (error: any) {
    error.value = error?.errors?.email?.[0] || error?.message || 'Unable to resend code. Please try again.'
  } finally {
    resending.value = false
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

        <h1 class="font-display text-2xl font-bold text-slate-900">Verify your email</h1>
        <p class="mt-2 text-sm text-slate-600">
          {{ flowMessage }}
        </p>
        <p v-if="email !== 'your email'" class="mt-1 text-sm text-slate-500">
          We sent it to <span class="font-medium text-slate-900">{{ email }}</span>.
        </p>

        <form class="mt-8" novalidate @submit.prevent="handleSubmit">
          <OtpInput v-model="digits" :error="error" @complete="verifyCode" />

          <div class="mt-8">
            <AuthButton :loading="loading">Verify code</AuthButton>
          </div>
        </form>

        <div class="mt-6 text-center text-sm">
          <span v-if="!canResend" class="text-slate-500">
            Resend code in <span class="font-mono font-medium text-slate-700">{{ formattedTime }}</span>
          </span>
          <button
            v-else
            type="button"
            class="font-semibold text-emerald-600 hover:text-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="resending"
            @click="handleResend"
          >
            {{ resending ? 'Sending…' : 'Resend code' }}
          </button>
        </div>

        <p class="mt-8 text-center text-sm text-slate-600">
          Wrong email?
          <NuxtLink :to="purpose === 'reset' ? '/forgot-password' : '/register'" class="font-semibold text-emerald-600 hover:text-emerald-700">
            Go back
          </NuxtLink>
        </p>
      </div>
    </div>
  </div>
</template>