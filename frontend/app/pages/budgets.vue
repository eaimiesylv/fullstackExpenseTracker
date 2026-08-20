<script setup lang="ts">
import BudgetFormModal, { type BudgetPayload } from '~/components/ui/BudgetFormModal.vue'
import BudgetDetailModal from '~/components/ui/BudgetDetailModal.vue'
import AddContributionModal from '~/components/ui/AddContributionModal.vue'
import Modal from '~/components/ui/Modal.vue'
import Pagination from '~/components/ui/Pagination.vue'
import { useApi } from '~/composables/useApi'

definePageMeta({
  middleware: ['auth'],
  title: 'Budgets',
  subtitle: 'Set limits, track spendings, and monitor member contributions.',
})

interface BudgetCardItem {
  id: string
  name?: string
  budget_name?: string
  scope: 'personal' | 'group'
  amount: string | number
  status: string
  start_date?: string | null
  end_date?: string | null
  category_id?: string
  category?: { id: string; category_name: string } | null
  group_id?: string
  group?: { id: string; group_name: string } | null
  allow_member_contribution?: boolean
  track_contributions?: boolean
  total_spent?: number
  total_contributed?: number
  spending_threshold?: {
    spent: number
    percentage: number
    status: string
    label: string
    badge_class: string
  }
  contribution_percentage?: number
}

interface GroupOption {
  id: string
  name: string
}

interface CategoryOption {
  id: string
  name: string
}

const showModal = ref(false)
const creating = ref(false)
const errorMessage = ref<string | null>(null)
const fieldErrors = ref<Record<string, string> | null>(null)

const budgets = ref<BudgetCardItem[]>([])
const loadingBudgets = ref(true)

// Pagination state
const currentPage = ref(1)
const lastPage = ref(1)
const totalBudgets = ref(0)
const perPage = ref(9)

// Filter state
const scopeFilter = ref<'all' | 'personal' | 'group'>('all')
const groupFilter = ref<string>('all')
const categoryFilter = ref<string>('all')
const statusFilter = ref<'all' | 'pending' | 'completed' | 'expired' | 'closed'>('all')
const searchQuery = ref('')

const userGroups = ref<GroupOption[]>([])
const categories = ref<CategoryOption[]>([])

// Detail & Contribution modals state
const selectedBudgetId = ref<string | null>(null)
const showDetailModal = ref(false)

const contribBudgetId = ref<string | null>(null)
const contribBudgetName = ref<string>('')
const contribGroupId = ref<string | null>(null)
const showContribModal = ref(false)

const showDeleteModal = ref(false)
const deletingBudgetItem = ref<BudgetCardItem | null>(null)
const deleting = ref(false)

async function loadFilterOptions() {
  try {
    const api = useApi()
    const [groupsRes, categoriesRes]: any = await Promise.all([
      api.get('groups/list'),
      api.get('categories/all'),
    ])

    const groupList = Array.isArray(groupsRes) ? groupsRes : (groupsRes?.data || [])
    userGroups.value = groupList.map((g: any) => ({
      id: g.id,
      name: g.group_name || g.name,
    }))

    const catList = Array.isArray(categoriesRes) ? categoriesRes : (categoriesRes?.data || [])
    categories.value = catList.map((c: any) => ({
      id: c.id,
      name: c.category_name || c.name,
    }))
  } catch (err) {
    console.error('Failed to load filter options:', err)
  }
}

async function fetchBudgets(page = 1) {
  loadingBudgets.value = true
  try {
    const api = useApi()
    const queryParams = new URLSearchParams()
    queryParams.set('page', String(page))
    queryParams.set('per_page', String(perPage.value))

    if (scopeFilter.value !== 'all') queryParams.set('scope', scopeFilter.value)
    if (groupFilter.value !== 'all') queryParams.set('group_id', groupFilter.value)
    if (categoryFilter.value !== 'all') queryParams.set('category_id', categoryFilter.value)
    if (statusFilter.value !== 'all') queryParams.set('status', statusFilter.value)
    if (searchQuery.value.trim()) queryParams.set('search', searchQuery.value.trim())

    const res: any = await api.get(`budgets?${queryParams.toString()}`)
    budgets.value = Array.isArray(res) ? res : (res?.data || [])

    if (res?.meta) {
      currentPage.value = res.meta.current_page || 1
      lastPage.value = res.meta.last_page || 1
      totalBudgets.value = res.meta.total || 0
      perPage.value = res.meta.per_page || 9
    }
  } catch (err) {
    console.error('Failed to fetch budgets:', err)
  } finally {
    loadingBudgets.value = false
  }
}

