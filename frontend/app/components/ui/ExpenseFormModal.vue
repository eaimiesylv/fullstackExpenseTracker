<script setup lang="ts">
import Modal from '~/components/ui/Modal.vue'
import CategoryFormModal, { type CategoryOption } from '~/components/ui/CategoryFormModal.vue'
import ItemFormModal, { type ItemOption } from '~/components/ui/ItemFormModal.vue'
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

export interface ExpensePayload {
  name: string
  amount: string
  date: string
  categoryId: string
  description?: string
  budgetLink: 'linked' | 'standalone'
  budgetId?: string
  expenseType?: 'personal' | 'group'
  groupId?: string
  splitExpense: boolean
  splitGroupId?: string
}

const emit = defineEmits<{
  submit: [payload: ExpensePayload]
}>()

// Core fields
const name = ref('')
const amount = ref('')
const date = ref('')
const categoryId = ref('')
const description = ref('')
const showDescription = ref(false)

const showItemModal = ref(false)

// Step 6: is this expense tied to an existing budget, or standalone?
const budgetLink = ref<'linked' | 'standalone'>('standalone')

// Step 7 (only when linked): which budget
const budgetId = ref('')

// Step 8 (only when standalone): personal or group expense
const expenseType = ref<'personal' | 'group'>('personal')

// Step 10 (only when standalone + group): which group
const groupId = ref('')

// Optional: split this expense with a group, independent of the budget-link path above
const splitExpense = ref(false)
const splitGroupId = ref('')

const errors = ref<{
  name?: string
  amount?: string
  date?: string
  categoryId?: string
  budgetId?: string
  groupId?: string
  splitGroupId?: string
}>({})

const localServerMessage = ref<string | null>(props.serverMessage)
const localServerErrors = ref<Record<string, string> | null>(props.serverErrors)

// TODO: replace these with your real endpoints, e.g.
// GET /expense-items, GET /expense-categories, GET /budgets, GET /groups
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

const showCategoryModal = ref(false)

function handleCategoryCreated(category: CategoryOption) {
  categories.value.push(category)
  categoryId.value = category.id
  clearServerError('categoryId')
}

function handleItemCreated(item: ItemOption) {
  items.value.push(item)
  name.value = item.name
  clearServerError('name')
}

const budgets = ref<Option[]>([])
const loadingBudgets = ref(false)
let budgetsLoaded = false

const groups = ref<Option[]>([])
const loadingGroups = ref(false)
let groupsLoaded = false

async function loadItems() {
  if (itemsLoaded) return
  loadingItems.value = true
  try {
    const api = useApi()
    // items.value = await api.get('expense-items')
    items.value = [
      { id: 'uber-ride', name: 'Uber ride' },
      { id: 'groceries', name: 'Groceries' },
      { id: 'electricity-bill', name: 'Electricity bill' },
    ]
    itemsLoaded = true
  } finally {
    loadingItems.value = false
  }
}

async function loadCategories() {
  if (categoriesLoaded) return
  loadingCategories.value = true
  try {
    const api = useApi()
    // categories.value = await api.get('expense-categories')
    categories.value = [
      { id: 'food', name: 'Food & Groceries' },
      { id: 'transport', name: 'Transport' },
      { id: 'utilities', name: 'Utilities' },
      { id: 'entertainment', name: 'Entertainment' },
    ]
    categoriesLoaded = true
  } finally {
    loadingCategories.value = false
  }
}

