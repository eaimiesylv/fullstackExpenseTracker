<script setup lang="ts">
import NeedFormModal, { type NeedPayload, type InitialNeedData } from '~/components/ui/NeedFormModal.vue'
import Modal from '~/components/ui/Modal.vue'
import { useApi } from '~/composables/useApi'

definePageMeta({
  middleware: ['auth'],
  title: 'Needs',
  subtitle: 'Set limits and track what you have left.',
})

interface NeedItem {
  id: string
  name: string
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

const showModal = ref(false)
const saving = ref(false)
const errorMessage = ref<string | null>(null)
const fieldErrors = ref<Record<string, string> | null>(null)

const editingNeed = ref<InitialNeedData | null>(null)

const showDeleteModal = ref(false)
const deletingNeed = ref<NeedItem | null>(null)
const deleting = ref(false)
const deleteErrorMessage = ref<string | null>(null)

const needs = ref<NeedItem[]>([])
const loadingNeeds = ref(true)

const typeFilter = ref<'all' | 'personal' | 'group'>('all')
const statusFilter = ref<'all' | 'pending' | 'completed' | 'expired' | 'closed'>('all')

async function fetchNeeds() {
  loadingNeeds.value = true
  try {
    const api = useApi()
    const res: any = await api.get('needs')
    needs.value = Array.isArray(res) ? res : (res?.data || [])
  } catch (error) {
    console.error('Failed to fetch needs:', error)
  } finally {
    loadingNeeds.value = false
  }
}

onMounted(() => {
  fetchNeeds()
})

const filteredNeeds = computed(() => {
  return needs.value.filter((n) => {
    if (typeFilter.value !== 'all' && n.type !== typeFilter.value) {
      return false
    }
    if (statusFilter.value !== 'all') {
      const st = (n.status || 'pending').toLowerCase()
      if (statusFilter.value === 'closed') {
        if (st !== 'closed' && st !== 'close') return false
      } else if (st !== statusFilter.value) {
        return false
      }
    }
    return true
  })
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
    await fetchNeeds()
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
    await fetchNeeds()
  } catch (err: any) {
    deleteErrorMessage.value = err?.message || 'Could not delete need. Please try again.'
  } finally {
    deleting.value = false
  }
}

async function updateNeedStatus(need: NeedItem, newStatus: string) {
  if (need.status === newStatus) return
  const oldStatus = need.status
  need.status = newStatus // optimistic update
  try {
    const api = useApi()
    await api.put(`needs/${need.id}`, { status: newStatus })
  } catch (err) {
    need.status = oldStatus // revert on failure
    console.error('Failed to update status:', err)
  }
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

function formatStatusLabel(status?: string) {
  const st = (status || 'pending').toLowerCase()
  if (st === 'close') return 'Closed'
  return st.charAt(0).toUpperCase() + st.slice(1)
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
        <p class="text-sm text-slate-500">Track and manage essential personal and group requirements.</p>
      </div>
      <button
        type="button"
        class="inline-flex items-center justify-center gap-2 rounded-full bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/30"
        @click="openCreateModal"
      >
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
          <path d="M12 5v14M5 12h14" />
        </svg>
        Create Need
      </button>
    </div>

    <!-- Summary Stats Bar -->
    <div v-if="!loadingNeeds && needs.length > 0" class="grid grid-cols-1 gap-4 sm:grid-cols-3">
      <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
        <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Total Needs</span>
        <div class="mt-1 flex items-baseline justify-between">
          <span class="text-2xl font-bold text-slate-900">{{ formatCurrency(totalAmount) }}</span>
          <span class="text-xs font-semibold text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full">{{ needs.length }} items</span>
        </div>
      </div>
      <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
        <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Personal</span>
        <div class="mt-1 flex items-baseline justify-between">
          <span class="text-2xl font-bold text-indigo-600">{{ formatCurrency(personalAmount) }}</span>
          <span class="text-xs font-medium text-slate-500">
            {{ needs.filter(n => n.type === 'personal').length }} items
          </span>
        </div>
      </div>
      <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
        <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Group</span>
        <div class="mt-1 flex items-baseline justify-between">
          <span class="text-2xl font-bold text-emerald-600">{{ formatCurrency(groupAmount) }}</span>
          <span class="text-xs font-medium text-slate-500">
            {{ needs.filter(n => n.type === 'group').length }} items
          </span>
        </div>
      </div>
    </div>

    <!-- Filters Toolbar -->
    <div v-if="!loadingNeeds && needs.length > 0" class="flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-slate-50/80 p-2 border border-slate-100">
      <div class="flex items-center gap-1">
        <button
          type="button"
          class="rounded-xl px-3 py-1.5 text-xs font-semibold transition"
          :class="typeFilter === 'all' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-900'"
          @click="typeFilter = 'all'"
        >
          All Types
        </button>
        <button
          type="button"
          class="rounded-xl px-3 py-1.5 text-xs font-semibold transition"
          :class="typeFilter === 'personal' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-900'"
          @click="typeFilter = 'personal'"
        >
          Personal
        </button>
        <button
          type="button"
          class="rounded-xl px-3 py-1.5 text-xs font-semibold transition"
          :class="typeFilter === 'group' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-900'"
          @click="typeFilter = 'group'"
        >
          Group
        </button>
      </div>

      <div class="flex items-center gap-1 border-l border-slate-200 pl-3">
        <button
          type="button"
          class="rounded-xl px-3 py-1.5 text-xs font-semibold transition"
          :class="statusFilter === 'all' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-900'"
          @click="statusFilter = 'all'"
        >
          All Status
        </button>
        <button
          type="button"
          class="rounded-xl px-3 py-1.5 text-xs font-semibold transition"
          :class="statusFilter === 'pending' ? 'bg-white text-amber-700 shadow-sm' : 'text-slate-500 hover:text-slate-900'"
          @click="statusFilter = 'pending'"
        >
          Pending
        </button>
        <button
          type="button"
          class="rounded-xl px-3 py-1.5 text-xs font-semibold transition"
          :class="statusFilter === 'completed' ? 'bg-white text-emerald-700 shadow-sm' : 'text-slate-500 hover:text-slate-900'"
          @click="statusFilter = 'completed'"
        >
          Completed
        </button>
        <button
          type="button"
          class="rounded-xl px-3 py-1.5 text-xs font-semibold transition"
          :class="statusFilter === 'expired' ? 'bg-white text-rose-700 shadow-sm' : 'text-slate-500 hover:text-slate-900'"
          @click="statusFilter = 'expired'"
        >
          Expired
        </button>
        <button
          type="button"
          class="rounded-xl px-3 py-1.5 text-xs font-semibold transition"
          :class="statusFilter === 'closed' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-900'"
          @click="statusFilter = 'closed'"
        >
          Closed
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loadingNeeds" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <div v-for="i in 3" :key="i" class="h-48 animate-pulse rounded-2xl border border-slate-100 bg-slate-50/50 p-5" />
    </div>

    <!-- Empty State -->
    <div
      v-else-if="needs.length === 0"
      class="flex h-full min-h-[350px] flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 p-8 text-center"
    >
      <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-indigo-50 text-indigo-600">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
        </svg>
      </div>
      <h3 class="text-base font-semibold text-slate-900">No Needs Yet</h3>
      <p class="mt-1 max-w-sm text-sm text-slate-500">
        Create your first need to set up limits and monitor your upcoming essential expenses.
      </p>
      <button
        type="button"
        class="mt-5 flex items-center gap-2 rounded-full bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
        @click="openCreateModal"
      >
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
          <path d="M12 5v14M5 12h14" />
        </svg>
        Create Need
      </button>
    </div>

    <!-- Filter Empty State -->
    <div
      v-else-if="filteredNeeds.length === 0"
      class="flex min-h-[250px] flex-col items-center justify-center rounded-2xl border border-slate-100 bg-slate-50/50 p-6 text-center"
    >
      <p class="text-sm font-medium text-slate-600">No needs match your selected filters.</p>
      <button
        type="button"
        class="mt-3 text-xs font-semibold text-indigo-600 hover:text-indigo-700"
        @click="typeFilter = 'all'; statusFilter = 'all'"
      >
        Reset filters
      </button>
    </div>

    <!-- Needs List Grid -->
    <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <div
        v-for="need in filteredNeeds"
        :key="need.id"
        class="group relative flex flex-col justify-between rounded-2xl border border-slate-100 bg-white p-5 shadow-sm transition hover:border-slate-200 hover:shadow-md"
      >
        <div>
          <div class="flex items-start justify-between gap-2">
            <div>
              <span
                v-if="need.category"
                class="inline-block rounded-lg bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700"
              >
                {{ need.category.category_name }}
              </span>
              <h3 class="mt-2 text-base font-semibold text-slate-900">{{ need.name }}</h3>
            </div>

            <div class="flex items-center gap-1">
              <span
                class="capitalize rounded-full px-2.5 py-0.5 text-xs font-medium"
                :class="need.type === 'group' ? 'bg-indigo-50 text-indigo-700' : 'bg-slate-100 text-slate-600'"
              >
                {{ need.type === 'group' ? (need.group?.group_name ? `Group: ${need.group.group_name}` : 'Group') : 'Personal' }}
              </span>
            </div>
          </div>

          <div class="mt-4 flex items-baseline justify-between">
            <span class="text-2xl font-bold text-slate-900">{{ formatCurrency(need.amount) }}</span>
          </div>
        </div>

        <div class="mt-5 border-t border-slate-100 pt-3">
          <div class="flex items-center justify-between text-xs text-slate-500">
            <span v-if="need.start_date || need.end_date">
              {{ formatDate(need.start_date) }} <span v-if="need.end_date">→ {{ formatDate(need.end_date) }}</span>
            </span>
            <span v-else>No date limit</span>

            <!-- Status Dropdown Selector -->
            <div class="relative inline-block">
              <select
                :value="need.status || 'pending'"
                class="appearance-none rounded-full border px-3 py-1 pr-6 text-xs font-semibold cursor-pointer transition focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
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
          </div>

          <!-- Action menu buttons -->
          <div class="mt-3 flex items-center justify-end gap-2 border-t border-slate-50 pt-2 opacity-90 sm:opacity-0 sm:group-hover:opacity-100 transition">
            <button
              type="button"
              class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-50 hover:text-indigo-600 transition"
              title="Edit Need"
              @click="openEditModal(need)"
            >
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <path d="M12 20h9M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z" />
              </svg>
            </button>

            <button
              type="button"
              class="rounded-lg p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600 transition"
              title="Delete Need"
              @click="promptDelete(need)"
            >
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Create / Edit Need Modal -->
    <NeedFormModal
      v-model="showModal"
      :loading="saving"
      :initial-data="editingNeed"
      :server-message="errorMessage"
      :server-errors="fieldErrors"
      @submit="handleSave"
    />

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