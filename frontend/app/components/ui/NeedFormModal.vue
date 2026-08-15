<script setup lang="ts">
import Modal from '~/components/ui/Modal.vue'
import CategoryFormModal, { type CategoryOption } from '~/components/ui/CategoryFormModal.vue'
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

export interface NeedPayload {
  name: string
  type: 'personal' | 'group'
  amount: string
  categoryId: string
  startDate: string
  endDate: string
  groupId?: string
  allowMemberContribution?: boolean
}

// Retain BudgetPayload alias for backward compatibility
export type BudgetPayload = NeedPayload

const emit = defineEmits<{
  submit: [payload: NeedPayload]
}>()

const name = ref('')
const type = ref<'personal' | 'group'>('personal')
const amount = ref('')
const categoryId = ref('')
const startDate = ref('')
const endDate = ref('')
const groupId = ref('')
const allowMemberContribution = ref(false)

const showCategoryModal = ref(false)

const errors = ref<{
  name?: string
  amount?: string
  categoryId?: string
  groupId?: string
}>({})

const localServerMessage = ref<string | null>(props.serverMessage)
const localServerErrors = ref<Record<string, string> | null>(props.serverErrors)

interface Option {
  id: string
  name: string
}

const categories = ref<Option[]>([])
const loadingCategories = ref(false)
let categoriesLoaded = false

interface GroupOption {
  id: string
  name: string
}
const groups = ref<GroupOption[]>([])
const loadingGroups = ref(false)
let groupsLoaded = false

async function loadCategories() {
  if (categoriesLoaded) return
  loadingCategories.value = true
  try {
    const api = useApi()
    // categories.value = await api.get('need-categories')
    categories.value = [
      { id: 'housing', name: 'Housing & Rent' },
      { id: 'groceries', name: 'Food & Groceries' },
      { id: 'healthcare', name: 'Healthcare' },
      { id: 'education', name: 'Education' },
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

watch(type, (value) => {
  if (value === 'group') loadGroups()
})

function handleCategoryCreated(category: CategoryOption) {
  categories.value.push(category)
  categoryId.value = category.id
  clearServerError('categoryId')
}

function resetForm() {
  name.value = ''
  type.value = 'personal'
  amount.value = ''
  categoryId.value = ''
  startDate.value = ''
  endDate.value = ''
  groupId.value = ''
  allowMemberContribution.value = false
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

function validate() {
  errors.value = {}
  if (!name.value.trim()) errors.value.name = 'Need name is required'
  if (!amount.value) errors.value.amount = 'Need amount is required'
  if (!categoryId.value) errors.value.categoryId = 'Select a category'
  if (type.value === 'group' && !groupId.value) errors.value.groupId = 'Select a group'
  return Object.keys(errors.value).length === 0
}

function handleSubmit() {
  localServerMessage.value = null
  if (!validate()) return

  const payload: NeedPayload = {
    name: name.value,
    type: type.value,
    amount: amount.value,
    categoryId: categoryId.value,
    startDate: startDate.value,
    endDate: endDate.value,
  }

  if (type.value === 'group') {
    payload.groupId = groupId.value
    payload.allowMemberContribution = allowMemberContribution.value
  }

  emit('submit', payload)
}
</script>

<template>
  <Modal v-model="isOpen" title="Create Need" subtitle="Set up a personal or shared need.">
    <form class="space-y-5" novalidate @submit.prevent="handleSubmit">
      <div
        v-if="localServerMessage && !localServerErrors"
        class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"
      >
        {{ localServerMessage }}
      </div>

      <!-- Need name -->
      <div>
        <label for="need-name" class="mb-1.5 block text-sm font-medium text-slate-700">Need name</label>
        <input
          id="need-name"
          v-model="name"
          type="text"
          placeholder="e.g. Monthly Rent"
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

      <!-- Type -->
      <div>
        <label class="mb-1.5 block text-sm font-medium text-slate-700">Type</label>
        <div class="grid grid-cols-2 gap-2">
          <button
            type="button"
            class="rounded-xl border px-3 py-2.5 text-sm font-medium transition"
            :class="type === 'personal' ? 'border-indigo-400 bg-indigo-50 text-indigo-700' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
            @click="type = 'personal'"
          >
            Personal
          </button>
          <button
            type="button"
            class="rounded-xl border px-3 py-2.5 text-sm font-medium transition"
            :class="type === 'group' ? 'border-indigo-400 bg-indigo-50 text-indigo-700' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
            @click="type = 'group'"
          >
            Group
          </button>
        </div>
      </div>

      <!-- Need amount -->
      <div>
        <label for="need-amount" class="mb-1.5 block text-sm font-medium text-slate-700">Need amount (₦)</label>
        <div class="relative">
          <span class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-sm text-slate-400">₦</span>
          <input
            id="need-amount"
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

      <!-- Category -->
      <div>
        <div class="mb-1.5 flex items-center justify-between">
          <label for="need-category" class="block text-sm font-medium text-slate-700">Category</label>
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
          id="need-category"
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

      <!-- Dates -->
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label for="start-date" class="mb-1.5 block text-sm font-medium text-slate-700">Start date</label>
          <input
            id="start-date"
            v-model="startDate"
            type="date"
            class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-900 transition focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/30"
          />
        </div>
        <div>
          <label for="end-date" class="mb-1.5 block text-sm font-medium text-slate-700">End date</label>
          <input
            id="end-date"
            v-model="endDate"
            type="date"
            class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-900 transition focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/30"
          />
        </div>
      </div>

      <!-- Group settings -->
      <Transition name="expand">
        <div v-if="type === 'group'" class="space-y-5 rounded-2xl bg-slate-50 p-4">
          <div>
            <label for="group-select" class="mb-1.5 block text-sm font-medium text-slate-700">Group</label>
            <select
              id="group-select"
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
        </div>
      </Transition>

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
          {{ loading ? 'Saving…' : 'Create Need' }}
        </button>
      </div>
    </form>
  </Modal>

  <CategoryFormModal v-model="showCategoryModal" type="need" @created="handleCategoryCreated" />
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