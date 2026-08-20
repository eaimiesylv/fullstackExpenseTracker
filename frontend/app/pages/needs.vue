<script setup lang="ts">
import NeedFormModal, { type NeedPayload, type InitialNeedData } from '~/components/ui/NeedFormModal.vue'
import Modal from '~/components/ui/Modal.vue'
import Pagination from '~/components/ui/Pagination.vue'
import { useApi } from '~/composables/useApi'

definePageMeta({
  middleware: ['auth'],
  title: 'Needs',
  subtitle: 'Set limits, state your purposes, and track what you have left in tabular view.',
})

interface NeedItem {
  id: string
  name: string
  purpose?: string | null
  type: 'personal' | 'group'
  amount: string | number
  status: string
  start_date?: string | null
  end_date?: string | null
  category_id?: string
  category?: { id: string; category_name: string } | null
  item_id?: string
  item?: { id: string; name: string } | null
  group_id?: string
  group?: { id: string; group_name: string } | null
  allow_member_contribution?: boolean
}

interface GroupOption {
  id: string
  name: string
}

const showModal = ref(false)
const saving = ref(false)
const errorMessage = ref<string | null>(null)
const fieldErrors = ref<Record<string, string> | null>(null)

const editingNeed = ref<InitialNeedData | null>(null)

const showDeleteModal = ref(false)
const deletingNeed = ref<NeedItem | null>(null)
const deleting = ref(false)
const deleteErrorMessage = ref<string | null>(null)

// Read Purpose Modal state
const showPurposeModal = ref(false)
const viewingPurposeNeed = ref<NeedItem | null>(null)

const needs = ref<NeedItem[]>([])
const loadingNeeds = ref(true)

// Pagination state
const currentPage = ref(1)
const lastPage = ref(1)
const totalNeeds = ref(0)
const perPage = ref(10)

// Filters state
const typeFilter = ref<'all' | 'personal' | 'group'>('all')
const statusFilter = ref<'all' | 'pending' | 'completed' | 'expired' | 'closed'>('all')
const groupFilter = ref<string>('all')
const searchQuery = ref('')

const userGroups = ref<GroupOption[]>([])
const loadingUserGroups = ref(false)

async function loadUserGroups() {
  loadingUserGroups.value = true
  try {
    const api = useApi()
    const res: any = await api.get('groups/list')
    const list = Array.isArray(res) ? res : (res?.data || [])
    userGroups.value = list.map((g: any) => ({
      id: g.id,
      name: g.group_name || g.name,
    }))
  } catch (err) {
    console.error('Failed to load user groups:', err)
  } finally {
    loadingUserGroups.value = false
  }
}

async function fetchNeeds(page = 1) {
  loadingNeeds.value = true
  try {
    const api = useApi()
    const queryParams = new URLSearchParams()
    queryParams.set('page', String(page))
    queryParams.set('per_page', String(perPage.value))

    if (typeFilter.value !== 'all') queryParams.set('type', typeFilter.value)
    if (statusFilter.value !== 'all') queryParams.set('status', statusFilter.value)
    if (groupFilter.value !== 'all') queryParams.set('group_id', groupFilter.value)
    if (searchQuery.value.trim()) queryParams.set('search', searchQuery.value.trim())

    const res: any = await api.get(`needs?${queryParams.toString()}`)
    needs.value = Array.isArray(res) ? res : (res?.data || [])

    if (res?.meta) {
      currentPage.value = res.meta.current_page || 1
      lastPage.value = res.meta.last_page || 1
      totalNeeds.value = res.meta.total || 0
      perPage.value = res.meta.per_page || 10
    }
  } catch (error) {
    console.error('Failed to fetch needs:', error)
  } finally {
    loadingNeeds.value = false
  }
}

let searchDebounce: any = null
function onSearchInput() {
  clearTimeout(searchDebounce)
  searchDebounce = setTimeout(() => {
    fetchNeeds(1)
  }, 300)
}

