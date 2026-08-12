<script setup lang="ts">
import FormModal, { type FormField } from '~/components/ui/FormModal.vue'

interface HeaderAction {
  label: string
  endpoint: string
  fields: FormField[]
}

const route = useRoute()

const title = computed(() => (route.meta.title as string) || 'Dashboard')
const subtitle = computed(() => (route.meta.subtitle as string) || '')
const headerAction = computed(() => route.meta.headerAction as HeaderAction | undefined)

const showModal = ref(false)
const creating = ref(false)

function openModal() {
  showModal.value = true
}

async function handleCreate(values: Record<string, string>) {
  if (!headerAction.value) return

  creating.value = true
  try {
    // TODO: replace with your real create call, e.g.
    // await $fetch(headerAction.value.endpoint, { method: 'POST', body: values })
    await new Promise((resolve) => setTimeout(resolve, 800))
    showModal.value = false
  } finally {
    creating.value = false
  }
}
</script>

<template>
  <div class="flex h-screen bg-slate-50">
    <DashboardSidebar />

    <div class="flex flex-1 flex-col overflow-hidden">
      <DashboardHeader
        :title="title"
        :subtitle="subtitle"
        user-name="Okom Emmanuel"
        :has-notifications="true"
        :action-label="headerAction?.label ?? ''"
        @action-click="openModal"
      />

      <main class="flex-1 overflow-y-auto p-8">
        <slot />
      </main>
    </div>

    <FormModal
      v-if="headerAction"
      v-model="showModal"
      :title="headerAction.label"
      :fields="headerAction.fields"
      :loading="creating"
      @submit="handleCreate"
    />
  </div>
</template>s