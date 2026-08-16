<script setup lang="ts">
import Modal from '~/components/ui/Modal.vue'
import CategoryFormModal, { type CategoryOption } from '~/components/ui/CategoryFormModal.vue'
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

export interface BillPayload {
  name: string
  amount: string
  dueDate: string
  categoryId: string
  description?: string
  billType: 'personal' | 'group'
  groupId?: string
  splitMethod?: 'equal' | 'fixed' | 'custom'
  fixedAmountPerMember?: string
  customSplit?: Record<string, string>
  allowPartialPayment: boolean
  allowPaymentProof: boolean
  autoReminder: boolean
  reminderFrequency?: string
}

const emit = defineEmits<{
  submit: [payload: BillPayload]
}>()

// 1–5. Core fields
const name = ref('')
const amount = ref('')
const dueDate = ref('')
const categoryId = ref('')
const description = ref('')
const showDescription = ref(false)

// 6. Bill type + group
const billType = ref<'personal' | 'group'>('personal')
const groupId = ref('')

// 7. Split method
const splitMethod = ref<'equal' | 'fixed' | 'custom'>('equal')
const fixedAmountPerMember = ref('')
const customSplit = ref<Record<string, string>>({})
const showCustomSplitModal = ref(false)
const showCategoryModal = ref(false)

// 8. Payment settings
const allowPartialPayment = ref(false)
const allowPaymentProof = ref(false)
const autoReminder = ref(false)
const reminderFrequency = ref('3_days_before')

const reminderOptions = [
  { value: '1_day_before', label: '1 day before due date' },
  { value: '3_days_before', label: '3 days before due date' },
  { value: '1_week_before', label: '1 week before due date' },
]

const errors = ref<{
  name?: string
  amount?: string
  dueDate?: string
  categoryId?: string
  groupId?: string
  fixedAmountPerMember?: string
  customSplit?: string
}>({})

const localServerMessage = ref<string | null>(props.serverMessage)
const localServerErrors = ref<Record<string, string> | null>(props.serverErrors)

// TODO: replace with real endpoints
interface Option {
  id: string
  name: string
}

const categories = ref<Option[]>([])
const loadingCategories = ref(false)
let categoriesLoaded = false

const groups = ref<Option[]>([])
const loadingGroups = ref(false)
let groupsLoaded = false

const groupMembers = ref<GroupMember[]>([])
const loadingMembers = ref(false)
let loadedMembersFor = ''

async function loadCategories() {
  if (categoriesLoaded) return
  loadingCategories.value = true
  try {
    const api = useApi()
    // categories.value = await api.get('bill-categories')
    categories.value = [
      { id: 'rent', name: 'Rent & Utilities' },
      { id: 'food', name: 'Food' },
      { id: 'transport', name: 'Transport' },
      { id: 'subscriptions', name: 'Subscriptions' },
    ]
    categoriesLoaded = true
  } finally {
    loadingCategories.value = false
  }
}

async function loadGroups() {
  if (groupsLoaded) return
  loadingGroups.value = true
  try {
    const api = useApi()
    // groups.value = await api.get('groups')
    groups.value = [
      { id: 'family', name: 'Family' },
      { id: 'roommates', name: 'Roommates' },
      { id: 'office', name: 'Office Lunch Crew' },
    ]
    groupsLoaded = true
  } finally {
    loadingGroups.value = false
  }
}

async function loadGroupMembers(id: string) {
  if (!id || loadedMembersFor === id) return
  loadingMembers.value = true
  try {
    const api = useApi()
    // groupMembers.value = await api.get(`groups/${id}/members`)
    groupMembers.value = [
      { id: 'u1', name: 'Ada Okafor' },
      { id: 'u2', name: 'Bayo Adeyemi' },
      { id: 'u3', name: 'Tunde Bello' },
    ]
    loadedMembersFor = id
  } finally {
    loadingMembers.value = false
  }
}

watch(billType, (value) => {
  if (value === 'group') loadGroups()
})

watch(groupId, (value) => {
  if (value && splitMethod.value === 'custom') loadGroupMembers(value)
})

watch(splitMethod, (value) => {
  if (value === 'custom' && groupId.value) loadGroupMembers(groupId.value)
})

function resetForm() {
  name.value = ''
  amount.value = ''
  dueDate.value = ''
  categoryId.value = ''
  description.value = ''
  showDescription.value = false
  billType.value = 'personal'
  groupId.value = ''
  splitMethod.value = 'equal'
  fixedAmountPerMember.value = ''
  customSplit.value = {}
  allowPartialPayment.value = false
  allowPaymentProof.value = false
  autoReminder.value = false
  reminderFrequency.value = '3_days_before'
  errors.value = {}
  localServerMessage.value = null
  localServerErrors.value = null
}