watch([typeFilter, statusFilter, groupFilter], () => {
  fetchNeeds(1)
})

onMounted(() => {
  loadUserGroups()
  fetchNeeds(1)
})

const totalAmount = computed(() => {
  return needs.value.reduce((acc, n) => acc + (Number(n.amount) || 0), 0)
})

const personalAmount = computed(() => {
  return needs.value
    .filter((n) => n.type === 'personal')
    .reduce((acc, n) => acc + (Number(n.amount) || 0), 0)
})

const groupAmount = computed(() => {
  return needs.value
    .filter((n) => n.type === 'group')
    .reduce((acc, n) => acc + (Number(n.amount) || 0), 0)
})

function openCreateModal() {
  editingNeed.value = null
  errorMessage.value = null
  fieldErrors.value = null
  showModal.value = true
}

function openEditModal(need: NeedItem) {
  editingNeed.value = {
    id: need.id,
    name: need.name,
    purpose: need.purpose,
    itemId: need.item_id || need.item?.id,
    type: need.type,
    amount: need.amount,
    categoryId: need.category_id || need.category?.id,
    startDate: need.start_date,
    endDate: need.end_date,
    groupId: need.group_id || need.group?.id,
    allowMemberContribution: need.allow_member_contribution,
    status: need.status || 'pending',
  }
  errorMessage.value = null
  fieldErrors.value = null
  showModal.value = true
}

function openPurposeModal(need: NeedItem) {
  viewingPurposeNeed.value = need
  showPurposeModal.value = true
}

async function handleSave(payload: NeedPayload) {
  saving.value = true
  errorMessage.value = null
  fieldErrors.value = null

  try {
    const api = useApi()
    const targetId = payload.id || editingNeed.value?.id

    if (targetId) {
      await api.put(`needs/${targetId}`, payload)
    } else {
      await api.post('needs', payload)
    }

    showModal.value = false
    editingNeed.value = null
    await fetchNeeds(currentPage.value)
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
    saving.value = false
  }
}

function promptDelete(need: NeedItem) {
  deletingNeed.value = need
  deleteErrorMessage.value = null
  showDeleteModal.value = true
}

async function confirmDelete() {
  if (!deletingNeed.value) return
  deleting.value = true
  deleteErrorMessage.value = null

  try {
    const api = useApi()
    await api.delete(`needs/${deletingNeed.value.id}`)
    showDeleteModal.value = false
    deletingNeed.value = null
    await fetchNeeds(currentPage.value)
  } catch (err: any) {
    deleteErrorMessage.value = err?.message || 'Could not delete need. Please try again.'
  } finally {
    deleting.value = false
  }
}