async function loadBudgets() {
  if (budgetsLoaded) return
  loadingBudgets.value = true
  try {
    const api = useApi()
    // budgets.value = await api.get('budgets')
    budgets.value = [
      { id: 'family-monthly', name: 'Family Monthly Budget' },
      { id: 'personal-food', name: 'Personal Food Budget' },
    ]
    budgetsLoaded = true
  } finally {
    loadingBudgets.value = false
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

watch(budgetLink, (value) => {
  if (value === 'linked') loadBudgets()
})

watch(expenseType, (value) => {
  if (value === 'group') loadGroups()
})

watch(splitExpense, (value) => {
  if (value) loadGroups()
})

function resetForm() {
  name.value = ''
  amount.value = ''
  date.value = ''
  categoryId.value = ''
  description.value = ''
  showDescription.value = false
  budgetLink.value = 'standalone'
  budgetId.value = ''
  expenseType.value = 'personal'
  groupId.value = ''
  splitExpense.value = false
  splitGroupId.value = ''
  errors.value = {}
  localServerMessage.value = null
  localServerErrors.value = null
}

watch(isOpen, (open) => {
  if (open) {
    resetForm()
    loadItems()
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
  if (!name.value.trim()) errors.value.name = 'Select or create an expense item'
  if (!amount.value) errors.value.amount = 'Amount is required'
  if (!date.value) errors.value.date = 'Date is required'
  if (!categoryId.value) errors.value.categoryId = 'Select a category'

  if (budgetLink.value === 'linked' && !budgetId.value) {
    errors.value.budgetId = 'Select a budget'
  }

  if (budgetLink.value === 'standalone' && expenseType.value === 'group' && !groupId.value) {
    errors.value.groupId = 'Select a group'
  }

  if (splitExpense.value && !splitGroupId.value) {
    errors.value.splitGroupId = 'Select a group to split with'
  }

  return Object.keys(errors.value).length === 0
}

function handleSubmit() {
  localServerMessage.value = null
  if (!validate()) return

  const payload: ExpensePayload = {
    name: name.value,
    amount: amount.value,
    date: date.value,
    categoryId: categoryId.value,
    description: description.value || undefined,
    budgetLink: budgetLink.value,
    splitExpense: splitExpense.value,
  }

  if (budgetLink.value === 'linked') {
    payload.budgetId = budgetId.value
  } else {
    payload.expenseType = expenseType.value
    if (expenseType.value === 'group') {
      payload.groupId = groupId.value
    }
  }

  if (splitExpense.value) {
    payload.splitGroupId = splitGroupId.value
  }

  emit('submit', payload)
}
</script>

<template>
  <Modal v-model="isOpen" title="Add Expense" subtitle="Record a new expense.">
    <form class="space-y-5" novalidate @submit.prevent="handleSubmit">
      <div
        v-if="localServerMessage && !localServerErrors"
        class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"
      >
        {{ localServerMessage }}
      </div>

      <!-- 1. Expense name (select existing item, or create a new one) -->
      <div>
        <div class="mb-1.5 flex items-center justify-between">
          <label for="expense-name" class="block text-sm font-medium text-slate-700">Expense name</label>
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
          id="expense-name"
          v-model="name"
          class="w-full rounded-xl border bg-white px-4 py-3 text-sm text-slate-900 transition focus:outline-none focus:ring-2"
          :class="errors.name || localServerErrors?.name
            ? 'border-rose-300 focus:border-rose-400 focus:ring-rose-200'
            : 'border-slate-200 focus:border-emerald-500 focus:ring-emerald-500/30'"
          @change="clearServerError('name')"
        >
          <option value="" disabled>{{ loadingItems ? 'Loading items…' : 'Select an item' }}</option>
          <option v-for="i in items" :key="i.id" :value="i.name">{{ i.name }}</option>
        </select>
        <p v-if="errors.name || localServerErrors?.name" class="mt-1.5 text-xs text-rose-600">
          {{ errors.name || localServerErrors?.name }}
        </p>
        <p v-if="items.length === 0 && !loadingItems" class="mt-1.5 text-xs text-slate-400">
          No items yet — use "Create item" above to add one.
        </p>
      </div>

      <!-- 2. Amount / 3. Date -->
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label for="expense-amount" class="mb-1.5 block text-sm font-medium text-slate-700">Amount (₦)</label>
          <div class="relative">
            <span class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-sm text-slate-400">₦</span>
            <input
              id="expense-amount"
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
          <label for="expense-date" class="mb-1.5 block text-sm font-medium text-slate-700">Date</label>
          <input
            id="expense-date"
            v-model="date"
            type="date"
            class="w-full rounded-xl border px-4 py-3 text-sm text-slate-900 transition focus:outline-none focus:ring-2"
            :class="errors.date || localServerErrors?.date
              ? 'border-rose-300 focus:border-rose-400 focus:ring-rose-200'
              : 'border-slate-200 focus:border-emerald-500 focus:ring-emerald-500/30'"
            @change="clearServerError('date')"
          />
          <p v-if="errors.date || localServerErrors?.date" class="mt-1.5 text-xs text-rose-600">
            {{ errors.date || localServerErrors?.date }}
          </p>
        </div>
      </div>

      <!-- 4. Category -->
      <div>
        <div class="mb-1.5 flex items-center justify-between">
          <label for="expense-category" class="block text-sm font-medium text-slate-700">Category</label>
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
          id="expense-category"
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
              <label for="expense-description" class="block text-sm font-medium text-slate-700">Description (optional)</label>
              <button
                type="button"
                class="text-xs font-medium text-slate-400 hover:text-slate-600"
                @click="showDescription = false; description = ''"
              >
                Remove
              </button>
            </div>
            <textarea
              id="expense-description"
              v-model="description"
              placeholder="Any extra details"
              rows="3"
              autofocus
              class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 transition focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/30"
            />
          </div>
        </Transition>
      </div>

      <!-- 6. Link to a budget, or standalone -->
      <div>
        <label class="mb-1.5 block text-sm font-medium text-slate-700">Is there a budget for this expense?</label>
        <div class="grid grid-cols-2 gap-2">
          <button
            type="button"
            class="rounded-xl border px-3 py-2.5 text-sm font-medium transition"
            :class="budgetLink === 'linked' ? 'border-indigo-400 bg-indigo-50 text-indigo-700' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
            @click="budgetLink = 'linked'"
          >
            Yes
          </button>
          <button
            type="button"
            class="rounded-xl border px-3 py-2.5 text-sm font-medium transition"
            :class="budgetLink === 'standalone' ? 'border-indigo-400 bg-indigo-50 text-indigo-700' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
            @click="budgetLink = 'standalone'"
          >
            No
          </button>
        </div>
      </div>

      <!-- 7. Budget select (only when linked) -->
      <Transition name="expand" mode="out-in">
        <div v-if="budgetLink === 'linked'" key="linked" class="rounded-2xl bg-slate-50 p-4">
          <label for="budget-select" class="mb-1.5 block text-sm font-medium text-slate-700">Budget</label>
          <select
            id="budget-select"
            v-model="budgetId"
            class="w-full rounded-xl border bg-white px-4 py-3 text-sm text-slate-900 transition focus:outline-none focus:ring-2"
            :class="errors.budgetId || localServerErrors?.budgetId
              ? 'border-rose-300 focus:border-rose-400 focus:ring-rose-200'
              : 'border-slate-200 focus:border-emerald-500 focus:ring-emerald-500/30'"
            @change="clearServerError('budgetId')"
          >
            <option value="" disabled>{{ loadingBudgets ? 'Loading budgets…' : 'Select a budget' }}</option>
            <option v-for="b in budgets" :key="b.id" :value="b.id">{{ b.name }}</option>
          </select>
          <p v-if="errors.budgetId || localServerErrors?.budgetId" class="mt-1.5 text-xs text-rose-600">
            {{ errors.budgetId || localServerErrors?.budgetId }}
          </p>
        </div>

        <!-- 8. Personal or group (only when standalone) -->
        <div v-else key="standalone" class="space-y-5 rounded-2xl bg-slate-50 p-4">
          <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Is this a personal or group expense?</label>
            <div class="grid grid-cols-2 gap-2">
              <button
                type="button"
                class="rounded-xl border bg-white px-3 py-2.5 text-sm font-medium transition"
                :class="expenseType === 'personal' ? 'border-indigo-400 bg-indigo-50 text-indigo-700' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
                @click="expenseType = 'personal'"
              >
                Personal
              </button>
              <button
                type="button"
                class="rounded-xl border bg-white px-3 py-2.5 text-sm font-medium transition"
                :class="expenseType === 'group' ? 'border-indigo-400 bg-indigo-50 text-indigo-700' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
                @click="expenseType = 'group'"
              >
                Group
              </button>
            </div>
          </div>

          <!-- 10. Group select (only when group) -->
          <Transition name="expand">
            <div v-if="expenseType === 'group'">
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
          </Transition>
        </div>
      </Transition>

      <!-- Optional: split this expense with a group -->
      <div>
        <label class="flex items-center gap-2.5 text-sm font-medium text-slate-700">
          <input
            v-model="splitExpense"
            type="checkbox"
            class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500/30"
          />
          Do you want to Split this expense? (Optional)
        </label>

        <Transition name="expand">
          <div v-if="splitExpense" class="mt-3 rounded-2xl bg-slate-50 p-4">
            <label for="split-group-select" class="mb-1.5 block text-sm font-medium text-slate-700">Split with group</label>
            <select
              id="split-group-select"
              v-model="splitGroupId"
              class="w-full rounded-xl border bg-white px-4 py-3 text-sm text-slate-900 transition focus:outline-none focus:ring-2"
              :class="errors.splitGroupId || localServerErrors?.splitGroupId
                ? 'border-rose-300 focus:border-rose-400 focus:ring-rose-200'
                : 'border-slate-200 focus:border-emerald-500 focus:ring-emerald-500/30'"
              @change="clearServerError('splitGroupId')"
            >
              <option value="" disabled>{{ loadingGroups ? 'Loading groups…' : 'Select a group' }}</option>
              <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
            </select>
            <p v-if="errors.splitGroupId || localServerErrors?.splitGroupId" class="mt-1.5 text-xs text-rose-600">
              {{ errors.splitGroupId || localServerErrors?.splitGroupId }}
            </p>
          </div>
        </Transition>
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
          {{ loading ? 'Saving…' : 'Add Expense' }}
        </button>
      </div>
    </form>
  </Modal>

  <CategoryFormModal v-model="showCategoryModal" type="expense" @created="handleCategoryCreated" />

  <ItemFormModal v-model="showItemModal" type="expense" @created="handleItemCreated" />
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