watch(isOpen, (open) => {
  if (open) {
    resetForm()
    loadCategories()
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

function handleCategoryCreated(category: CategoryOption) {
  categories.value.push(category)
  categoryId.value = category.id
  clearServerError('categoryId')
}

const customSplitSummary = computed(() => {
  const entries = Object.entries(customSplit.value).filter(([, v]) => v)
  if (entries.length === 0) return null
  const total = entries.reduce((sum, [, v]) => sum + (Number(v) || 0), 0)
  return `${entries.length} member${entries.length > 1 ? 's' : ''} set — ₦${total.toLocaleString()} total`
})

const showAmountField = computed(() => {
  if (billType.value === 'personal') return true
  if (billType.value === 'group') {
    return splitMethod.value === 'equal' || splitMethod.value === 'fixed'
  }
  return false
})

function handleCustomSplitSubmit(values: Record<string, string>) {
  customSplit.value = values
  errors.value.customSplit = undefined
  const total = Object.values(values).reduce((sum, v) => sum + (Number(v) || 0), 0)
  amount.value = total ? String(total) : ''
}

function validate() {
  errors.value = {}
  if (!name.value.trim()) errors.value.name = 'Bill name is required'
  if (showAmountField.value && !amount.value) errors.value.amount = 'Amount is required'
  if (!dueDate.value) errors.value.dueDate = 'Due date is required'
  if (!categoryId.value) errors.value.categoryId = 'Select a category'

  if (billType.value === 'group') {
    if (!groupId.value) errors.value.groupId = 'Select a group'

    if (splitMethod.value === 'custom' && Object.keys(customSplit.value).length === 0) {
      errors.value.customSplit = 'Set a custom split for members'
    }
  }

  return Object.keys(errors.value).length === 0
}

function handleSubmit() {
  localServerMessage.value = null
  if (!validate()) return

  const payload: BillPayload = {
    name: name.value,
    amount: amount.value,
    dueDate: dueDate.value,
    categoryId: categoryId.value,
    description: description.value || undefined,
    billType: billType.value,
    allowPartialPayment: allowPartialPayment.value,
    allowPaymentProof: allowPaymentProof.value,
    autoReminder: autoReminder.value,
  }

  if (billType.value === 'group') {
    payload.groupId = groupId.value
    payload.splitMethod = splitMethod.value
    if (splitMethod.value === 'fixed') payload.fixedAmountPerMember = amount.value
    if (splitMethod.value === 'custom') payload.customSplit = customSplit.value
  }

  if (autoReminder.value) payload.reminderFrequency = reminderFrequency.value

  emit('submit', payload)
}
</script>

<template>

  <Modal v-model="isOpen" title="Create Bill" subtitle="Set up a bill and how it should be paid.">
    <form class="space-y-5" novalidate @submit.prevent="handleSubmit">
      <div
        v-if="localServerMessage && !localServerErrors"
        class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"
      >
        {{ localServerMessage }}
      </div>

      <!-- 1. Bill name -->
      <div>
        <label for="bill-name" class="mb-1.5 block text-sm font-medium text-slate-700">Bill name</label>
        <input
          id="bill-name"
          v-model="name"
          type="text"
          placeholder="e.g. Internet Subscription"
          class="w-full rounded-xl border px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 transition focus:outline-none focus:ring-2"
          :class="errors.name || localServerErrors?.name
            ? 'border-rose-300 focus:border-rose-400 focus:ring-rose-200'
            : 'border-slate-200 focus:border-emerald-500 focus:ring-emerald-500/30'"
          @input="clearServerError('name')"
        />
        <p v-if="errors.name || localServerErrors?.name" class="mt-1.5 text-xs text-rose-600">
          {{ errors.name || localServerErrors?.name }}
        </p>
      </div>

      <!-- 2. Total amount / 3. Due date -->
      <div :class="showAmountField ? 'grid grid-cols-2 gap-4' : ''">
        <div v-if="showAmountField">
          <label for="bill-amount" class="mb-1.5 block text-sm font-medium text-slate-700">Amount (₦)</label>
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

      <!-- 4. Category -->
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

      <!-- 5. Description (optional, hidden by default) -->
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

      <!-- 6. Bill type -->
      <div>
        <label class="mb-1.5 block text-sm font-medium text-slate-700">Bill type</label>
        <div class="grid grid-cols-2 gap-2">
          <button
            type="button"
            class="rounded-xl border px-3 py-2.5 text-sm font-medium transition"
            :class="billType === 'personal' ? 'border-indigo-400 bg-indigo-50 text-indigo-700' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
            @click="billType = 'personal'"
          >
            Personal
          </button>
          <button
            type="button"
            class="rounded-xl border px-3 py-2.5 text-sm font-medium transition"
            :class="billType === 'group' ? 'border-indigo-400 bg-indigo-50 text-indigo-700' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
            @click="billType = 'group'"
          >
            Group
          </button>
        </div>
      </div>

      <!-- Group select + split method (only when Group) -->
      <Transition name="expand">
        <div v-if="billType === 'group'" class="space-y-5 rounded-2xl bg-slate-50 p-4">
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

          <!-- 7. Split method -->
          <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Split method</label>
            <div class="grid grid-cols-3 gap-2">
              <button
                type="button"
                class="rounded-xl border bg-white px-3 py-2.5 text-sm font-medium transition"
                :class="splitMethod === 'equal' ? 'border-indigo-400 bg-indigo-50 text-indigo-700' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
                @click="splitMethod = 'equal'"
              >
                Equal
              </button>
              <button
                type="button"
                class="rounded-xl border bg-white px-3 py-2.5 text-sm font-medium transition"
                :class="splitMethod === 'fixed' ? 'border-indigo-400 bg-indigo-50 text-indigo-700' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
                @click="splitMethod = 'fixed'"
              >
                Fixed
              </button>
              <button
                type="button"
                class="rounded-xl border bg-white px-3 py-2.5 text-sm font-medium transition"
                :class="splitMethod === 'custom' ? 'border-indigo-400 bg-indigo-50 text-indigo-700' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
                @click="splitMethod = 'custom'"
              >
                Custom
              </button>
            </div>

            <Transition name="expand">
              <p v-if="splitMethod === 'equal'" class="mt-2 text-xs text-slate-500">
                that amount will be divided by members.
              </p>
            </Transition>

            <Transition name="expand">
              <p v-if="splitMethod === 'fixed'" class="mt-2 text-xs text-slate-500">
                members will pay the same amount.
              </p>
            </Transition>

            <Transition name="expand">
              <div v-if="splitMethod === 'custom'" class="mt-3">
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
        </div>
      </Transition>

      <!-- 8. Payment settings -->
      <div class="space-y-4 rounded-2xl border border-slate-100 p-4">
        <p class="text-sm font-medium text-slate-700">Payment settings</p>

        <div class="flex items-center justify-between gap-4">
          <div>
            <p class="text-sm text-slate-700">Allow partial payment</p>
            <p class="mt-0.5 text-xs text-slate-500">Members can pay their share in installments.</p>
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

        <div class="flex items-center justify-between gap-4">
          <div>
            <p class="text-sm text-slate-700">Allow payment proof upload</p>
            <p class="mt-0.5 text-xs text-slate-500">Members can attach a receipt or screenshot when they pay.</p>
          </div>
          <button
            type="button"
            role="switch"
            :aria-checked="allowPaymentProof"
            class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition"
            :class="allowPaymentProof ? 'bg-emerald-500' : 'bg-slate-300'"
            @click="allowPaymentProof = !allowPaymentProof"
          >
            <span
              class="inline-block h-4 w-4 transform rounded-full bg-white transition"
              :class="allowPaymentProof ? 'translate-x-6' : 'translate-x-1'"
            />
          </button>
        </div>

        <div>
          <div class="flex items-center justify-between gap-4">
            <div>
              <p class="text-sm text-slate-700">Auto reminder</p>
              <p class="mt-0.5 text-xs text-slate-500">Automatically remind members before the due date.</p>
            </div>
            <button
              type="button"
              role="switch"
              :aria-checked="autoReminder"
              class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition"
              :class="autoReminder ? 'bg-emerald-500' : 'bg-slate-300'"
              @click="autoReminder = !autoReminder"
            >
              <span
                class="inline-block h-4 w-4 transform rounded-full bg-white transition"
                :class="autoReminder ? 'translate-x-6' : 'translate-x-1'"
              />
            </button>
          </div>

          <Transition name="expand">
            <div v-if="autoReminder" class="mt-3">
              <label for="reminder-frequency" class="mb-1.5 block text-sm font-medium text-slate-700">Remind</label>
              <select
                id="reminder-frequency"
                v-model="reminderFrequency"
                class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 transition focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/30"
              >
                <option v-for="opt in reminderOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
            </div>
          </Transition>
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
          {{ loading ? 'Saving…' : 'Create Bill' }}
        </button>
      </div>
    </form>
  </Modal>

  <CategoryFormModal v-model="showCategoryModal" type="bill" @created="handleCategoryCreated" />

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