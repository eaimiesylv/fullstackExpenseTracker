<script setup lang="ts">
import DashboardHeader from '~/components/dashboard/DashboardHeader.vue'
import DashboardSidebar from '~/components/dashboard/DashboardSidebar.vue'

const route = useRoute()

const title = computed(() => (route.meta.title as string) || 'Dashboard')
const subtitle = computed(() => (route.meta.subtitle as string) || '')
</script>

<template>
  <div class="flex h-screen bg-slate-50">
    <DashboardSidebar />

    <div class="flex flex-1 flex-col overflow-hidden">
      <DashboardHeader :title="title" :subtitle="subtitle" user-name="Okom Emmanuel" :has-notifications="true" />

      <main class="flex-1 overflow-y-auto p-8">
        <slot />
      </main>
    </div>
  </div>
</template>