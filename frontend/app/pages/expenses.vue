<script setup lang="ts">
import ExpenseFormModal, { type ExpensePayload } from '~/components/ui/ExpenseFormModal.vue'
import { useApi } from '~/composables/useApi'

definePageMeta({
  middleware: ['auth'],
  title: 'Expenses',
  subtitle: 'Track every expense across your budgets.',
  headerAction: {
    label: 'Add Expense',
  },
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

async function handleCreate(payload: ExpensePayload) {
  creating.value = true
  errorMessage.value = null
  fieldErrors.value = null

  try {
    const api = useApi()
    await api.post('expenses', payload)
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
  <div class="space-y-6">
    <div class="flex justify-end">
      <button
        type="button"
        class="flex items-center gap-2 rounded-full bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
        @click="openModal"
      >
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
          <path d="M12 5v14M5 12h14" />
        </svg>
        Add Expense
      </button>
    </div>

    <div class="flex h-full min-h-[400px] items-center justify-center rounded-2xl border border-dashed border-slate-200">
      <p class="text-slate-400">Expenses content goes here.</p>
    </div>

    <ExpenseFormModal
      v-model="showModal"
      :loading="creating"
      :server-message="errorMessage"
      :server-errors="fieldErrors"
      @submit="handleCreate"
    />
  </div>
</template>