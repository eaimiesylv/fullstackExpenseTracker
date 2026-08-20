<script setup lang="ts">
import { useAuthStore } from '~/stores/auth'

interface NavItem {
  label: string
  to: string
  icon: string
  action?: () => void
}

const authStore = useAuthStore()
const isLoggingOut = ref(false)

const navItems: NavItem[] = [
  { label: 'Dashboard', to: '/dashboard', icon: 'home' },
 
  { label: 'Groups', to: '/groups', icon: 'users' },
  { label: 'Needs', to: '/needs', icon: 'piggy' },
  { label: 'Budgets', to: '/budgets', icon: 'wallet' },
  { label: 'Expenses', to: '/expenses', icon: 'dollar' },
  { label: 'Shared Bills', to: '/bills', icon: 'receipt' },
 
  {
    label: 'Logout',
    to: '#',
    icon: 'logout',
    action: async () => {
      if (isLoggingOut.value) return
      isLoggingOut.value = true
      try {
        await authStore.logoutApi()
      } finally {
        isLoggingOut.value = false
      }
    },
  },
]

async function handleNavAction(item: NavItem) {
  if (item.action) {
    await item.action()
  }
}
</script>

<template>
  <aside class="flex h-screen w-64 shrink-0 flex-col border-r border-slate-200 bg-white">
    <div class="flex items-center gap-2 px-6 py-6">
      <span class="flex h-8 w-8 items-center justify-center rounded-lg" style="background: var(--emerald)">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
          <path d="M4 4h16M4 12h10M4 20h16" stroke="white" stroke-width="2.4" stroke-linecap="round" />
        </svg>
      </span>
      <span class="font-display text-lg font-bold tracking-tight text-slate-900">LedgerFlow</span>
    </div>

    <nav class="flex-1 space-y-1 px-3">
      <NuxtLink
        v-for="item in navItems"
        :key="item.to"
        :to="item.to"
        class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50 hover:text-slate-900"
        active-class="!bg-emerald-50 !text-emerald-700"
        @click.prevent="item.action ? handleNavAction(item) : undefined"
      >
        <svg v-if="item.icon !== 'logout' || !isLoggingOut" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="shrink-0">
          <path v-if="item.icon === 'home'" d="M3 11l9-7 9 7M5 10v9a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1v-9" />
          <path v-else-if="item.icon === 'chart'" d="M4 20V10M12 20V4M20 20v-7" />
          <path v-else-if="item.icon === 'wallet'" d="M3 7a2 2 0 0 1 2-2h13a1 1 0 0 1 1 1v3M3 7v10a2 2 0 0 0 2 2h14a1 1 0 0 0 1-1v-4M17 12h.01" />
          <path v-else-if="item.icon === 'dollar'" d="M12 2v20M17 6.5c0-1.9-2.2-3.5-5-3.5s-5 1.6-5 3.5 2.2 3 5 3.5 5 1.6 5 3.5-2.2 3.5-5 3.5-5-1.6-5-3.5" />
          <path v-else-if="item.icon === 'receipt'" d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Z M8 8h8M8 12h8M8 16h5" />
          <path v-else-if="item.icon === 'users'" d="M17 20v-1a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v1M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM23 20v-1a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
          <path v-else-if="item.icon === 'piggy'" d="M11 5a5 5 0 0 1 5 5v.2a3 3 0 0 1 2 2.8v1l-2 .5v1.5a1 1 0 0 1-1 1h-1v1H9v-1H7l-1-2H4.5a1.5 1.5 0 0 1 0-3H5a5 5 0 0 1 5-5M8 8h.01" />
          <path v-else-if="item.icon === 'bell'" d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0" />
          <path v-else-if="item.icon === 'gear'" d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9c.36.36.86.6 1.51.6H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z" />
          <path v-else-if="item.icon === 'logout'" d="M10 17l-1.4-1.4 2.6-2.6H3v-2h8.2l-2.6-2.6L10 7l5 5-5 5Zm8-10h-2V5h2a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-2v-2h2V7h-2Z" />
        </svg>
        <span v-else class="h-4 w-4 shrink-0 animate-spin rounded-full border-2 border-slate-300 border-t-emerald-600"></span>
        {{ item.label }}
      </NuxtLink>
    </nav>
  </aside>
</template>