<script setup lang="ts">
interface Props {
  id: string
  label: string
  type?: 'text' | 'email' | 'password'
  placeholder?: string
  autocomplete?: string
  error?: string
}

const props = withDefaults(defineProps<Props>(), {
  type: 'text',
  placeholder: '',
  autocomplete: 'off',
  error: '',
})

const model = defineModel<string>({ default: '' })

const showPassword = ref(false)

const inputType = computed(() => {
  if (props.type !== 'password') return props.type
  return showPassword.value ? 'text' : 'password'
})
</script>

<template>
  <div>
    <label :for="id" class="mb-1.5 block text-sm font-medium text-slate-700">
      {{ label }}
    </label>

    <div class="relative">
      <input
        :id="id"
        v-model="model"
        :type="inputType"
        :placeholder="placeholder"
        :autocomplete="autocomplete"
        :aria-invalid="!!error"
        class="w-full rounded-xl border bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 transition focus:outline-none focus:ring-2"
        :class="error
          ? 'border-rose-300 focus:border-rose-400 focus:ring-rose-200'
          : 'border-slate-200 focus:border-emerald-500 focus:ring-emerald-500/30'"
      />

      <button
        v-if="type === 'password'"
        type="button"
        class="absolute inset-y-0 right-0 flex items-center pr-4 text-slate-400 hover:text-slate-600"
        :aria-label="showPassword ? 'Hide password' : 'Show password'"
        @click="showPassword = !showPassword"
      >
        <svg v-if="!showPassword" width="18" height="18" viewBox="0 0 24 24" fill="none">
          <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z" stroke="currentColor" stroke-width="1.8" />
          <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8" />
        </svg>
        <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none">
          <path d="M3 3l18 18M10.6 10.6a3 3 0 0 0 4.24 4.24M6.6 6.7C4.2 8.2 2 12 2 12s3.6 7 10 7c1.8 0 3.4-.4 4.7-1.1M14.6 5.3C13.8 5.1 13 5 12 5c-6.4 0-10 7-10 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
        </svg>
      </button>
    </div>

    <p v-if="error" class="mt-1.5 text-xs text-rose-600">{{ error }}</p>
  </div>
</template>