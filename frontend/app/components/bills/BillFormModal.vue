<script setup lang="ts">
import Modal from '~/components/ui/Modal.vue'
import CategoryFormModal, { type CategoryOption } from '~/components/ui/CategoryFormModal.vue'
import ItemFormModal, { type ItemOption } from '~/components/ui/ItemFormModal.vue'
import CustomSplitModal, { type GroupMember } from '~/components/bills/CustomSplitModal.vue'
import { useApi } from '~/composables/useApi'

interface Props {
  loading?: boolean
  serverMessage?: string | null
  serverErrors?: Record<string, string> | null
}

const props = withDefaults(defineProps<Props>(), {
  loading: false,
  serverMessage: null,
  serverErrors: null,
})

const isOpen = defineModel<boolean>({ default: false })

export interface PersonalDebtorRow {
  type: 'registered' | 'guest'
  userId: string
  name: string
  amount: string
}

export interface GuestDebtorRow {
  name: string
  amount: string
}

export interface BillPayload {
  name: string
  itemId?: string
  amount: string
  dueDate: string
  startDate?: string
  categoryId: string
  description?: string
  billType: 'personal' | 'group'
  groupId?: string
  splitMethod?: 'equal' | 'fixed' | 'custom'
  fixedAmountPerMember?: string
  customSplit?: Record<string, string>
  personalDebtors?: PersonalDebtorRow[]
  guestDebtors?: GuestDebtorRow[]
  allowPartialPayment: boolean
}

const emit = defineEmits<{
  submit: [payload: BillPayload]
}>()

// Core fields
const name = ref('')
const selectedItemId = ref('')
const amount = ref('')
const startDate = ref(new Date().toISOString().split('T')[0])
const dueDate = ref('')
const categoryId = ref('')
const description = ref('')
const showDescription = ref(false)

// Bill scope (Group Debt vs Personal Debt)
const billType = ref<'group' | 'personal'>('group')
const groupId = ref('')

// Personal Debtors array list (side-by-side name & amount)
const personalDebtors = ref<PersonalDebtorRow[]>([
  { type: 'registered', userId: '', name: '', amount: '' },
])

// Group Guest Debtors array list
const guestDebtors = ref<GuestDebtorRow[]>([])

// Registered App Users list
interface AppUser {
  id: string
  fullname: string
  email?: string
}
const registeredUsers = ref<AppUser[]>([])
const loadingUsers = ref(false)
let usersLoaded = false

// Split method
const splitMethod = ref<'equal' | 'fixed' | 'custom'>('equal')
const customSplit = ref<Record<string, string>>({})
const showCustomSplitModal = ref(false)
const showCategoryModal = ref(false)
const showItemModal = ref(false)

// Payment settings
const allowPartialPayment = ref(true)

const errors = ref<{
  name?: string
  amount?: string
  dueDate?: string
  categoryId?: string
  groupId?: string
  customSplit?: string
  debtors?: string
}>({})

const localServerMessage = ref<string | null>(props.serverMessage)
const localServerErrors = ref<Record<string, string> | null>(props.serverErrors)

interface Option {
  id: string
  name: string
}

const items = ref<Option[]>([])
const loadingItems = ref(false)
let itemsLoaded = false

const categories = ref<Option[]>([])
const loadingCategories = ref(false)
let categoriesLoaded = false

const groups = ref<Option[]>([])
const loadingGroups = ref(false)
let groupsLoaded = false

const groupMembers = ref<GroupMember[]>([])
const loadingMembers = ref(false)
let loadedMembersFor = ''

async function loadBillItems() {
  if (itemsLoaded) return
  loadingItems.value = true
  try {
    const api = useApi()
    const res: any = await api.get('items?type=bill')
    const list = Array.isArray(res) ? res : (res?.data || [])
    items.value = list.map((i: any) => ({
      id: i.id,
      name: i.name,
    }))
    itemsLoaded = true
  } catch (err) {
    console.error('Failed to load bill items:', err)
  } finally {
    loadingItems.value = false
  }
}

async function loadCategories() {
  if (categoriesLoaded) return
  loadingCategories.value = true
  try {
    const api = useApi()
    const res: any = await api.get('categories/all')
    const list = Array.isArray(res) ? res : (res?.data || [])
    categories.value = list.map((c: any) => ({
      id: c.id,
      name: c.category_name || c.name,
    }))
    categoriesLoaded = true
  } catch (err) {
    console.error('Failed to load categories:', err)
  } finally {
    loadingCategories.value = false
  }
}

