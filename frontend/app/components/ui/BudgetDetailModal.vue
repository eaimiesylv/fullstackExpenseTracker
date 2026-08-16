<script setup lang="ts">
import Modal from '~/components/ui/Modal.vue'
import AddContributionModal from '~/components/ui/AddContributionModal.vue'
import { useApi } from '~/composables/useApi'

interface Props {
  budgetId: string | null
}

const props = defineProps<Props>()
const isOpen = defineModel<boolean>({ default: false })

const emit = defineEmits<{
  updated: []
}>()

const budgetDetail = ref<any>(null)
const loading = ref(true)
const showAddContribModal = ref(false)

async function fetchBudgetDetails() {
  if (!props.budgetId) return
  loading.value = true
  try {
    const api = useApi()
    const res: any = await api.get(`budgets/${props.budgetId}`)
    budgetDetail.value = res?.data || res
  } catch (err) {
    console.error('Failed to load budget details:', err)
  } finally {
    loading.value = false
  }
}

watch(() => props.budgetId, (id) => {
  if (id && isOpen.value) fetchBudgetDetails()
})

watch(isOpen, (open) => {
  if (open && props.budgetId) fetchBudgetDetails()
})

function formatCurrency(val: string | number) {
  const num = Number(val) || 0
  return new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN' }).format(num)
}

