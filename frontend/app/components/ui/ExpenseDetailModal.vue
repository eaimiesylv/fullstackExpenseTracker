<script setup lang="ts">
import Modal from '~/components/ui/Modal.vue'
import { useApi } from '~/composables/useApi'

interface Props {
  expenseId: string | null
}

const props = defineProps<Props>()
const isOpen = defineModel<boolean>({ default: false })

const expenseDetail = ref<any>(null)
const loading = ref(true)

async function fetchExpenseDetails() {
  if (!props.expenseId) return
  loading.value = true
  try {
    const api = useApi()
    const res: any = await api.get(`expenses/${props.expenseId}`)
    expenseDetail.value = res?.data || res
  } catch (err) {
    console.error('Failed to load expense details:', err)
  } finally {
    loading.value = false
  }
}

watch(() => props.expenseId, (id) => {
  if (id && isOpen.value) fetchExpenseDetails()
})

watch(isOpen, (open) => {
  if (open && props.expenseId) fetchExpenseDetails()
})

function formatCurrency(val: string | number) {
  const num = Number(val) || 0
  return new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN' }).format(num)
}

function formatDate(dateStr?: string | null) {
  if (!dateStr) return 'N/A'
  return new Date(dateStr).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}
</script>

<template>
  <Modal
    v-model="isOpen"
    :z-index="50"
    max-width="max-w-xl"
    :title="expenseDetail ? expenseDetail.title || expenseDetail.name : 'Expense Details'"
    subtitle="View expense breakdown, category, and budget links."
  >
    <div v-if="loading" class="space-y-4 py-6">
      <div class="h-20 animate-pulse rounded-2xl bg-slate-50" />
      <div class="h-28 animate-pulse rounded-2xl bg-slate-50" />
    </div>

    <div v-else-if="expenseDetail" class="space-y-5">
      <!-- Header Card with Amount -->
      <div class="rounded-2xl border border-slate-100 bg-slate-50/60 p-4 flex items-center justify-between">
        <div>
          <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">
            {{ expenseDetail.expense_type === 'group' ? (expenseDetail.group?.group_name ? `Group: ${expenseDetail.group.group_name}` : 'Group Expense') : 'Personal Expense' }}
          </span>
          <p class="mt-1 text-2xl font-bold text-slate-900">{{ formatCurrency(expenseDetail.amount) }}</p>
          <p class="text-xs text-slate-500 mt-0.5">Date: {{ formatDate(expenseDetail.expense_date) }}</p>
        </div>

        <div class="flex flex-col items-end gap-1.5">
          <span v-if="expenseDetail.category" class="rounded-md bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 border border-emerald-200">
            {{ expenseDetail.category.category_name }}
          </span>
        </div>
      </div>

      <!-- Info Breakdown Grid -->
      <div class="rounded-2xl border border-slate-100 bg-white p-4 space-y-3">
        <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Expense Details</h4>

        <div class="grid grid-cols-2 gap-3 text-xs">
          <div>
            <span class="text-slate-400">Created By</span>
            <p class="font-semibold text-slate-800 mt-0.5">{{ expenseDetail.user?.fullname || 'You' }}</p>
          </div>

          <div>
            <span class="text-slate-400">Linked Budget</span>
            <p class="font-semibold text-slate-800 mt-0.5">
              {{ expenseDetail.budget ? expenseDetail.budget.budget_name : 'Standalone Expense' }}
            </p>
          </div>

          <div>
            <span class="text-slate-400">Currency</span>
            <p class="font-semibold text-slate-800 mt-0.5">{{ expenseDetail.currency || 'NGN' }}</p>
          </div>

          <div>
            <span class="text-slate-400">Scope / Type</span>
            <p class="font-semibold text-slate-800 mt-0.5 capitalize">{{ expenseDetail.expense_type || 'Personal' }}</p>
          </div>
        </div>

        <!-- Description if present -->
        <div v-if="expenseDetail.description" class="border-t border-slate-100 pt-3">
          <span class="text-xs text-slate-400">Description</span>
          <p class="text-xs text-slate-700 mt-1 rounded-xl bg-slate-50 p-3 leading-relaxed">
            {{ expenseDetail.description }}
          </p>
        </div>
      </div>

      <div class="flex justify-end border-t border-slate-100 pt-4">
        <button
          type="button"
          class="rounded-full bg-slate-100 px-5 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-200 transition"
          @click="isOpen = false"
        >
          Close
        </button>
      </div>
    </div>
  </Modal>
</template>
