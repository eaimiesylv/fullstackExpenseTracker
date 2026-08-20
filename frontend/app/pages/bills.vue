<script setup lang="ts">
import BillFormModal, { type BillPayload } from '~/components/bills/BillFormModal.vue'
import BillDetailModal from '~/components/bills/BillDetailModal.vue'
import RecordBillPaymentModal from '~/components/bills/RecordBillPaymentModal.vue'
import SendReminderModal from '~/components/bills/SendReminderModal.vue'
import Modal from '~/components/ui/Modal.vue'
import Pagination from '~/components/ui/Pagination.vue'
import { useApi } from '~/composables/useApi'

definePageMeta({
  middleware: ['auth'],
  title: 'Bills & Splits',
  subtitle: 'Track shared bills, who has paid, and who still owes.',
})

interface BillCardItem {
  id: string
  title?: string
  name?: string
  amount: string | number
  total_assigned?: number
  total_collected?: number
  total_outstanding?: number
  computed_status?: string
  status: string
  due_date?: string | null
  start_date?: string | null
  scope: 'personal' | 'group'
  split_type?: string
  allow_partial_payment?: boolean
  category_id?: string
  category?: { id: string; category_name: string } | null
  group_id?: string
  group?: { id: string; group_name: string } | null
  participants?: any[]
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

const bills = ref<BillCardItem[]>([])
const loadingBills = ref(true)

// Pagination state
const currentPage = ref(1)
const lastPage = ref(1)
const totalBills = ref(0)
const perPage = ref(9)

// Multi-parameter filter state
const scopeFilter = ref<'all' | 'personal' | 'group'>('all')
const groupFilter = ref<string>('all')
const categoryFilter = ref<string>('all')
const statusFilter = ref<'all' | 'full' | 'incomplete' | 'no_payment'>('all')
const startDateFilter = ref('')
const endDateFilter = ref('')
const searchQuery = ref('')

const userGroups = ref<GroupOption[]>([])
const categories = ref<CategoryOption[]>([])

// Modals state
const selectedBillId = ref<string | null>(null)
const selectedBillForAction = ref<any>(null)

const showDetailModal = ref(false)
const showRecordPaymentModal = ref(false)
const showSendReminderModal = ref(false)

const recordingPayment = ref(false)
const sendingReminder = ref(false)

const showDeleteModal = ref(false)
const deletingBillItem = ref<BillCardItem | null>(null)
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

async function fetchBills(page = 1) {
  loadingBills.value = true
  try {
    const api = useApi()
    const queryParams = new URLSearchParams()
    queryParams.set('page', String(page))
    queryParams.set('per_page', String(perPage.value))

    if (scopeFilter.value !== 'all') queryParams.set('scope', scopeFilter.value)
    if (groupFilter.value !== 'all') queryParams.set('group_id', groupFilter.value)
    if (categoryFilter.value !== 'all') queryParams.set('category_id', categoryFilter.value)
    if (statusFilter.value !== 'all') queryParams.set('status', statusFilter.value)
    if (startDateFilter.value) queryParams.set('start_date', startDateFilter.value)
    if (endDateFilter.value) queryParams.set('end_date', endDateFilter.value)
    if (searchQuery.value.trim()) queryParams.set('search', searchQuery.value.trim())

    const res: any = await api.get(`bills?${queryParams.toString()}`)
    bills.value = Array.isArray(res) ? res : (res?.data || [])

    if (res?.meta) {
      currentPage.value = res.meta.current_page || 1
      lastPage.value = res.meta.last_page || 1
      totalBills.value = res.meta.total || 0
      perPage.value = res.meta.per_page || 9
    }
  } catch (err) {
    console.error('Failed to fetch bills:', err)
  } finally {
    loadingBills.value = false
  }
}

let searchDebounce: any = null
function onSearchInput() {
  clearTimeout(searchDebounce)
  searchDebounce = setTimeout(() => {
    fetchBills(1)
  }, 300)
}

watch([scopeFilter, groupFilter, categoryFilter, statusFilter, startDateFilter, endDateFilter], () => {
  fetchBills(1)
})

onMounted(() => {
  loadFilterOptions()
  fetchBills(1)
})

const totalBillAmount = computed(() => {
  return bills.value.reduce((sum, b) => sum + (Number(b.total_assigned || b.amount) || 0), 0)
})

const totalCollectedAmount = computed(() => {
  return bills.value.reduce((sum, b) => sum + (Number(b.total_collected) || 0), 0)
})

const totalOutstandingAmount = computed(() => {
  return bills.value.reduce((sum, b) => sum + (Number(b.total_outstanding) || 0), 0)
})

function openCreateModal() {
  errorMessage.value = null
  fieldErrors.value = null
  showModal.value = true
}

async function handleCreate(payload: BillPayload) {
  creating.value = true
  errorMessage.value = null
  fieldErrors.value = null

  try {
    const api = useApi()
    await api.post('bills', payload)
    showModal.value = false
    await fetchBills(currentPage.value)
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

function openDetailModal(bill: BillCardItem) {
  selectedBillId.value = bill.id
  showDetailModal.value = true
}

async function openRecordPaymentModal(bill: BillCardItem) {
  selectedBillId.value = bill.id
  selectedBillForAction.value = bill
  showRecordPaymentModal.value = true

  // Fetch fresh bill details to ensure all registered participants are loaded
  try {
    const api = useApi()
    const res: any = await api.get(`bills/${bill.id}`)
    if (res?.data) {
      selectedBillForAction.value = res.data
    }
  } catch (err) {
    console.error('Failed to load fresh bill detail for payment:', err)
  }
}

async function handleRecordPayment(payload: any) {
  if (!selectedBillId.value) return
  recordingPayment.value = true
  try {
    const api = useApi()
    await api.post(`bills/${selectedBillId.value}/payments`, payload)
    showRecordPaymentModal.value = false
    await fetchBills(currentPage.value)
    if (showDetailModal.value) {
      selectedBillId.value = null
      nextTick(() => { selectedBillId.value = selectedBillForAction.value.id })
    }
  } catch (err: any) {
    console.error('Failed to record payment:', err)
  } finally {
    recordingPayment.value = false
  }
}

function openSendReminderModal(bill: BillCardItem) {
  selectedBillId.value = bill.id
  selectedBillForAction.value = bill
  showSendReminderModal.value = true
}

async function handleSendReminder(payload: any) {
  if (!selectedBillId.value) return
  sendingReminder.value = true
  try {
    const api = useApi()
    await api.post(`bills/${selectedBillId.value}/reminders`, payload)
    showSendReminderModal.value = false
    await fetchBills(currentPage.value)
  } catch (err: any) {
    console.error('Failed to send reminder:', err)
  } finally {
    sendingReminder.value = false
  }
}

function promptDeleteBill(bill: BillCardItem) {
  deletingBillItem.value = bill
  showDeleteModal.value = true
}

async function confirmDeleteBill() {
  if (!deletingBillItem.value) return
  deleting.value = true
  try {
    const api = useApi()
    await api.delete(`bills/${deletingBillItem.value.id}`)
    showDeleteModal.value = false
    deletingBillItem.value = null
    await fetchBills(currentPage.value)
  } catch (err) {
    console.error('Failed to delete bill:', err)
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

function getStatusBadgeClass(status?: string) {
  const st = (status || 'no_payment').toLowerCase()
  switch (st) {
    case 'full':
    case 'complete':
    case 'completed':
      return 'bg-emerald-50 text-emerald-700 border-emerald-200'
    case 'incomplete':
    case 'partial':
      return 'bg-amber-50 text-amber-700 border-amber-200'
    case 'no_payment':
    default:
      return 'bg-rose-50 text-rose-700 border-rose-200'
  }
}

function getStatusLabel(status?: string) {
  const st = (status || 'no_payment').toLowerCase()
  switch (st) {
    case 'full':
    case 'complete':
    case 'completed':
      return 'Completed'
    case 'incomplete':
    case 'partial':
      return 'Incomplete'
    case 'no_payment':
    default:
      return 'No Payment'
  }
}

function calculateProgress(collected?: number, assigned?: number) {
  const total = Number(assigned) || 0
  const done = Number(collected) || 0
  if (total <= 0) return 0
  return Math.min(100, Math.round((done / total) * 100))
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header Action Bar -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h2 class="text-xl font-bold text-slate-900">Bills & Shared Splits</h2>
        <p class="text-sm text-slate-500">Track recurring & one-off bills, split collections, and outstanding member payments.</p>
      </div>

      <button
        type="button"
        class="inline-flex items-center justify-center gap-2 rounded-full bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/30"
        @click="openCreateModal"
      >
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
          <path d="M12 5v14M5 12h14" />
        </svg>
        Create Bill
      </button>
    </div>

    <!-- Summary Stats Overview Bar -->
    <div v-if="!loadingBills && bills.length > 0" class="grid grid-cols-1 gap-4 sm:grid-cols-4">
      <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
        <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Page Total Bills</span>
        <div class="mt-1 flex items-baseline justify-between">
          <span class="text-2xl font-bold text-slate-900">{{ formatCurrency(totalBillAmount) }}</span>
          <span class="text-xs font-semibold text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full">{{ totalBills || bills.length }} bills</span>
        </div>
      </div>

      <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
        <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Total Collected</span>
        <div class="mt-1 flex items-baseline justify-between">
          <span class="text-2xl font-bold text-emerald-600">{{ formatCurrency(totalCollectedAmount) }}</span>
          <span class="text-xs font-medium text-slate-500">Paid Share</span>
        </div>
      </div>

      <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
        <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Total Outstanding</span>
        <div class="mt-1 flex items-baseline justify-between">
          <span class="text-2xl font-bold text-rose-600">{{ formatCurrency(totalOutstandingAmount) }}</span>
          <span class="text-xs font-medium text-slate-500">Remaining</span>
        </div>
      </div>

      <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
        <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Overall Records</span>
        <div class="mt-1 flex items-baseline justify-between">
          <span class="text-2xl font-bold text-indigo-600">{{ totalBills || bills.length }}</span>
          <span class="text-xs font-medium text-slate-500">Total Bills</span>
        </div>
      </div>
    </div>

    <!-- Multi-Parameter Filter Toolbar -->
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-slate-50/80 p-3 border border-slate-100">
      <div class="flex flex-wrap items-center gap-2 flex-1 min-w-[200px]">
        <div class="relative w-full sm:w-56">
          <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="11" cy="11" r="8" />
              <path d="m21 21-4.3-4.3" />
            </svg>
          </span>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search bill title…"
            class="w-full rounded-xl border border-slate-200 bg-white py-1.5 pl-9 pr-3 text-xs text-slate-900 placeholder:text-slate-400 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"
            @input="onSearchInput"
          />
        </div>

        <div class="hidden sm:flex items-center gap-1 border-l border-slate-200 pl-2">
          <button
            type="button"
            class="rounded-xl px-2.5 py-1.5 text-xs font-semibold transition"
            :class="scopeFilter === 'all' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-900'"
            @click="scopeFilter = 'all'"
          >
            All Scopes
          </button>
          <button
            type="button"
            class="rounded-xl px-2.5 py-1.5 text-xs font-semibold transition"
            :class="scopeFilter === 'personal' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-900'"
            @click="scopeFilter = 'personal'"
          >
            Personal
          </button>
          <button
            type="button"
            class="rounded-xl px-2.5 py-1.5 text-xs font-semibold transition"
            :class="scopeFilter === 'group' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-900'"
            @click="scopeFilter = 'group'"
          >
            Group
          </button>
        </div>
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <div class="flex items-center gap-1 text-xs text-slate-500">
          <span class="hidden md:inline">Start:</span>
          <input
            v-model="startDateFilter"
            type="date"
            class="rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
          />
        </div>

        <div class="flex items-center gap-1 text-xs text-slate-500">
          <span class="hidden md:inline">End:</span>
          <input
            v-model="endDateFilter"
            type="date"
            class="rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
          />
        </div>

        <select
          v-model="categoryFilter"
          class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
        >
          <option value="all">All Categories</option>
          <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>

        <select
          v-model="statusFilter"
          class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
        >
          <option value="all">All Status</option>
          <option value="full">Completed Only</option>
          <option value="incomplete">Incomplete Only</option>
          <option value="no_payment">No Payment Only</option>
        </select>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loadingBills" class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
      <div v-for="i in 3" :key="i" class="h-52 animate-pulse rounded-2xl border border-slate-100 bg-slate-50/50 p-5" />
    </div>

    <!-- Empty State -->
    <div
      v-else-if="totalBills === 0 && groupFilter === 'all' && scopeFilter === 'all' && categoryFilter === 'all' && statusFilter === 'all' && !startDateFilter && !endDateFilter && !searchQuery"
      class="flex h-full min-h-[350px] flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 p-8 text-center"
    >
      <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-indigo-50 text-indigo-600">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
          <polyline points="14 2 14 8 20 8" />
          <line x1="16" y1="13" x2="8" y2="13" />
          <line x1="16" y1="17" x2="8" y2="17" />
          <polyline points="10 9 9 9 8 9" />
        </svg>
      </div>
      <h3 class="text-base font-semibold text-slate-900">No Bills Created Yet</h3>
      <p class="mt-1 max-w-sm text-sm text-slate-500">
        Create a bill to track shared expenses, split amounts among group members, and monitor member payments.
      </p>
      <button
        type="button"
        class="mt-5 flex items-center gap-2 rounded-full bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
        @click="openCreateModal"
      >
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
          <path d="M12 5v14M5 12h14" />
        </svg>
        Create Bill
      </button>
    </div>

    <!-- Filter Empty State -->
    <div
      v-else-if="bills.length === 0"
      class="flex min-h-[250px] flex-col items-center justify-center rounded-2xl border border-slate-100 bg-slate-50/50 p-6 text-center"
    >
      <p class="text-sm font-medium text-slate-600">No bills match your selected filters, date range, or search query.</p>
      <button
        type="button"
        class="mt-3 text-xs font-semibold text-indigo-600 hover:text-indigo-700"
        @click="scopeFilter = 'all'; groupFilter = 'all'; categoryFilter = 'all'; statusFilter = 'all'; startDateFilter = ''; endDateFilter = ''; searchQuery = ''; fetchBills(1)"
      >
        Reset all filters
      </button>
    </div>

    <!-- Bill Cards Grid Layout -->
    <div v-else class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
      <div
        v-for="bill in bills"
        :key="bill.id"
        class="group relative flex flex-col justify-between rounded-2xl border border-slate-100 bg-white p-5 shadow-xs transition hover:border-slate-200 hover:shadow-md"
      >
        <div class="space-y-3">
          <div class="flex items-start justify-between gap-2">
            <div>
              <span
                v-if="bill.category"
                class="inline-block rounded-md bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 border border-emerald-200"
              >
                {{ bill.category.category_name }}
              </span>
              <h3 class="mt-1 text-base font-bold text-slate-900">{{ bill.title || bill.name }}</h3>
            </div>

            <span
              class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide border"
              :class="bill.scope === 'group' ? 'bg-indigo-50 text-indigo-700 border-indigo-200' : 'bg-slate-100 text-slate-600 border-slate-200'"
            >
              {{ bill.scope === 'group' ? (bill.group?.group_name ? `Group: ${bill.group.group_name}` : 'Group') : 'Personal' }}
            </span>
          </div>

          <div class="space-y-2 pt-1 border-t border-slate-50">
            <div class="flex items-baseline justify-between">
              <div>
                <span class="text-2xl font-bold text-slate-900">{{ formatCurrency(bill.total_assigned || bill.amount) }}</span>
                <span class="text-xs text-slate-400 block mt-0.5">Due: {{ formatDate(bill.due_date) }}</span>
              </div>

              <span
                class="rounded-full px-2.5 py-0.5 text-[10px] font-bold border capitalize"
                :class="getStatusBadgeClass(bill.computed_status || bill.status)"
              >
                {{ getStatusLabel(bill.computed_status || bill.status) }}
              </span>
            </div>

            <div class="space-y-1">
              <div class="flex justify-between text-[11px] font-medium text-slate-500">
                <span>Collected: <strong class="text-emerald-600">{{ formatCurrency(bill.total_collected || 0) }}</strong></span>
                <span>Outstanding: <strong class="text-rose-600">{{ formatCurrency(bill.total_outstanding || 0) }}</strong></span>
              </div>
              <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                <div
                  class="h-full rounded-full bg-emerald-500 transition-all duration-300"
                  :style="{ width: `${calculateProgress(bill.total_collected, bill.total_assigned || bill.amount)}%` }"
                />
              </div>
            </div>
          </div>
        </div>

        <div class="mt-4 border-t border-slate-100 pt-3">
          <div class="flex items-center justify-between gap-1.5">
            <button
              type="button"
              class="rounded-xl bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white shadow-xs transition hover:bg-emerald-700"
              @click="openRecordPaymentModal(bill)"
            >
              + Record Payment
            </button>

            <button
              type="button"
              class="rounded-xl bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100"
              @click="openSendReminderModal(bill)"
            >
              Remind
            </button>

            <button
              type="button"
              class="inline-flex items-center gap-1 text-xs font-semibold text-slate-600 hover:text-slate-900"
              @click="openDetailModal(bill)"
            >
              Details →
            </button>

            <button
              v-if="bill.is_owner"
              type="button"
              class="rounded-lg p-1 text-slate-400 hover:bg-rose-50 hover:text-rose-600 transition ml-auto"
              title="Delete Bill"
              @click="promptDeleteBill(bill)"
            >
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Reusable Server-Side Pagination Bar -->
    <Pagination
      v-if="!loadingBills && lastPage > 1"
      v-model:current-page="currentPage"
      :last-page="lastPage"
      :total="totalBills"
      :per-page="perPage"
      :loading="loadingBills"
      @change="fetchBills"
    />

    <!-- Add Bill Modal -->
    <BillFormModal
      v-model="showModal"
      :loading="creating"
      :server-message="errorMessage"
      :server-errors="fieldErrors"
      @submit="handleCreate"
    />

    <!-- Bill Detail Drawer Modal -->
    <BillDetailModal
      v-model="showDetailModal"
      :bill-id="selectedBillId"
      @record-payment="openRecordPaymentModal"
      @send-reminder="openSendReminderModal"
    />

    <!-- Record Payment Modal -->
    <RecordBillPaymentModal
      v-model="showRecordPaymentModal"
      :bill-id="selectedBillId"
      :bill-title="selectedBillForAction?.title || selectedBillForAction?.name"
      :allow-partial-payment="selectedBillForAction?.allow_partial_payment ?? true"
      :participants="selectedBillForAction?.participants || []"
      :loading="recordingPayment"
      @submit="handleRecordPayment"
    />

    <!-- Send Reminder Modal -->
    <SendReminderModal
      v-model="showSendReminderModal"
      :bill-id="selectedBillId"
      :bill-title="selectedBillForAction?.title || selectedBillForAction?.name"
      :loading="sendingReminder"
      @submit="handleSendReminder"
    />

    <!-- Delete Bill Confirmation Modal -->
    <Modal
      v-model="showDeleteModal"
      :z-index="70"
      title="Delete Bill"
      subtitle="Are you sure you want to delete this bill?"
    >
      <div class="space-y-4">
        <p v-if="deletingBillItem" class="font-semibold text-slate-900">
          {{ deletingBillItem.title || deletingBillItem.name }} ({{ formatCurrency(deletingBillItem.total_assigned || deletingBillItem.amount) }})
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
            @click="confirmDeleteBill"
          >
            {{ deleting ? 'Deleting…' : 'Delete Bill' }}
          </button>
        </div>
      </div>
    </Modal>
  </div>
</template>