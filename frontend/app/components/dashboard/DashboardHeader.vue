<script setup lang="ts">
interface Props {
  title: string
  subtitle?: string
  userName?: string
  hasNotifications?: boolean
  actionLabel?: string
}

withDefaults(defineProps<Props>(), {
  subtitle: '',
  userName: 'User',
  hasNotifications: false,
  actionLabel: '',
})

const emit = defineEmits<{
  'action-click': []
}>()

function initials(name: string) {
  return name
    .split(' ')
    .map((part) => part[0])
    .join('')
    .slice(0, 2)
    .toUpperCase()
}
</script>

<template>
  <header class="flex items-center justify-between border-b border-slate-200 bg-white px-8 py-5">
    <div class="flex items-center gap-5">
      <button
        v-if="actionLabel"
        type="button"
        class="flex items-center gap-2 rounded-full bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
        @click="emit('action-click')"
      >
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
          <path d="M12 5v14M5 12h14" />
        </svg>
        {{ actionLabel }}
      </button>

      <div>
        <h1 class="font-display text-2xl font-bold text-slate-900">{{ title }}</h1>
        <p v-if="subtitle" class="mt-1 text-sm text-slate-500">{{ subtitle }}</p>
      </div>
    </div>

    <div class="flex items-center gap-5">
      <button type="button" class="relative text-slate-400 hover:text-slate-600" aria-label="Notifications">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0" />
        </svg>
        <span
          v-if="hasNotifications"
          class="absolute -right-0.5 -top-0.5 h-2 w-2 rounded-full bg-emerald-500"
        />
      </button>

      <div class="flex items-center gap-2.5">
        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-emerald-100 text-sm font-semibold text-emerald-700">
          {{ initials(userName) }}
        </span>
        <span class="text-sm font-medium text-slate-700">{{ userName }}</span>
      </div>
    </div>
  </header>
</template>