async function updateNeedStatus(need: NeedItem, newStatus: string) {
  if (need.status === newStatus) return
  const oldStatus = need.status
  need.status = newStatus
  try {
    const api = useApi()
    await api.put(`needs/${need.id}`, { status: newStatus })
  } catch (err) {
    need.status = oldStatus
    console.error('Failed to update status:', err)
  }
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
    case 'close':
    case 'closed':
      return 'bg-slate-100 text-slate-700 border-slate-200'
    case 'pending':
    default:
      return 'bg-amber-50 text-amber-700 border-amber-200'
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
</script>

<template>
  <div class="space-y-6">
    <!-- Header Action Bar -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h2 class="text-xl font-bold text-slate-900">Your Needs</h2>
        <p class="text-sm text-slate-500">View and manage all essential personal and group needs in high-density table format.</p>
      </div>
      <button
        type="button"
        class="inline-flex items-center justify-center gap-2 rounded-full bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/30"
        @click="openCreateModal"
      >
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
          <path d="M12 5v14M5 12h14" />
        </svg>
        Create Needs
      </button>
    </div>

    <!-- Summary Stats Overview Bar -->
    <div v-if="!loadingNeeds && needs.length > 0" class="grid grid-cols-1 gap-4 sm:grid-cols-3">
      <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-xs">
        <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Page Total Amount</span>
        <div class="mt-1 flex items-baseline justify-between">
          <span class="text-2xl font-bold text-slate-900">{{ formatCurrency(totalAmount) }}</span>
          <span class="text-xs font-semibold text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full">{{ totalNeeds || needs.length }} items</span>
        </div>
      </div>
      <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-xs">
        <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Personal Needs</span>
        <div class="mt-1 flex items-baseline justify-between">
          <span class="text-2xl font-bold text-indigo-600">{{ formatCurrency(personalAmount) }}</span>
          <span class="text-xs font-medium text-slate-500">
            {{ needs.filter(n => n.type === 'personal').length }} items
          </span>
        </div>
      </div>
      <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-xs">
        <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Group Needs</span>
        <div class="mt-1 flex items-baseline justify-between">
          <span class="text-2xl font-bold text-emerald-600">{{ formatCurrency(groupAmount) }}</span>
          <span class="text-xs font-medium text-slate-500">
            {{ needs.filter(n => n.type === 'group').length }} items
          </span>
        </div>
      </div>
    </div>

    <!-- Toolbar Filters Bar -->
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-slate-50/80 p-2.5 border border-slate-100">
      <div class="flex items-center gap-2 flex-1 min-w-[200px]">
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
            placeholder="Search need name or purpose…"
            class="w-full rounded-xl border border-slate-200 bg-white py-1.5 pl-9 pr-3 text-xs text-slate-900 placeholder:text-slate-400 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"
            @input="onSearchInput"
          />
        </div>

        <div class="hidden sm:flex items-center gap-1 border-l border-slate-200 pl-2">
          <button
            type="button"
            class="rounded-xl px-3 py-1.5 text-xs font-semibold transition"
            :class="typeFilter === 'all' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-900'"
            @click="typeFilter = 'all'"
          >
            All Types
          </button>
          <button
            type="button"
            class="rounded-xl px-3 py-1.5 text-xs font-semibold transition"
            :class="typeFilter === 'personal' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-900'"
            @click="typeFilter = 'personal'"
          >
            Personal
          </button>
          <button
            type="button"
            class="rounded-xl px-3 py-1.5 text-xs font-semibold transition"
            :class="typeFilter === 'group' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-900'"
            @click="typeFilter = 'group'"
          >
            Group
          </button>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <select
          v-model="groupFilter"
          class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
        >
          <option value="all">All Belonged Groups</option>
          <option v-for="g in userGroups" :key="g.id" :value="g.id" :title="g.name">{{ truncateGroup(g.name) }}</option>
        </select>

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

    <!-- Loading Table State -->
    <div v-if="loadingNeeds" class="rounded-2xl border border-slate-100 bg-white p-6 shadow-xs">
      <div class="space-y-4">
        <div v-for="i in 5" :key="i" class="h-10 w-full animate-pulse rounded-xl bg-slate-100" />
      </div>
    </div>

    <!-- Empty Table State -->
    <div
      v-else-if="totalNeeds === 0 && groupFilter === 'all' && typeFilter === 'all' && statusFilter === 'all' && !searchQuery"
      class="flex h-full min-h-[350px] flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 p-8 text-center bg-white"
    >
      <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-indigo-50 text-indigo-600">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
        </svg>
      </div>
      <h3 class="text-base font-semibold text-slate-900">No Needs Recorded</h3>
      <p class="mt-1 max-w-sm text-sm text-slate-500">
        Create essential needs to set up limits, state their purpose, and monitor execution.
      </p>
      <button
        type="button"
        class="mt-5 flex items-center gap-2 rounded-full bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
        @click="openCreateModal"
      >
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
          <path d="M12 5v14M5 12h14" />
        </svg>
        Create Needs
      </button>
    </div>

    <!-- High-Density Data Table Layout -->
    <div v-else class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xs">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead class="border-b border-slate-200/80 bg-slate-50/70 text-slate-500 font-bold uppercase tracking-wider">
            <tr>
              <th scope="col" class="py-3.5 pl-5 pr-3">Need Item / Name</th>
              <th scope="col" class="px-3 py-3.5">Amount</th>
              <th scope="col" class="px-3 py-3.5">Category</th>
              <th scope="col" class="px-3 py-3.5">Scope / Type</th>
              <th scope="col" class="px-3 py-3.5">Date Limit</th>
              <th scope="col" class="px-3 py-3.5">Status</th>
              <th scope="col" class="px-3 py-3.5">Purpose</th>
              <th scope="col" class="py-3.5 pl-3 pr-5 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
            <tr
              v-for="need in needs"
              :key="need.id"
              class="transition hover:bg-slate-50/60"
            >
              <!-- Need Item / Name -->
              <td class="py-3.5 pl-5 pr-3">
                <div class="font-bold text-slate-900 text-xs sm:text-sm">{{ need.name }}</div>
                <div v-if="need.purpose" class="text-[11px] text-slate-400 line-clamp-1 max-w-xs mt-0.5">
                  {{ need.purpose }}
                </div>
              </td>

              <!-- Amount -->
              <td class="px-3 py-3.5 font-bold text-slate-900 whitespace-nowrap">
                {{ formatCurrency(need.amount) }}
              </td>

              <!-- Category -->
              <td class="px-3 py-3.5 whitespace-nowrap">
                <span
                  v-if="need.category"
                  class="inline-block rounded-md bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-700 border border-emerald-200"
                >
                  {{ need.category.category_name }}
                </span>
                <span v-else class="text-slate-400">—</span>
              </td>

              <!-- Scope / Type -->
              <td class="px-3 py-3.5 whitespace-nowrap">
                <span
                  class="inline-block rounded-full px-2.5 py-0.5 text-[11px] font-semibold border capitalize"
                  :class="need.type === 'group' ? 'bg-indigo-50 text-indigo-700 border-indigo-200' : 'bg-slate-100 text-slate-600 border-slate-200'"
                  :title="need.group?.group_name || ''"
                >
                  {{ need.type === 'group' ? (need.group?.group_name ? `Group: ${truncateGroup(need.group.group_name)}` : 'Group') : 'Personal' }}
                </span>
              </td>

              <!-- Date Limit -->
              <td class="px-3 py-3.5 whitespace-nowrap text-slate-500">
                <span v-if="need.start_date || need.end_date">
                  {{ formatDate(need.start_date) }} <span v-if="need.end_date">→ {{ formatDate(need.end_date) }}</span>
                </span>
                <span v-else class="text-slate-400">No date limit</span>
              </td>

              <!-- Status Interactive Selector -->
              <td class="px-3 py-3.5 whitespace-nowrap">
                <div class="relative inline-block">
                  <select
                    :value="need.status || 'pending'"
                    class="appearance-none rounded-full border px-3 py-1 pr-6 text-[11px] font-semibold cursor-pointer transition focus:outline-none focus:ring-2 focus:ring-indigo-500/20 capitalize"
                    :class="getStatusBadgeClass(need.status)"
                    @change="updateNeedStatus(need, ($event.target as HTMLSelectElement).value)"
                  >
                    <option value="pending" class="bg-white text-slate-900">Pending</option>
                    <option value="completed" class="bg-white text-slate-900">Completed</option>
                    <option value="expired" class="bg-white text-slate-900">Expired</option>
                    <option value="close" class="bg-white text-slate-900">Closed</option>
                  </select>
                  <div class="pointer-events-none absolute inset-y-0 right-2 flex items-center">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                      <path d="m6 9 6 6 6-6" />
                    </svg>
                  </div>
                </div>
              </td>

              <!-- Purpose / Read Purpose Link -->
              <td class="px-3 py-3.5 whitespace-nowrap">
                <button
                  v-if="need.purpose"
                  type="button"
                  class="inline-flex items-center gap-1 text-[11px] font-semibold text-indigo-600 hover:text-indigo-800 transition bg-indigo-50 px-2.5 py-1 rounded-lg border border-indigo-100"
                  @click="openPurposeModal(need)"
                >
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10" />
                    <line x1="12" y1="16" x2="12" y2="12" />
                    <line x1="12" y1="8" x2="12.01" y2="8" />
                  </svg>
                  Read Purpose
                </button>
                <span v-else class="text-slate-400">—</span>
              </td>

              <!-- Actions -->
              <td class="py-3.5 pl-3 pr-5 text-right whitespace-nowrap">
                <div class="flex items-center justify-end gap-1.5">
                  <button
                    type="button"
                    class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-indigo-600 transition"
                    title="Edit Need"
                    @click="openEditModal(need)"
                  >
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                      <path d="M12 20h9M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z" />
                    </svg>
                  </button>

                  <button
                    type="button"
                    class="rounded-lg p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600 transition"
                    title="Delete Need"
                    @click="promptDelete(need)"
                  >
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                      <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                    </svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Reusable Server-Side Pagination Bar -->
    <Pagination
      v-if="!loadingNeeds && lastPage > 1"
      v-model:current-page="currentPage"
      :last-page="lastPage"
      :total="totalNeeds"
      :per-page="perPage"
      :loading="loadingNeeds"
      @change="fetchNeeds"
    />

    <!-- Create / Edit Need Modal -->
    <NeedFormModal
      v-model="showModal"
      :loading="saving"
      :initial-data="editingNeed"
      :server-message="errorMessage"
      :server-errors="fieldErrors"
      @submit="handleSave"
    />

    <!-- Read Purpose Modal -->
    <Modal
      v-model="showPurposeModal"
      :title="viewingPurposeNeed?.name || 'Need Purpose'"
      subtitle="Stated purpose and justification for this need requirement."
    >
      <div class="space-y-4">
        <div v-if="viewingPurposeNeed" class="space-y-3">
          <div class="flex items-center justify-between rounded-xl bg-slate-50 p-3">
            <span class="text-xs font-semibold text-slate-500">Amount</span>
            <span class="text-base font-bold text-slate-900">{{ formatCurrency(viewingPurposeNeed.amount) }}</span>
          </div>

          <div>
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Purpose Statement</span>
            <p class="mt-1 text-sm text-slate-700 rounded-xl bg-indigo-50/50 border border-indigo-100 p-4 leading-relaxed whitespace-pre-wrap">
              {{ viewingPurposeNeed.purpose || 'No purpose specified for this need.' }}
            </p>
          </div>
        </div>

        <div class="flex justify-end border-t border-slate-100 pt-4">
          <button
            type="button"
            class="rounded-full bg-slate-100 px-5 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-200 transition"
            @click="showPurposeModal = false"
          >
            Close
          </button>
        </div>
      </div>
    </Modal>

    <!-- Delete Confirmation Modal -->
    <Modal
      v-model="showDeleteModal"
      title="Delete Need"
      subtitle="Are you sure you want to delete this need? This action cannot be undone."
    >
      <div class="space-y-4">
        <div v-if="deleteErrorMessage" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
          {{ deleteErrorMessage }}
        </div>

        <div v-if="deletingNeed" class="rounded-xl border border-slate-100 bg-slate-50 p-4">
          <p class="font-semibold text-slate-900">{{ deletingNeed.name }}</p>
          <p class="text-sm text-slate-500 mt-1">{{ formatCurrency(deletingNeed.amount) }}</p>
        </div>

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
            class="rounded-full bg-rose-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-70"
            @click="confirmDelete"
          >
            {{ deleting ? 'Deleting…' : 'Delete Need' }}
          </button>
        </div>
      </div>
    </Modal>
  </div>
</template>