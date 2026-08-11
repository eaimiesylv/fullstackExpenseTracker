<script setup lang="ts">
interface Props {
  loading?: boolean
  type?: 'button' | 'submit'
}

withDefaults(defineProps<Props>(), {
  loading: false,
  type: 'submit',
})
</script>

<template>
  <button
    :type="type"
    :disabled="loading"
    class="grad-cta flex w-full items-center justify-center gap-2 rounded-full px-6 py-3.5 text-sm font-semibold text-white shadow-md shadow-emerald-200 transition hover:brightness-105 disabled:cursor-not-allowed disabled:opacity-70"
  >
    <svg
      v-if="loading"
      class="h-4 w-4 animate-spin"
      viewBox="0 0 24 24"
      fill="none"
    >
      <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" opacity="0.25" />
      <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
    </svg>
    <slot />
  </button>
</template>