function formatDate(dateStr?: string | null) {
  if (!dateStr) return 'N/A'
  return new Date(dateStr).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

function handleContributionSaved() {
  fetchBudgetDetails()
  emit('updated')
}
</script>

<template>
  <Modal
    v-model="isOpen"
    :z-index="50"
    max-width="max-w-3xl"
    :title="budgetDetail ? budgetDetail.name || budgetDetail.budget_name : 'Budget Details'"
    subtitle="View budget progress, contributor breakdown, line items, and linked expenses."
  >
    <div v-if="loading" class="space-y-4 py-6">
      <div class="h-20 animate-pulse rounded-2xl bg-slate-50" />
      <div class="h-32 animate-pulse rounded-2xl bg-slate-50" />
    </div>

    <div v-else-if="budgetDetail" class="space-y-6">
      <!-- Header Info & Spending Threshold Status Badge -->
      <div class="rounded-2xl border border-slate-100 bg-slate-50/60 p-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <div class="flex items-center gap-2">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">
              {{ budgetDetail.scope === 'group' ? (budgetDetail.group?.group_name ? `Group: ${budgetDetail.group.group_name}` : 'Group Budget') : 'Personal Budget' }}
            </span>
            <span v-if="budgetDetail.category" class="rounded-md bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">
              {{ budgetDetail.category.category_name }}
            </span>
          </div>
          <p class="mt-1 text-2xl font-bold text-slate-900">{{ formatCurrency(budgetDetail.amount) }}</p>
          <p class="text-xs text-slate-500 mt-0.5">
            Duration: {{ formatDate(budgetDetail.start_date) }} → {{ formatDate(budgetDetail.end_date) }}
          </p>
        </div>

        <div class="flex flex-col items-start sm:items-end gap-1.5">
          <!-- Spending Threshold Badge -->
          <span
            v-if="budgetDetail.spending_threshold"
            class="rounded-full px-3 py-1 text-xs font-bold border"
            :class="budgetDetail.spending_threshold.badge_class"
          >
            {{ budgetDetail.spending_threshold.label }}
          </span>

          <span class="text-xs font-medium text-slate-400 capitalize">
            Status: <strong class="text-slate-700">{{ budgetDetail.status || 'Pending' }}</strong>
          </span>
        </div>
      </div>

      <!-- Spending Progress Bar (Target vs Spent) -->
      <div class="rounded-2xl border border-slate-100 bg-white p-4 space-y-2">
        <div class="flex items-center justify-between text-xs">
          <span class="font-semibold text-slate-700">Spending Against Budget Target</span>
          <span class="font-bold text-slate-900">
            {{ formatCurrency(budgetDetail.total_spent || 0) }} / {{ formatCurrency(budgetDetail.amount) }}
          </span>
        </div>

        <div class="h-3 w-full overflow-hidden rounded-full bg-slate-100">
          <div
            class="h-full transition-all duration-500"
            :class="[
              (budgetDetail.spending_threshold?.percentage || 0) >= 100
                ? 'bg-rose-600'
                : (budgetDetail.spending_threshold?.percentage || 0) >= 90
                  ? 'bg-orange-500'
                  : (budgetDetail.spending_threshold?.percentage || 0) >= 80
                    ? 'bg-amber-500'
                    : 'bg-emerald-500'
            ]"
            :style="{ width: `${Math.min(100, budgetDetail.spending_threshold?.percentage || 0)}%` }"
          />
        </div>
      </div>

      <!-- Member / Guest Contribution Section (if track_contributions enabled) -->
      <div v-if="budgetDetail.allow_member_contribution || budgetDetail.track_contributions" class="rounded-2xl border border-indigo-100 bg-indigo-50/30 p-4 space-y-3">
        <div class="flex items-center justify-between">
          <div>
            <h4 class="text-sm font-bold text-indigo-950 flex items-center gap-1.5">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
              </svg>
              Contribution Progress & Raised Money
            </h4>
            <p class="text-xs text-indigo-700 mt-0.5">Tracked contributions from group members and guest contributors.</p>
          </div>

          <button
            type="button"
            class="inline-flex items-center gap-1 rounded-full bg-indigo-600 px-3.5 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700 transition"
            @click="showAddContribModal = true"
          >
            + Add Contribution
          </button>
        </div>

        <!-- Progress Bar -->
        <div class="space-y-1">
          <div class="flex items-center justify-between text-xs">
            <span class="font-medium text-indigo-900">Total Raised</span>
            <span class="font-bold text-indigo-950">
              {{ formatCurrency(budgetDetail.total_contributed || 0) }} ({{ budgetDetail.contribution_percentage || 0 }}%)
            </span>
          </div>
          <div class="h-2.5 w-full overflow-hidden rounded-full bg-indigo-100">
            <div
              class="h-full bg-indigo-600 transition-all duration-500"
              :style="{ width: `${budgetDetail.contribution_percentage || 0}%` }"
            />
          </div>
        </div>

        <!-- Breakdown of Contributors List -->
        <div class="border-t border-indigo-100/80 pt-3 space-y-2">
          <span class="text-xs font-bold text-indigo-900 uppercase tracking-wider">Contributors Breakdown</span>

          <div v-if="!budgetDetail.contributors || budgetDetail.contributors.length === 0" class="text-xs text-indigo-500 italic py-2">
            No contributions recorded yet. Click "+ Add Contribution" above to log the first contribution.
          </div>

          <div v-else class="space-y-2 max-h-44 overflow-y-auto pr-1">
            <div
              v-for="c in budgetDetail.contributors"
              :key="c.id"
              class="flex items-center justify-between rounded-xl bg-white p-2.5 border border-indigo-100 text-xs shadow-2xs"
            >
              <div class="flex items-center gap-2">
                <div class="flex h-7 w-7 items-center justify-center rounded-full bg-indigo-100 font-bold text-indigo-800 text-xs">
                  {{ c.name.charAt(0).toUpperCase() }}
                </div>
                <div>
                  <div class="flex items-center gap-1.5">
                    <span class="font-semibold text-slate-900">{{ c.name }}</span>
                    <span
                      class="rounded-full px-1.5 py-0.2 text-[9px] font-bold"
                      :class="c.is_guest ? 'bg-amber-100 text-amber-800' : 'bg-indigo-100 text-indigo-800'"
                    >
                      {{ c.is_guest ? 'Guest' : 'Member' }}
                    </span>
                  </div>
                  <span class="text-[11px] text-slate-400">{{ formatDate(c.contribution_date) }} <span v-if="c.notes">• {{ c.notes }}</span></span>
                </div>
              </div>

              <span class="font-bold text-emerald-700 text-sm">+{{ formatCurrency(c.amount) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Sub-Items Breakdown Section -->
      <div v-if="budgetDetail.items && budgetDetail.items.length > 0" class="rounded-2xl border border-slate-100 bg-white p-4 space-y-2">
        <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Budget Sub-Items Lineup</h4>
        <div class="space-y-1.5">
          <div
            v-for="item in budgetDetail.items"
            :key="item.id"
            class="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2 text-xs"
          >
            <span class="font-medium text-slate-800">{{ item.name }}</span>
            <span class="font-semibold text-slate-900">{{ formatCurrency(item.amount) }}</span>
          </div>
        </div>
      </div>

      <!-- Linked Expenses Section -->
      <div class="rounded-2xl border border-slate-100 bg-white p-4 space-y-2">
        <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Linked Expenses</h4>

        <div v-if="!budgetDetail.expenses || budgetDetail.expenses.length === 0" class="text-xs text-slate-400 italic py-2">
          No expenses linked to this budget yet.
        </div>

        <div v-else class="space-y-1.5 max-h-40 overflow-y-auto">
          <div
            v-for="exp in budgetDetail.expenses"
            :key="exp.id"
            class="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2 text-xs"
          >
            <div>
              <p class="font-semibold text-slate-900">{{ exp.title }}</p>
              <p class="text-[11px] text-slate-400">{{ formatDate(exp.expense_date) }}</p>
            </div>
            <span class="font-bold text-rose-600">-{{ formatCurrency(exp.amount) }}</span>
          </div>
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

      <!-- Add Contribution Sub-Modal -->
      <AddContributionModal
        v-if="budgetDetail"
        v-model="showAddContribModal"
        :budget-id="budgetDetail.id"
        :budget-name="budgetDetail.name || budgetDetail.budget_name"
        :group-id="budgetDetail.group_id"
        @saved="handleContributionSaved"
      />
    </div>
  </Modal>
</template>