let searchDebounce: any = null
function onSearchInput() {
  clearTimeout(searchDebounce)
  searchDebounce = setTimeout(() => {
    fetchBudgets(1)
  }, 300)
}

watch([scopeFilter, groupFilter, categoryFilter, statusFilter], () => {
  fetchNeedsOrBudgets()
})

function fetchNeedsOrBudgets() {
  fetchBudgets(1)
}

onMounted(() => {
  loadFilterOptions()
  fetchBudgets(1)
})

const totalBudgetedAmount = computed(() => {
  return budgets.value.reduce((sum, b) => sum + (Number(b.amount) || 0), 0)
})

const totalSpentAmount = computed(() => {
  return budgets.value.reduce((sum, b) => sum + (Number(b.total_spent) || 0), 0)
})

const totalContributedAmount = computed(() => {
  return budgets.value.reduce((sum, b) => sum + (Number(b.total_contributed) || 0), 0)
})

function openCreateModal() {
  errorMessage.value = null
  fieldErrors.value = null
  showModal.value = true
}

async function handleCreate(payload: BudgetPayload) {
  creating.value = true
  errorMessage.value = null
  fieldErrors.value = null

  try {
    const api = useApi()
    await api.post('budgets', payload)
    showModal.value = false
    await fetchBudgets(currentPage.value)
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

function openDetailModal(budget: BudgetCardItem) {
  selectedBudgetId.value = budget.id
  showDetailModal.value = true
}

function openAddContributionModal(budget: BudgetCardItem) {
  contribBudgetId.value = budget.id
  contribBudgetName.value = budget.name || budget.budget_name || ''
  contribGroupId.value = budget.group_id || null
  showContribModal.value = true
}

function promptDeleteBudget(budget: BudgetCardItem) {
  deletingBudgetItem.value = budget
  showDeleteModal.value = true
}

async function confirmDeleteBudget() {
  if (!deletingBudgetItem.value) return
  deleting.value = true
  try {
    const api = useApi()
    await api.delete(`budgets/${deletingBudgetItem.value.id}`)
    showDeleteModal.value = false
    deletingBudgetItem.value = null
    await fetchBudgets(currentPage.value)
  } catch (err) {
    console.error('Failed to delete budget:', err)
  } finally {
    deleting.value = false
  }
}

function formatCurrency(val: string | number) {
  const num = Number(val) || 0
  return new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN' }).format(num)
}

function formatDate(dateStr?: string | null) {
  if (!dateStr) return ''
  return new Date(dateStr).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

function truncateGroup(name?: string, max = 20) {
  if (!name) return ''
  return name.length > max ? name.slice(0, max) + '…' : name
}

function getStatusBadgeClass(status?: string) {
  const st = (status || 'pending').toLowerCase()
  switch (st) {
    case 'completed':
      return 'bg-emerald-50 text-emerald-700 border-emerald-200'
    case 'expired':
      return 'bg-rose-50 text-rose-700 border-rose-200'
    case 'closed':
    case 'close':
      return 'bg-slate-100 text-slate-700 border-slate-200'
    case 'pending':
    default:
      return 'bg-amber-50 text-amber-700 border-amber-200'
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header Action Bar -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h2 class="text-xl font-bold text-slate-900">Your Budgets</h2>
        <p class="text-sm text-slate-500">Set limits, monitor spending thresholds, and track member contributions.</p>
      </div>

      <button
        type="button"
        class="inline-flex items-center justify-center gap-2 rounded-full bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/30"
        @click="openCreateModal"
      >
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
          <path d="M12 5v14M5 12h14" />
        </svg>
        Create Budget
      </button>
    </div>

    <!-- Summary Stats Overview Bar -->
    <div v-if="!loadingBudgets && budgets.length > 0" class="grid grid-cols-1 gap-4 sm:grid-cols-4">
      <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
        <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Page Budget Target</span>
        <div class="mt-1 flex items-baseline justify-between">
          <span class="text-2xl font-bold text-slate-900">{{ formatCurrency(totalBudgetedAmount) }}</span>
          <span class="text-xs font-semibold text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full">{{ totalBudgets || budgets.length }} items</span>
        </div>
      </div>

      <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
        <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Total Spent</span>
        <div class="mt-1 flex items-baseline justify-between">
          <span class="text-2xl font-bold text-indigo-600">{{ formatCurrency(totalSpentAmount) }}</span>
          <span class="text-xs font-medium text-slate-500">Linked expenses</span>
        </div>
      </div>

      <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
        <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Contributions Raised</span>
        <div class="mt-1 flex items-baseline justify-between">
          <span class="text-2xl font-bold text-emerald-600">{{ formatCurrency(totalContributedAmount) }}</span>
          <span class="text-xs font-medium text-slate-500">Members & Guests</span>
        </div>
      </div>

      <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
        <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Budget Status</span>
        <div class="mt-1 flex items-baseline justify-between">
          <span class="text-2xl font-bold text-amber-600">Active</span>
          <span class="text-xs font-medium text-slate-500">Auto-expires past end date</span>
        </div>
      </div>
    </div>

    <!-- Filters Toolbar (Scope, Group, Category, Status, & Search) -->
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-slate-50/80 p-3 border border-slate-100">
      <div class="flex items-center gap-1.5 flex-1 min-w-[200px]">
        <div class="relative w-full sm:w-64">
          <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="11" cy="11" r="8" />
              <path d="m21 21-4.3-4.3" />
            </svg>
          </span>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search budget name…"
            class="w-full rounded-xl border border-slate-200 bg-white py-1.5 pl-9 pr-3 text-xs text-slate-900 placeholder:text-slate-400 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"
            @input="onSearchInput"
          />
        </div>

        <div class="hidden sm:flex items-center gap-1 border-l border-slate-200 pl-3">
          <button
            type="button"
            class="rounded-xl px-3 py-1.5 text-xs font-semibold transition"
            :class="scopeFilter === 'all' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-900'"
            @click="scopeFilter = 'all'"
          >
            All Scopes
          </button>
          <button
            type="button"
            class="rounded-xl px-3 py-1.5 text-xs font-semibold transition"
            :class="scopeFilter === 'personal' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-900'"
            @click="scopeFilter = 'personal'"
          >
            Personal
          </button>
          <button
            type="button"
            class="rounded-xl px-3 py-1.5 text-xs font-semibold transition"
            :class="scopeFilter === 'group' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-900'"
            @click="scopeFilter = 'group'"
          >
            Group
          </button>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <!-- Group Filter Dropdown -->
        <select
          v-model="groupFilter"
          class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
        >
          <option value="all">All Groups</option>
          <option v-for="g in userGroups" :key="g.id" :value="g.id" :title="g.name">{{ truncateGroup(g.name) }}</option>
        </select>

        <!-- Category Filter Dropdown -->
        <select
          v-model="categoryFilter"
          class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
        >
          <option value="all">All Categories</option>
          <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>

        <!-- Status Filter Dropdown -->
        <select
          v-model="statusFilter"
          class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
        >
          <option value="all">All Status</option>
          <option value="pending">Pending Only</option>
          <option value="completed">Completed Only</option>
          <option value="expired">Expired Only</option>
          <option value="closed">Closed Only</option>
        </select>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loadingBudgets" class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
      <div v-for="i in 3" :key="i" class="h-56 animate-pulse rounded-2xl border border-slate-100 bg-slate-50/50 p-5" />
    </div>

    <!-- Empty State -->
    <div
      v-else-if="totalBudgets === 0 && groupFilter === 'all' && scopeFilter === 'all' && categoryFilter === 'all' && statusFilter === 'all' && !searchQuery"
      class="flex h-full min-h-[350px] flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 p-8 text-center"
    >
      <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-indigo-50 text-indigo-600">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <path d="M21 12V7H5a2 2 0 0 1 0-4h14v4" />
          <path d="M3 5v14a2 2 0 0 0 2 2h16v-5" />
          <path d="M18 12a2 2 0 0 0 0 4h4v-4z" />
        </svg>
      </div>
      <h3 class="text-base font-semibold text-slate-900">No Budgets Created Yet</h3>
      <p class="mt-1 max-w-sm text-sm text-slate-500">
        Create a budget to set limits, track expenses, and manage contributions with members.
      </p>
      <button
        type="button"
        class="mt-5 flex items-center gap-2 rounded-full bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
        @click="openCreateModal"
      >
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
          <path d="M12 5v14M5 12h14" />
        </svg>
        Create Budget
      </button>
    </div>

    <!-- Filter Empty State -->
    <div
      v-else-if="budgets.length === 0"
      class="flex min-h-[250px] flex-col items-center justify-center rounded-2xl border border-slate-100 bg-slate-50/50 p-6 text-center"
    >
      <p class="text-sm font-medium text-slate-600">No budgets match your selected filters or search query.</p>
      <button
        type="button"
        class="mt-3 text-xs font-semibold text-indigo-600 hover:text-indigo-700"
        @click="scopeFilter = 'all'; groupFilter = 'all'; categoryFilter = 'all'; statusFilter = 'all'; searchQuery = ''; fetchBudgets(1)"
      >
        Reset filters
      </button>
    </div>

    <!-- Budgets Grid -->
    <div v-else class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
      <div
        v-for="budget in budgets"
        :key="budget.id"
        class="group relative flex flex-col justify-between rounded-2xl border border-slate-100 bg-white p-5 shadow-sm transition hover:border-slate-200 hover:shadow-md"
      >
        <div class="space-y-3">
          <!-- Top Row: Name, Scope, Category, & Spending Threshold Badge -->
          <div>
            <div class="flex items-start justify-between gap-2">
              <div>
                <span
                  v-if="budget.category"
                  class="inline-block rounded-md bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 border border-emerald-200"
                >
                  {{ budget.category.category_name }}
                </span>
                <h3 class="mt-1 text-base font-bold text-slate-900">{{ budget.name || budget.budget_name }}</h3>
              </div>

              <!-- Scope Tag -->
              <span
                class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide border"
                :class="budget.scope === 'group' ? 'bg-indigo-50 text-indigo-700 border-indigo-200' : 'bg-slate-100 text-slate-600 border-slate-200'"
                :title="budget.group?.group_name || ''"
              >
                {{ budget.scope === 'group' ? (budget.group?.group_name ? `Group: ${truncateGroup(budget.group.group_name)}` : 'Group') : 'Personal' }}
              </span>
            </div>
          </div>

          <!-- Budget Amount & Spending Threshold Indicator -->
          <div class="space-y-1.5 pt-1 border-t border-slate-50">
            <div class="flex items-baseline justify-between">
              <span class="text-2xl font-bold text-slate-900">{{ formatCurrency(budget.amount) }}</span>
              <span
                v-if="budget.spending_threshold"
                class="rounded-full px-2.5 py-0.5 text-[10px] font-bold border"
                :class="budget.spending_threshold.badge_class"
              >
                {{ budget.spending_threshold.label }}
              </span>
            </div>

            <!-- Spending Progress Bar (Target vs Spent) -->
            <div class="space-y-1">
              <div class="flex items-center justify-between text-[11px] text-slate-500">
                <span>Spent: {{ formatCurrency(budget.total_spent || 0) }}</span>
                <span>{{ budget.spending_threshold?.percentage || 0 }}%</span>
              </div>
              <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                <div
                  class="h-full transition-all duration-500"
                  :class="[
                    (budget.spending_threshold?.percentage || 0) >= 100
                      ? 'bg-rose-600'
                      : (budget.spending_threshold?.percentage || 0) >= 90
                        ? 'bg-orange-500'
                        : (budget.spending_threshold?.percentage || 0) >= 80
                          ? 'bg-amber-500'
                          : 'bg-emerald-500'
                  ]"
                  :style="{ width: `${Math.min(100, budget.spending_threshold?.percentage || 0)}%` }"
                />
              </div>
            </div>

            <!-- Contribution Progress Bar (if enabled) -->
            <div v-if="budget.allow_member_contribution || budget.track_contributions" class="space-y-1 pt-1.5">
              <div class="flex items-center justify-between text-[11px] text-indigo-700">
                <span>Contributions Raised: {{ formatCurrency(budget.total_contributed || 0) }}</span>
                <span class="font-bold">{{ budget.contribution_percentage || 0 }}%</span>
              </div>
              <div class="h-1.5 w-full overflow-hidden rounded-full bg-indigo-100">
                <div
                  class="h-full bg-indigo-600 transition-all duration-500"
                  :style="{ width: `${budget.contribution_percentage || 0}%` }"
                />
              </div>
            </div>
          </div>
        </div>

        <div class="mt-4 border-t border-slate-100 pt-3">
          <div class="flex items-center justify-between text-xs text-slate-500">
            <span>{{ formatDate(budget.start_date) }} <span v-if="budget.end_date">→ {{ formatDate(budget.end_date) }}</span></span>
            <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold border capitalize" :class="getStatusBadgeClass(budget.status)">
              {{ budget.status || 'Pending' }}
            </span>
          </div>

          <!-- Bottom Action Buttons -->
          <div class="mt-3 flex items-center justify-between border-t border-slate-50 pt-2">
            <button
              type="button"
              class="inline-flex items-center gap-1 text-xs font-semibold text-indigo-600 hover:text-indigo-700"
              @click="openDetailModal(budget)"
            >
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z" />
                <path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
              View Details
            </button>

            <div class="flex items-center gap-1">
              <button
                v-if="budget.allow_member_contribution || budget.track_contributions"
                type="button"
                class="rounded-lg bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 transition"
                title="Add Contribution"
                @click="openAddContributionModal(budget)"
              >
                + Contribute
              </button>

              <button
                type="button"
                class="rounded-lg p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600 transition"
                title="Delete Budget"
                @click="promptDeleteBudget(budget)"
              >
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                </svg>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Reusable Server-Side Pagination Bar -->
    <Pagination
      v-if="!loadingBudgets && lastPage > 1"
      v-model:current-page="currentPage"
      :last-page="lastPage"
      :total="totalBudgets"
      :per-page="perPage"
      :loading="loadingBudgets"
      @change="fetchBudgets"
    />

    <!-- Create Budget Modal -->
    <BudgetFormModal
      v-model="showModal"
      :loading="creating"
      :server-message="errorMessage"
      :server-errors="fieldErrors"
      @submit="handleCreate"
    />

    <!-- Budget Detail Drawer / Modal -->
    <BudgetDetailModal
      v-model="showDetailModal"
      :budget-id="selectedBudgetId"
      @updated="fetchBudgets(currentPage)"
    />

    <!-- Add Contribution Sub-Modal -->
    <AddContributionModal
      v-if="contribBudgetId"
      v-model="showContribModal"
      :budget-id="contribBudgetId"
      :budget-name="contribBudgetName"
      :group-id="contribGroupId"
      @saved="fetchBudgets(currentPage)"
    />

    <!-- Delete Budget Confirmation Modal -->
    <Modal
      v-model="showDeleteModal"
      :z-index="70"
      title="Delete Budget"
      subtitle="Are you sure you want to delete this budget? All related sub-items will be removed."
    >
      <div class="space-y-4">
        <p v-if="deletingBudgetItem" class="font-semibold text-slate-900">
          {{ deletingBudgetItem.name || deletingBudgetItem.budget_name }}
        </p>
        <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
          <button
            type="button"
            class="rounded-full px-5 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50"
            @click="showDeleteModal = false"
          >
            Cancel
          </button>
          <button
            type="button"
            :disabled="deleting"
            class="rounded-full bg-rose-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-700 disabled:opacity-70"
            @click="confirmDeleteBudget"
          >
            {{ deleting ? 'Deleting…' : 'Delete Budget' }}
          </button>
        </div>
      </div>
    </Modal>
  </div>
</template>