async function loadGroups() {
  if (groupsLoaded) return
  loadingGroups.value = true
  try {
    const api = useApi()
    const res: any = await api.get('groups/list')
    const list = Array.isArray(res) ? res : (res?.data || [])
    groups.value = list.map((g: any) => ({
      id: g.id,
      name: g.group_name || g.name,
    }))
    groupsLoaded = true
  } catch (err) {
    console.error('Failed to load groups:', err)
  } finally {
    loadingGroups.value = false
  }
}

async function searchRegisteredUsers(query = '') {
  if (usersLoaded && !query) return
  loadingUsers.value = true
  try {
    const api = useApi()
    const endpoint = query.trim() ? `users/search?query=${encodeURIComponent(query.trim())}` : 'users/search?query=a'
    const res: any = await api.get(endpoint)
    const list = Array.isArray(res) ? res : (res?.data || [])
    registeredUsers.value = list.map((u: any) => ({
      id: u.id,
      fullname: u.fullname || u.name || u.email,
      email: u.email,
    }))
    usersLoaded = true
  } catch (err) {
    console.error('Failed to search users:', err)
  } finally {
    loadingUsers.value = false
  }
}

async function loadGroupMembers(id: string) {
  if (!id || loadedMembersFor === id) return
  loadingMembers.value = true
  try {
    const api = useApi()
    const res: any = await api.get(`groups/${id}`)
    const members = res?.data?.members || res?.members || []
    groupMembers.value = members.map((m: any) => ({
      id: m.id,
      name: m.user ? m.user.fullname : (m.name || m.email || 'Member'),
      email: m.user ? m.user.email : (m.email || ''),
    }))
    loadedMembersFor = id
  } catch (err) {
    console.error('Failed to load group members:', err)
  } finally {
    loadingMembers.value = false
  }
}

watch(groupId, (value) => {
  if (value && splitMethod.value === 'custom') loadGroupMembers(value)
})

watch(splitMethod, (value) => {
  if (value === 'custom' && groupId.value) loadGroupMembers(groupId.value)
})

watch(billType, (val) => {
  if (val === 'group') {
    loadGroups()
  } else {
    searchRegisteredUsers()
  }
})

function addPersonalDebtorRow() {
  personalDebtors.value.push({ type: 'registered', userId: '', name: '', amount: '' })
}

function removePersonalDebtorRow(index: number) {
  if (personalDebtors.value.length > 1) {
    personalDebtors.value.splice(index, 1)
  }
}

function addGuestDebtorRow() {
  guestDebtors.value.push({ name: '', amount: '' })
}

function removeGuestDebtorRow(index: number) {
  guestDebtors.value.splice(index, 1)
}

function handleRegisteredUserChange(index: number) {
  const row = personalDebtors.value[index]
  if (!row) return
  const found = registeredUsers.value.find((u) => u.id === row.userId)
  if (found) {
    row.name = found.fullname
  }
}

// Automatically calculate total amount for personal bill from personal debtors
watch(personalDebtors, (list) => {
  if (billType.value === 'personal') {
    const total = list.reduce((sum, item) => sum + (Number(item.amount) || 0), 0)
    if (total > 0) amount.value = String(total)
  }
}, { deep: true })

function handleCategoryCreated(category: CategoryOption) {
  categories.value.push(category)
  categoryId.value = category.id
  clearServerError('categoryId')
}

function handleItemCreated(item: ItemOption) {
  items.value.push(item)
  selectedItemId.value = item.id
  name.value = item.name
  clearServerError('name')
}

function handleItemChange() {
  clearServerError('name')
  const found = items.value.find((i) => i.id === selectedItemId.value)
  if (found) {
    name.value = found.name
  }
}

const customSplitSummary = computed(() => {
  const entries = Object.entries(customSplit.value).filter(([, v]) => v)
  if (entries.length === 0) return null
  const total = entries.reduce((sum, [, v]) => sum + (Number(v) || 0), 0)
  return `${entries.length} member${entries.length > 1 ? 's' : ''} configured — ₦${total.toLocaleString()} total`
})

const showAmountField = computed(() => {
  if (billType.value === 'personal') return true
  return splitMethod.value === 'equal' || splitMethod.value === 'fixed'
})

