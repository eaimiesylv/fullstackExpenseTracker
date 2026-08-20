<script setup lang="ts">
import ExpenseFormModal, { type ExpensePayload } from '~/components/ui/ExpenseFormModal.vue'
import ExpenseDetailModal from '~/components/ui/ExpenseDetailModal.vue'
import Modal from '~/components/ui/Modal.vue'
import Pagination from '~/components/ui/Pagination.vue'
import { useApi } from '~/composables/useApi'

definePageMeta({
  middleware: ['auth'],
  title: 'Expenses',
  subtitle: 'Track every expense across personal and group budgets.',
})

interface ExpenseCardItem {
  id: string
  title?: string
  name?: string
  amount: string | number
  expense_date?: string | null
  date?: string | null
  expense_type: 'personal' | 'group'
  status?: string
  description?: string
  category_id?: string
  category?: { id: string; category_name: string } | null
  group_id?: string
  group?: { id: string; group_name: string } | null
  budget_id?: string
  budget?: { id: string; budget_name: string; amount: number } | null
  is_owner?: boolean
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

const expenses = ref<ExpenseCardItem[]>([])
const loadingExpenses = ref(true)

// Pagination state
const currentPage = ref(1)
const lastPage = ref(1)
const totalExpenses = ref(0)
const perPage = ref(10)

// Filter state
const scopeFilter = ref<'all' | 'personal' | 'group'>('all')
const groupFilter = ref<string>('all')
const categoryFilter = ref<string>('all')
const searchQuery = ref('')

const userGroups = ref<GroupOption[]>([])
const categories = ref<CategoryOption[]>([])

// Detail & Delete modal state
const selectedExpenseId = ref<string | null>(null)
const showDetailModal = ref(false)

const showDeleteModal = ref(false)
const deletingExpenseItem = ref<ExpenseCardItem | null>(null)
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

async function fetchExpenses(page = 1) {
  loadingExpenses.value = true
  try {
    const api = useApi()
    const queryParams = new URLSearchParams()
    queryParams.set('page', String(page))
    queryParams.set('per_page', String(perPage.value))

    if (scopeFilter.value !== 'all') queryParams.set('scope', scopeFilter.value)
    if (groupFilter.value !== 'all') queryParams.set('group_id', groupFilter.value)
    if (categoryFilter.value !== 'all') queryParams.set('category_id', categoryFilter.value)
    if (searchQuery.value.trim()) queryParams.set('search', searchQuery.value.trim())

    const res: any = await api.get(`expenses?${queryParams.toString()}`)
    expenses.value = Array.isArray(res) ? res : (res?.data || [])

    if (res?.meta) {
      currentPage.value = res.meta.current_page || 1
      lastPage.value = res.meta.last_page || 1
      totalExpenses.value = res.meta.total || 0
      perPage.value = res.meta.per_page || 10
    }
  } catch (err) {
    console.error('Failed to fetch expenses:', err)
  } finally {
    loadingExpenses.value = false
  }
}

let searchDebounce: any = null
function onSearchInput() {
  clearTimeout(searchDebounce)
  searchDebounce = setTimeout(() => {
    fetchExpenses(1)
  }, 300)
}

watch([scopeFilter, groupFilter, categoryFilter], () => {
  fetchExpenses(1)
})

onMounted(() => {
  loadFilterOptions()
  fetchExpenses(1)
})

const totalSpentAmount = computed(() => {
  return expenses.value.reduce((sum, e) => sum + (Number(e.amount) || 0), 0)
})

const personalSpentAmount = computed(() => {
  return expenses.value
    .filter((e) => e.expense_type === 'personal')
    .reduce((sum, e) => sum + (Number(e.amount) || 0), 0)
})

const groupSpentAmount = computed(() => {
  return expenses.value
    .filter((e) => e.expense_type === 'group')
    .reduce((sum, e) => sum + (Number(e.amount) || 0), 0)
})

function openCreateModal() {
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
    await fetchExpenses(currentPage.value)
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

function openDetailModal(expense: ExpenseCardItem) {
  selectedExpenseId.value = expense.id
  showDetailModal.value = true
}

function promptDeleteExpense(expense: ExpenseCardItem) {
  deletingExpenseItem.value = expense
  showDeleteModal.value = true
}

async function confirmDeleteExpense() {
  if (!deletingExpenseItem.value) return
  deleting.value = true
  try {
    const api = useApi()
    await api.delete(`expenses/${deletingExpenseItem.value.id}`)
    showDeleteModal.value = false
    deletingExpenseItem.value = null
    await fetchExpenses(currentPage.value)
  } catch (err) {
    console.error('Failed to delete expense:', err)
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
</script>

<template>
  <div class="space-y-6">
    <!-- Header Action Bar -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h2 class="text-xl font-bold text-slate-900">Your Expenses</h2>
        <p class="text-sm text-slate-500">Track and manage every expense logged across your personal and group budgets.</p>
      </div>

      <button
        type="button"
        class="inline-flex items-center justify-center gap-2 rounded-full bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/30"
        @click="openCreateModal"
      >
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
          <path d="M12 5v14M5 12h14" />
        </svg>
        Add Expense
      </button>
    </div>

    <!-- Summary Stats Overview Bar -->
    <div v-if="!loadingExpenses && expenses.length > 0" class="grid grid-cols-1 gap-4 sm:grid-cols-4">
      <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
        <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Page Total Spent</span>
        <div class="mt-1 flex items-baseline justify-between">
          <span class="text-2xl font-bold text-slate-900">{{ formatCurrency(totalSpentAmount) }}</span>
          <span class="text-xs font-semibold text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full">{{ totalExpenses || expenses.length }} items</span>
        </div>
      </div>

      <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
        <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Personal Expenses</span>
        <div class="mt-1 flex items-baseline justify-between">
          <span class="text-2xl font-bold text-indigo-600">{{ formatCurrency(personalSpentAmount) }}</span>
          <span class="text-xs font-medium text-slate-500">Personal Scope</span>
        </div>
      </div>

      <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
        <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Group Expenses</span>
        <div class="mt-1 flex items-baseline justify-between">
          <span class="text-2xl font-bold text-purple-600">{{ formatCurrency(groupSpentAmount) }}</span>
          <span class="text-xs font-medium text-slate-500">Group Scope</span>
        </div>
      </div>

      <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
        <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Total Records</span>
        <div class="mt-1 flex items-baseline justify-between">
          <span class="text-2xl font-bold text-emerald-600">{{ totalExpenses || expenses.length }}</span>
          <span class="text-xs font-medium text-slate-500">Expenses</span>
        </div>
      </div>
    </div>

    <!-- Filters Toolbar (Scope, Group, Category, & Search) -->
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
            placeholder="Search expense title…"
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
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loadingExpenses" class="space-y-3">
      <div v-for="i in 5" :key="i" class="h-14 animate-pulse rounded-2xl border border-slate-100 bg-slate-50/50" />
    </div>

    <!-- Empty State -->
    <div
      v-else-if="totalExpenses === 0 && groupFilter === 'all' && scopeFilter === 'all' && categoryFilter === 'all' && !searchQuery"
      class="flex h-full min-h-[350px] flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 p-8 text-center"
    >
      <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-indigo-50 text-indigo-600">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
        </svg>
      </div>
      <h3 class="text-base font-semibold text-slate-900">No Expenses Recorded Yet</h3>
      <p class="mt-1 max-w-sm text-sm text-slate-500">
        Record an expense to track payments, link expenses to budgets, and monitor group spending.
      </p>
      <button
        type="button"
        class="mt-5 flex items-center gap-2 rounded-full bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
        @click="openCreateModal"
      >
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
          <path d="M12 5v14M5 12h14" />
        </svg>
        Add Expense
      </button>
    </div>

    <!-- Filter Empty State -->
    <div
      v-else-if="expenses.length === 0"
      class="flex min-h-[250px] flex-col items-center justify-center rounded-2xl border border-slate-100 bg-slate-50/50 p-6 text-center"
    >
      <p class="text-sm font-medium text-slate-600">No expenses match your selected filters or search query.</p>
      <button
        type="button"
        class="mt-3 text-xs font-semibold text-indigo-600 hover:text-indigo-700"
        @click="scopeFilter = 'all'; groupFilter = 'all'; categoryFilter = 'all'; searchQuery = ''; fetchExpenses(1)"
      >
        Reset filters
      </button>
    </div>

    <!-- High-Density Expenses Data Table -->
    <div v-else class="overflow-x-auto rounded-2xl border border-slate-200/80 bg-white shadow-xs">
      <table class="w-full text-left text-xs border-collapse">
        <thead class="bg-slate-50/80 font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200/80">
          <tr>
            <th scope="col" class="py-3.5 pl-4 pr-3">Item / Expense Title</th>
            <th scope="col" class="px-3 py-3.5">Amount (₦)</th>
            <th scope="col" class="px-3 py-3.5">Category</th>
            <th scope="col" class="px-3 py-3.5">Scope / Group</th>
            <th scope="col" class="px-3 py-3.5">Linked Budget</th>
            <th scope="col" class="px-3 py-3.5">Date</th>
            <th scope="col" class="py-3.5 pl-3 pr-4 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr
            v-for="expense in expenses"
            :key="expense.id"
            class="hover:bg-slate-50/70 transition"
          >
            <!-- Title & Description -->
            <td class="py-3.5 pl-4 pr-3">
              <div class="flex flex-col">
                <span class="font-bold text-slate-900 text-xs sm:text-sm">{{ expense.title || expense.name }}</span>
                <span v-if="expense.description" class="text-[11px] text-slate-400 line-clamp-1 truncate max-w-xs">
                  {{ expense.description }}
                </span>
              </div>
            </td>

            <!-- Amount -->
            <td class="px-3 py-3.5 font-bold text-slate-900 text-sm whitespace-nowrap">
              {{ formatCurrency(expense.amount) }}
            </td>

            <!-- Category -->
            <td class="px-3 py-3.5 whitespace-nowrap">
              <span
                v-if="expense.category"
                class="inline-block rounded-md bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 border border-emerald-200"
              >
                {{ expense.category.category_name }}
              </span>
              <span v-else class="text-slate-400 text-xs">-</span>
            </td>

            <!-- Scope / Group -->
            <td class="px-3 py-3.5 whitespace-nowrap">
              <span
                class="rounded-full px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide border"
                :class="expense.expense_type === 'group' ? 'bg-indigo-50 text-indigo-700 border-indigo-200' : 'bg-slate-100 text-slate-600 border-slate-200'"
                :title="expense.group?.group_name || ''"
              >
                {{ expense.expense_type === 'group' ? (expense.group?.group_name ? `Group: ${truncateGroup(expense.group.group_name)}` : 'Group') : 'Personal' }}
              </span>
            </td>

            <!-- Linked Budget -->
            <td class="px-3 py-3.5 whitespace-nowrap">
              <span v-if="expense.budget" class="inline-flex items-center gap-1 rounded-md bg-indigo-50 px-2 py-0.5 text-[11px] font-semibold text-indigo-700 border border-indigo-200">
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M21 12V7H5a2 2 0 0 1 0-4h14v4" />
                  <path d="M3 5v14a2 2 0 0 0 2 2h16v-5" />
                </svg>
                {{ expense.budget.budget_name }}
              </span>
              <span v-else class="text-slate-400 text-xs">-</span>
            </td>

            <!-- Date -->
            <td class="px-3 py-3.5 whitespace-nowrap text-slate-600 font-medium">
              {{ formatDate(expense.expense_date || expense.date) }}
            </td>

            <!-- Actions -->
            <td class="py-3.5 pl-3 pr-4 text-right whitespace-nowrap">
              <div class="flex items-center justify-end gap-1.5">
                <button
                  type="button"
                  class="rounded-lg p-1.5 text-slate-500 hover:bg-indigo-50 hover:text-indigo-600 transition"
                  title="View Details"
                  @click="openDetailModal(expense)"
                >
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z" />
                    <path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                  </svg>
                </button>

                <button
                  v-if="expense.is_owner"
                  type="button"
                  class="rounded-lg p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600 transition"
                  title="Delete Expense"
                  @click="promptDeleteExpense(expense)"
                >
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                  </svg>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Reusable Server-Side Pagination Bar -->
    <Pagination
      v-if="!loadingExpenses && lastPage > 1"
      v-model:current-page="currentPage"
      :last-page="lastPage"
      :total="totalExpenses"
      :per-page="perPage"
      :loading="loadingExpenses"
      @change="fetchExpenses"
    />

    <!-- Add Expense Modal -->
    <ExpenseFormModal
      v-model="showModal"
      :loading="creating"
      :server-message="errorMessage"
      :server-errors="fieldErrors"
      @submit="handleCreate"
    />

    <!-- Expense Detail Modal -->
    <ExpenseDetailModal
      v-model="showDetailModal"
      :expense-id="selectedExpenseId"
    />

    <!-- Delete Expense Confirmation Modal -->
    <Modal
      v-model="showDeleteModal"
      :z-index="70"
      title="Delete Expense"
      subtitle="Are you sure you want to delete this expense record?"
    >
      <div class="space-y-4">
        <p v-if="deletingExpenseItem" class="font-semibold text-slate-900">
          {{ deletingExpenseItem.title || deletingExpenseItem.name }} ({{ formatCurrency(deletingExpenseItem.amount) }})
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
            @click="confirmDeleteExpense"
          >
            {{ deleting ? 'Deleting…' : 'Delete Expense' }}
          </button>
        </div>
      </div>
    </Modal>
  </div>
</template>