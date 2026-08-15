<script setup lang="ts">
import { computed, ref } from 'vue'
import FormModal, { type FormField } from '~/components/ui/FormModal.vue'
import { useApi } from '~/composables/useApi'

interface HeaderAction {
  label: string
  endpoint?: string
  fields?: FormField[]
}

const route = useRoute()

const title = computed(() => (route.meta.title as string) || 'Dashboard')
const subtitle = computed(() => (route.meta.subtitle as string) || '')
const headerAction = computed(() => route.meta.headerAction as HeaderAction | undefined)

const isGenericAction = computed(() => {
  return !!(headerAction.value?.endpoint && headerAction.value?.fields && headerAction.value.fields.length > 0)
})

const showModal = ref(false)
const creating = ref(false)
const errorMessage = ref<string | null>(null)
const fieldErrors = ref<Record<string, string> | null>(null)

function openModal() {
  errorMessage.value = null
  fieldErrors.value = null
  showModal.value = true
}

async function handleCreate(values: Record<string, string>) {
  if (!headerAction.value?.endpoint) return

  creating.value = true
  errorMessage.value = null
  fieldErrors.value = null

  try {
    const api = useApi()
    const endpoint = headerAction.value.endpoint.replace(/^\/?api\/?/, '').replace(/^\//, '')
    await api.post(endpoint, values)
    showModal.value = false
  } catch (error: any) {
    const apiError = error || {}
    errorMessage.value = apiError.message || 'Something went wrong. Please try again.'

    const errors = apiError.errors
    if (errors && typeof errors === 'object') {
      fieldErrors.value = Object.fromEntries(
        Object.entries(errors).map(([key, value]) => [
          key,
          Array.isArray(value) ? String(value[0]) : String(value),
        ])
      )
    }
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
        :action-label="isGenericAction ? (headerAction?.label ?? '') : ''"
        @action-click="openModal"
      />

      <main class="flex-1 overflow-y-auto p-8">
        <slot />
      </main>
    </div>

    <FormModal
      v-if="isGenericAction && headerAction && headerAction.fields"
      v-model="showModal"
      :title="headerAction.label"
      :fields="headerAction.fields"
      :loading="creating"
      :serverMessage="errorMessage"
      :serverErrors="fieldErrors"
      @submit="handleCreate"
    />
  </div>
</template>