function handleCustomSplitSubmit(values: Record<string, string>) {
  customSplit.value = values
  errors.value.customSplit = undefined
  const total = Object.values(values).reduce((sum, v) => sum + (Number(v) || 0), 0)
  amount.value = total ? String(total) : ''
}

function resetForm() {
  name.value = ''
  selectedItemId.value = ''
  amount.value = ''
  startDate.value = new Date().toISOString().split('T')[0]
  dueDate.value = ''
  categoryId.value = ''
  description.value = ''
  showDescription.value = false
  billType.value = 'group'
  groupId.value = ''
  splitMethod.value = 'equal'
  personalDebtors.value = [{ type: 'registered', userId: '', name: '', amount: '' }]
  guestDebtors.value = []
  customSplit.value = {}
  allowPartialPayment.value = true
  errors.value = {}
  localServerMessage.value = null
  localServerErrors.value = null
}

watch(isOpen, (open) => {
  if (open) {
    resetForm()
    loadBillItems()
    loadCategories()
    loadGroups()
    searchRegisteredUsers()
  }
})

watch(() => props.serverMessage, (msg) => (localServerMessage.value = msg))
watch(() => props.serverErrors, (errs) => {
  localServerErrors.value = errs ? { ...errs } : null
  if (errs && Object.keys(errs).length) localServerMessage.value = null
})

function clearServerError(field: string) {
  localServerMessage.value = null
  if (!localServerErrors.value?.[field]) return
  const next = { ...localServerErrors.value }
  delete next[field]
  localServerErrors.value = Object.keys(next).length ? next : null
}

function validate() {
  errors.value = {}
  if (!name.value.trim()) errors.value.name = 'Select or create a bill item'
  if (showAmountField.value && !amount.value) errors.value.amount = 'Amount is required'
  if (!dueDate.value) errors.value.dueDate = 'Due date is required'
  if (!categoryId.value) errors.value.categoryId = 'Select a category'

  if (billType.value === 'group') {
    if (!groupId.value) errors.value.groupId = 'Select a group'
    if (splitMethod.value === 'custom' && Object.keys(customSplit.value).length === 0) {
      errors.value.customSplit = 'Set a custom split for members'
    }
  } else if (billType.value === 'personal') {
    const invalidRow = personalDebtors.value.some((row) => {
      if (row.type === 'registered' && !row.userId) return true
      if (row.type === 'guest' && !row.name.trim()) return true
      if (!row.amount || Number(row.amount) <= 0) return true
      return false
    })
    if (invalidRow) {
      errors.value.debtors = 'Please fill all debtor name/user and amount fields.'
    }
  }

  return Object.keys(errors.value).length === 0
}

function handleSubmit() {
  localServerMessage.value = null
  if (!validate()) return

  const payload: BillPayload = {
    name: name.value,
    itemId: selectedItemId.value || undefined,
    amount: amount.value,
    startDate: startDate.value,
    dueDate: dueDate.value,
    categoryId: categoryId.value,
    description: description.value || undefined,
    billType: billType.value,
    allowPartialPayment: allowPartialPayment.value,
  }

  if (billType.value === 'group') {
    payload.groupId = groupId.value
    payload.splitMethod = splitMethod.value
    if (splitMethod.value === 'fixed') payload.fixedAmountPerMember = amount.value
    if (splitMethod.value === 'custom') payload.customSplit = customSplit.value
    if (guestDebtors.value.length > 0) payload.guestDebtors = guestDebtors.value
  } else if (billType.value === 'personal') {
    payload.personalDebtors = personalDebtors.value
  }

  emit('submit', payload)
}
</script>

<template>
  <Modal v-model="isOpen" title="Create Bill / Debt" subtitle="Set up a group split bill or personal IOUs owed to you.">
    <form class="space-y-5" novalidate @submit.prevent="handleSubmit">
      <div
        v-if="localServerMessage && !localServerErrors"
        class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"
      >
        {{ localServerMessage }}
      </div>

      <!-- Scope Switcher (Group Debt vs Personal Debt) -->
      <div>
        <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-500">Bill Type / Scope</label>
        <div class="grid grid-cols-2 gap-2 rounded-2xl bg-slate-100 p-1">
          <button
            type="button"
            class="rounded-xl py-2.5 text-xs font-bold transition flex items-center justify-center gap-1.5"
            :class="billType === 'group' ? 'bg-white text-indigo-700 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
            @click="billType = 'group'"
          >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
              <circle cx="9" cy="7" r="4" />
              <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
              <path d="M16 3.13a4 4 0 0 1 0 7.75" />
            </svg>
            Group Debt / Split
          </button>
          <button
            type="button"
            class="rounded-xl py-2.5 text-xs font-bold transition flex items-center justify-center gap-1.5"
            :class="billType === 'personal' ? 'bg-white text-indigo-700 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
            @click="billType = 'personal'"
          >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
              <circle cx="12" cy="7" r="4" />
            </svg>
            Personal Debt / IOU
          </button>
        </div>
      </div>

      <!-- Bill name Item Select with Create Item Link -->
      <div>
        <div class="mb-1.5 flex items-center justify-between">
          <label for="bill-item-select" class="block text-sm font-medium text-slate-700">Bill Item Name</label>
          <button
            type="button"
            class="flex items-center gap-1 text-xs font-medium text-emerald-600 hover:text-emerald-700"
            @click="showItemModal = true"
          >
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round">
              <path d="M12 5v14M5 12h14" />
            </svg>
            Create item
          </button>
        </div>
        <select
          id="bill-item-select"
          v-model="selectedItemId"
          class="w-full rounded-xl border bg-white px-4 py-3 text-sm text-slate-900 transition focus:outline-none focus:ring-2"
          :class="errors.name || localServerErrors?.name
            ? 'border-rose-300 focus:border-rose-400 focus:ring-rose-200'
            : 'border-slate-200 focus:border-emerald-500 focus:ring-emerald-500/30'"
          @change="handleItemChange"
        >
          <option value="" disabled>{{ loadingItems ? 'Loading bill items…' : 'Select a bill item' }}</option>
          <option v-for="i in items" :key="i.id" :value="i.id">{{ i.name }}</option>
        </select>
        <p v-if="errors.name || localServerErrors?.name" class="mt-1.5 text-xs text-rose-600">
          {{ errors.name || localServerErrors?.name }}
        </p>
      </div>

      <!-- Personal Debtors Section (Side-by-side Name & Amount fields) -->
      <div v-if="billType === 'personal'" class="space-y-4 rounded-2xl border border-indigo-100 bg-indigo-50/40 p-4">
        <div class="flex items-center justify-between">
          <div>
            <h4 class="text-xs font-bold text-indigo-900 uppercase tracking-wider">Personal Debtors & Amounts</h4>
            <p class="text-[11px] text-slate-500">Add registered users or guest debtors with their respective amounts side-by-side.</p>
          </div>
        </div>

        <div v-for="(debtor, idx) in personalDebtors" :key="idx" class="space-y-2 rounded-xl bg-white p-3 border border-slate-200/70 shadow-2xs">
          <div class="flex items-center justify-between">
            <!-- Payer Type Switcher -->
            <div class="flex items-center gap-1 rounded-lg bg-slate-100 p-0.5 text-[11px] font-semibold">
              <button
                type="button"
                class="px-2 py-0.5 rounded-md transition"
                :class="debtor.type === 'registered' ? 'bg-white text-indigo-700 shadow-2xs' : 'text-slate-500'"
                @click="debtor.type = 'registered'"
              >
                Registered User
              </button>
              <button
                type="button"
                class="px-2 py-0.5 rounded-md transition"
                :class="debtor.type === 'guest' ? 'bg-white text-indigo-700 shadow-2xs' : 'text-slate-500'"
                @click="debtor.type = 'guest'"
              >
                Guest Contributor
              </button>
            </div>

            <button
              v-if="personalDebtors.length > 1"
              type="button"
              class="text-xs font-semibold text-rose-600 hover:text-rose-700"
              @click="removePersonalDebtorRow(idx)"
            >
              Remove
            </button>
          </div>

          <!-- Side-by-Side Name & Amount Fields -->
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <!-- Name / Registered User Select -->
            <div>
              <label class="mb-1 block text-xs font-semibold text-slate-700">
                {{ debtor.type === 'registered' ? 'Select Registered User' : 'Guest Debtor Name' }}
              </label>
              <select
                v-if="debtor.type === 'registered'"
                v-model="debtor.userId"
                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                @change="handleRegisteredUserChange(idx)"
              >
                <option value="" disabled>{{ loadingUsers ? 'Loading users…' : 'Select registered user' }}</option>
                <option v-for="u in registeredUsers" :key="u.id" :value="u.id">
                  {{ u.fullname }} {{ u.email ? `(${u.email})` : '' }}
                </option>
              </select>

              <input
                v-else
                v-model="debtor.name"
                type="text"
                placeholder="e.g. Samuel (Guest)"
                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
              />
            </div>

            <!-- Amount (₦) Field -->
            <div>
              <label class="mb-1 block text-xs font-semibold text-slate-700">Amount Owed (₦)</label>
              <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-xs text-slate-400">₦</span>
                <input
                  v-model="debtor.amount"
                  type="number"
                  step="0.01"
                  placeholder="0.00"
                  class="w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-7 pr-3 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                />
              </div>
            </div>
          </div>
        </div>

        <button
          type="button"
          class="w-full rounded-xl border border-dashed border-indigo-300 bg-white py-2.5 text-center text-xs font-bold text-indigo-600 hover:bg-indigo-50/50 transition flex items-center justify-center gap-1.5"
          @click="addPersonalDebtorRow"
        >
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round">
            <path d="M12 5v14M5 12h14" />
          </svg>
          Add Another Debtor Entry
        </button>
        <p v-if="errors.debtors" class="text-xs text-rose-600">{{ errors.debtors }}</p>
      </div>

      <!-- Group Selection & Split Method (Group Debt Scope) -->
      <div v-if="billType === 'group'" class="space-y-4">
        <div>
          <label for="bill-group" class="mb-1.5 block text-sm font-medium text-slate-700">Group</label>
          <select
            id="bill-group"
            v-model="groupId"
            class="w-full rounded-xl border bg-white px-4 py-3 text-sm text-slate-900 transition focus:outline-none focus:ring-2"
            :class="errors.groupId || localServerErrors?.groupId
              ? 'border-rose-300 focus:border-rose-400 focus:ring-rose-200'
              : 'border-slate-200 focus:border-emerald-500 focus:ring-emerald-500/30'"
            @change="clearServerError('groupId')"
          >
            <option value="" disabled>{{ loadingGroups ? 'Loading groups…' : 'Select a group' }}</option>
            <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
          </select>
          <p v-if="errors.groupId || localServerErrors?.groupId" class="mt-1.5 text-xs text-rose-600">
            {{ errors.groupId || localServerErrors?.groupId }}
          </p>
        </div>

        <!-- Split method selection & Explanations -->
        <div class="rounded-2xl bg-slate-50/80 p-4 border border-slate-100 space-y-3">
          <label class="block text-sm font-medium text-slate-700">Split Method</label>
          <div class="grid grid-cols-3 gap-2">
            <button
              type="button"
              class="rounded-xl border bg-white px-3 py-2.5 text-sm font-medium transition"
              :class="splitMethod === 'equal' ? 'border-indigo-500 bg-indigo-50 text-indigo-700 font-bold' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
              @click="splitMethod = 'equal'"
            >
              Equal
            </button>
            <button
              type="button"
              class="rounded-xl border bg-white px-3 py-2.5 text-sm font-medium transition"
              :class="splitMethod === 'fixed' ? 'border-indigo-500 bg-indigo-50 text-indigo-700 font-bold' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
              @click="splitMethod = 'fixed'"
            >
              Fixed
            </button>
            <button
              type="button"
              class="rounded-xl border bg-white px-3 py-2.5 text-sm font-medium transition"
              :class="splitMethod === 'custom' ? 'border-indigo-500 bg-indigo-50 text-indigo-700 font-bold' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
              @click="splitMethod = 'custom'"
            >
              Custom
            </button>
          </div>

          <!-- Short Description Explanations for Split Methods -->
          <p v-if="splitMethod === 'equal'" class="text-xs text-slate-600 bg-white p-3 rounded-xl border border-slate-200/70 leading-relaxed">
            💡 <strong>Equal Split:</strong> The total bill amount is divided equally among all members of the group (Total Bill Amount ÷ Number of Group Members).
          </p>

          <p v-else-if="splitMethod === 'fixed'" class="text-xs text-slate-600 bg-white p-3 rounded-xl border border-slate-200/70 leading-relaxed">
            💡 <strong>Fixed Split:</strong> All group members pay the exact same fixed amount specified above.
          </p>

          <p v-else-if="splitMethod === 'custom'" class="text-xs text-slate-600 bg-white p-3 rounded-xl border border-slate-200/70 leading-relaxed">
            💡 <strong>Custom Split:</strong> Set individual custom split amounts for each group member.
          </p>

          <Transition name="expand">
            <div v-if="splitMethod === 'custom'" class="mt-2">
              <button
                type="button"
                class="w-full rounded-xl border border-dashed border-slate-300 bg-white px-4 py-3 text-left text-sm text-slate-600 transition hover:border-indigo-300 hover:bg-indigo-50/50"
                @click="showCustomSplitModal = true"
              >
                <span v-if="customSplitSummary" class="font-medium text-slate-900">{{ customSplitSummary }}</span>
                <span v-else>Set custom amounts for members →</span>
              </button>
              <p v-if="errors.customSplit" class="mt-1.5 text-xs text-rose-600">{{ errors.customSplit }}</p>
            </div>
          </Transition>
        </div>

        <!-- Add Guest Members to Group Bill (Side-by-side Name & Amount) -->
        <div class="space-y-3 rounded-2xl border border-slate-200/80 bg-slate-50/60 p-4">
          <div class="flex items-center justify-between">
            <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider">Additional Guest Members (Optional)</h4>
            <button
              type="button"
              class="flex items-center gap-1 text-xs font-semibold text-indigo-600 hover:text-indigo-700"
              @click="addGuestDebtorRow"
            >
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4">
                <path d="M12 5v14M5 12h14" />
              </svg>
              Add Guest Member
            </button>
          </div>

          <div v-for="(guest, gIdx) in guestDebtors" :key="gIdx" class="grid grid-cols-1 gap-3 sm:grid-cols-2 items-center rounded-xl bg-white p-3 border border-slate-200/70">
            <div>
              <label class="mb-1 block text-xs font-semibold text-slate-700">Guest Name</label>
              <input
                v-model="guest.name"
                type="text"
                placeholder="e.g. Samuel (Guest)"
                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
              />
            </div>
            <div class="relative">
              <div class="flex items-center justify-between mb-1">
                <label class="block text-xs font-semibold text-slate-700">Amount (₦)</label>
                <button
                  type="button"
                  class="text-[11px] font-semibold text-rose-600 hover:text-rose-700"
                  @click="removeGuestDebtorRow(gIdx)"
                >
                  Remove
                </button>
              </div>
              <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-xs text-slate-400">₦</span>
                <input
                  v-model="guest.amount"
                  type="number"
                  step="0.01"
                  placeholder="0.00"
                  class="w-full rounded-xl border border-slate-200 bg-white py-2 pl-7 pr-3 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                />
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Total amount & Dates -->
      <div :class="showAmountField ? 'grid grid-cols-2 gap-4' : ''">
        <div v-if="showAmountField">
          <label for="bill-amount" class="mb-1.5 block text-sm font-medium text-slate-700">
            {{ billType === 'personal' ? 'Total Personal Debt (₦)' : (splitMethod === 'fixed' ? 'Fixed Amount per Member (₦)' : 'Total Bill Amount (₦)') }}
          </label>
          <div class="relative">
            <span class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-sm text-slate-400">₦</span>
            <input
              id="bill-amount"
              v-model="amount"
              type="number"
              step="0.01"
              placeholder="0.00"
              class="w-full rounded-xl border py-3 pl-9 pr-4 text-sm text-slate-900 placeholder:text-slate-400 transition focus:outline-none focus:ring-2"
              :class="errors.amount || localServerErrors?.amount
                ? 'border-rose-300 focus:border-rose-400 focus:ring-rose-200'
                : 'border-slate-200 focus:border-emerald-500 focus:ring-emerald-500/30'"
              @input="clearServerError('amount')"
            />
          </div>
          <p v-if="errors.amount || localServerErrors?.amount" class="mt-1.5 text-xs text-rose-600">
            {{ errors.amount || localServerErrors?.amount }}
          </p>
        </div>

        <div>
          <label for="bill-due-date" class="mb-1.5 block text-sm font-medium text-slate-700">Due date</label>
          <input
            id="bill-due-date"
            v-model="dueDate"
            type="date"
            class="w-full rounded-xl border px-4 py-3 text-sm text-slate-900 transition focus:outline-none focus:ring-2"
            :class="errors.dueDate || localServerErrors?.dueDate
              ? 'border-rose-300 focus:border-rose-400 focus:ring-rose-200'
              : 'border-slate-200 focus:border-emerald-500 focus:ring-emerald-500/30'"
            @change="clearServerError('dueDate')"
          />
          <p v-if="errors.dueDate || localServerErrors?.dueDate" class="mt-1.5 text-xs text-rose-600">
            {{ errors.dueDate || localServerErrors?.dueDate }}
          </p>
        </div>
      </div>

      <!-- Category -->
      <div>
        <div class="mb-1.5 flex items-center justify-between">
          <label for="bill-category" class="block text-sm font-medium text-slate-700">Category</label>
          <button
            type="button"
            class="flex items-center gap-1 text-xs font-medium text-emerald-600 hover:text-emerald-700"
            @click="showCategoryModal = true"
          >
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round">
              <path d="M12 5v14M5 12h14" />
            </svg>
            Create category
          </button>
        </div>
        <select
          id="bill-category"
          v-model="categoryId"
          class="w-full rounded-xl border bg-white px-4 py-3 text-sm text-slate-900 transition focus:outline-none focus:ring-2"
          :class="errors.categoryId || localServerErrors?.categoryId
            ? 'border-rose-300 focus:border-rose-400 focus:ring-rose-200'
            : 'border-slate-200 focus:border-emerald-500 focus:ring-emerald-500/30'"
          @change="clearServerError('categoryId')"
        >
          <option value="" disabled>{{ loadingCategories ? 'Loading categories…' : 'Select a category' }}</option>
          <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
        <p v-if="errors.categoryId || localServerErrors?.categoryId" class="mt-1.5 text-xs text-rose-600">
          {{ errors.categoryId || localServerErrors?.categoryId }}
        </p>
      </div>

      <!-- Description -->
      <div>
        <button
          v-if="!showDescription"
          type="button"
          class="flex items-center gap-1.5 text-sm font-medium text-emerald-600 hover:text-emerald-700"
          @click="showDescription = true"
        >
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
            <path d="M12 5v14M5 12h14" />
          </svg>
          Add description
        </button>

        <Transition name="expand">
          <div v-if="showDescription">
            <div class="mb-1.5 flex items-center justify-between">
              <label for="bill-description" class="block text-sm font-medium text-slate-700">Description (optional)</label>
              <button
                type="button"
                class="text-xs font-medium text-slate-400 hover:text-slate-600"
                @click="showDescription = false; description = ''"
              >
                Remove
              </button>
            </div>
            <textarea
              id="bill-description"
              v-model="description"
              rows="3"
              placeholder="Any extra details"
              class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 transition focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/30"
            />
          </div>
        </Transition>
      </div>

      <!-- Partial payment setting -->
      <div class="space-y-4 rounded-2xl border border-slate-100 p-4">
        <div class="flex items-center justify-between gap-4">
          <div>
            <p class="text-sm font-medium text-slate-700">Allow partial payment</p>
            <p class="mt-0.5 text-xs text-slate-500">
              When ON, members can pay their share in parts; when OFF, payment must be in full.
            </p>
          </div>
          <button
            type="button"
            role="switch"
            :aria-checked="allowPartialPayment"
            class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition"
            :class="allowPartialPayment ? 'bg-emerald-500' : 'bg-slate-300'"
            @click="allowPartialPayment = !allowPartialPayment"
          >
            <span
              class="inline-block h-4 w-4 transform rounded-full bg-white transition"
              :class="allowPartialPayment ? 'translate-x-6' : 'translate-x-1'"
            />
          </button>
        </div>
      </div>

      <div class="flex justify-end gap-3 border-t border-slate-100 pt-5">
        <button
          type="button"
          class="rounded-full px-5 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50"
          @click="isOpen = false"
        >
          Cancel
        </button>
        <button
          type="submit"
          :disabled="loading"
          class="rounded-full bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-70"
        >
          {{ loading ? 'Saving…' : (billType === 'personal' ? 'Create Personal Debt' : 'Create Group Bill') }}
        </button>
      </div>
    </form>
  </Modal>

  <CategoryFormModal v-model="showCategoryModal" type="bill" @created="handleCategoryCreated" />

  <ItemFormModal v-model="showItemModal" type="bill" @created="handleItemCreated" />

  <CustomSplitModal
    v-model="showCustomSplitModal"
    :members="groupMembers"
    :total-amount="amount"
    :existing-amounts="customSplit"
    @submit="handleCustomSplitSubmit"
  />
</template>

<style scoped>
.expand-enter-active,
.expand-leave-active {
  transition: all 0.2s ease;
}
.expand-enter-from,
.expand-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>