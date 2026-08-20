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

export interface ExpenseRow {
  id: string
  name: string
  amount: string
  date: string
  categoryId: string
  description: string
  budgetLink: 'linked' | 'standalone'
  budgetId: string
  expenseType: 'personal' | 'group'
  groupId: string
}

export interface ExpensePayload {
  expenses: Array<{
    name: string
    amount: string
    date: string
    categoryId: string
    description?: string
    budgetLink: 'linked' | 'standalone'
    budgetId?: string
    expenseType?: 'personal' | 'group'
    groupId?: string
  }>
}

const emit = defineEmits<{
  submit: [payload: ExpensePayload]
}>()

const rows = ref<ExpenseRow[]>([])
const showItemModal = ref(false)
const showCategoryModal = ref(false)
const targetRowIndexForModal = ref<number | null>(null)

const localServerMessage = ref<string | null>(props.serverMessage)
const localServerErrors = ref<Record<string, string> | null>(props.serverErrors)
const validationErrorMsg = ref<string | null>(null)

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

const budgets = ref<Option[]>([])
const loadingBudgets = ref(false)
let budgetsLoaded = false

const groups = ref<Option[]>([])
const loadingGroups = ref(false)
let groupsLoaded = false

function createEmptyRow(): ExpenseRow {
  return {
    id: Math.random().toString(36).substring(2, 9),
    name: '',
    amount: '',
    date: new Date().toISOString().split('T')[0],
    categoryId: categories.value.length > 0 ? categories.value[0].id : '',
    description: '',
    budgetLink: 'standalone',
    budgetId: '',
    expenseType: 'personal',
    groupId: '',
  }
}

async function loadItems() {
  if (itemsLoaded) return
  loadingItems.value = true
  try {
    const api = useApi()
    const res: any = await api.get('items?type=expense')
    const list = Array.isArray(res) ? res : (res?.data || [])
    items.value = list.map((i: any) => ({
      id: i.id,
      name: i.name,
    }))
    itemsLoaded = true
  } catch (err) {
    console.error('Failed to load expense items:', err)
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

async function loadBudgets() {
  if (budgetsLoaded) return
  loadingBudgets.value = true
  try {
    const api = useApi()
    const res: any = await api.get('budgets?per_page=100')
    const list = Array.isArray(res) ? res : (res?.data || [])
    budgets.value = list.map((b: any) => ({
      id: b.id,
      name: b.name || b.budget_name,
    }))
    budgetsLoaded = true
  } catch (err) {
    console.error('Failed to load budgets:', err)
  } finally {
    loadingBudgets.value = false
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

function truncateName(str: string, max = 20) {
  if (!str) return ''
  return str.length > max ? str.slice(0, max) + '…' : str
}

function handleCategoryCreated(category: CategoryOption) {
  categories.value.push(category)
  if (targetRowIndexForModal.value !== null && rows.value[targetRowIndexForModal.value]) {
    rows.value[targetRowIndexForModal.value].categoryId = category.id
  } else {
    rows.value.forEach((r) => {
      if (!r.categoryId) r.categoryId = category.id
    })
  }
}

function handleItemCreated(item: ItemOption) {
  items.value.push(item)
  if (targetRowIndexForModal.value !== null && rows.value[targetRowIndexForModal.value]) {
    rows.value[targetRowIndexForModal.value].name = item.name
  }
}

function addRow() {
  rows.value.push(createEmptyRow())
}

function handleBudgetLinkChange(row: ExpenseRow) {
  if (row.budgetLink === 'linked') {
    loadBudgets()
  } else if (row.expenseType === 'group') {
    loadGroups()
  }
}

function handleExpenseTypeChange(row: ExpenseRow) {
  if (row.expenseType === 'group') {
    loadGroups()
  }
}

function removeRow(index: number) {
  if (rows.value.length === 1) {
    rows.value = [createEmptyRow()]
  } else {
    rows.value.splice(index, 1)
  }
}

function duplicateRow(index: number) {
  const source = rows.value[index]
  if (source) {
    rows.value.splice(index + 1, 0, {
      ...source,
      id: Math.random().toString(36).substring(2, 9),
    })
  }
}

function resetForm() {
  rows.value = [createEmptyRow()]
  localServerMessage.value = null
  localServerErrors.value = null
  validationErrorMsg.value = null
}

watch(isOpen, async (open) => {
  if (open) {
    resetForm()
    await Promise.all([loadItems(), loadCategories(), loadBudgets(), loadGroups()])
    if (categories.value.length > 0 && rows.value[0] && !rows.value[0].categoryId) {
      rows.value[0].categoryId = categories.value[0].id
    }
  }
})

watch(() => props.serverMessage, (msg) => (localServerMessage.value = msg))
watch(() => props.serverErrors, (errs) => {
  localServerErrors.value = errs ? { ...errs } : null
})

const grandTotal = computed(() => {
  return rows.value.reduce((sum, r) => sum + (Number(r.amount) || 0), 0)
})

function validate(): boolean {
  validationErrorMsg.value = null
  if (rows.value.length === 0) {
    validationErrorMsg.value = 'Please add at least one expense row.'
    return false
  }

  for (let i = 0; i < rows.value.length; i++) {
    const r = rows.value[i]
    if (!r.name.trim()) {
      validationErrorMsg.value = `Row #${i + 1}: Expense item name is required.`
      return false
    }
    if (!r.amount || Number(r.amount) <= 0) {
      validationErrorMsg.value = `Row #${i + 1}: Enter a valid positive amount.`
      return false
    }
    if (!r.categoryId) {
      validationErrorMsg.value = `Row #${i + 1}: Select a category.`
      return false
    }
    if (r.budgetLink === 'linked' && !r.budgetId) {
      validationErrorMsg.value = `Row #${i + 1}: Select a budget.`
      return false
    }
    if (r.budgetLink === 'standalone' && r.expenseType === 'group' && !r.groupId) {
      validationErrorMsg.value = `Row #${i + 1}: Select a group for group expense.`
      return false
    }
  }

  return true
}

function handleSubmit() {
  localServerMessage.value = null
  if (!validate()) return

  const formattedExpenses = rows.value.map((r) => ({
    name: r.name.trim(),
    amount: r.amount,
    date: r.date,
    categoryId: r.categoryId,
    description: r.description.trim() || undefined,
    budgetLink: r.budgetLink,
    budgetId: r.budgetLink === 'linked' ? r.budgetId : undefined,
    expenseType: r.budgetLink === 'standalone' ? r.expenseType : undefined,
    groupId: r.budgetLink === 'standalone' && r.expenseType === 'group' ? r.groupId : undefined,
  }))

  emit('submit', { expenses: formattedExpenses })
}
</script>

<template>
  <Modal
    v-model="isOpen"
    :z-index="50"
    max-width="max-w-6xl"
    title="Add Expenses (Excel Landscape Entry)"
    subtitle="Enter multiple expense line items quickly in a spreadsheet-style table layout."
  >
    <form class="space-y-4" novalidate @submit.prevent="handleSubmit">
      <!-- Error Banners -->
      <div
        v-if="localServerMessage || validationErrorMsg"
        class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-semibold text-rose-700 flex items-center justify-between"
      >
        <span>{{ localServerMessage || validationErrorMsg }}</span>
        <button type="button" class="text-rose-500 hover:text-rose-800" @click="localServerMessage = null; validationErrorMsg = null">
          ✕
        </button>
      </div>

      <!-- Landscape Action & Summary Bar -->
      <div class="rounded-2xl border border-indigo-100 bg-indigo-50/50 p-3.5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between shadow-2xs">
        <div class="flex items-center gap-3">
          <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600 font-bold text-white shadow-xs">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
              <polyline points="14 2 14 8 20 8" />
              <line x1="8" y1="13" x2="16" y2="13" />
              <line x1="8" y1="17" x2="16" y2="17" />
            </svg>
          </div>
          <div>
            <span class="text-xs font-bold text-indigo-950 uppercase tracking-wider">Spreadsheet Entry Mode</span>
            <p class="text-xs text-indigo-700">
              {{ rows.length }} expense record{{ rows.length > 1 ? 's' : '' }} configured
            </p>
          </div>
        </div>

        <div class="flex items-center gap-3 self-end sm:self-auto">
          <div class="rounded-xl bg-white px-4 py-2 border border-indigo-100 shadow-2xs text-right">
            <span class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Total Amount</span>
            <div class="text-lg font-bold text-indigo-950">₦{{ grandTotal.toLocaleString('en-NG', { minimumFractionDigits: 2 }) }}</div>
          </div>

          <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-full bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow-xs hover:bg-indigo-700 transition"
            @click="addRow"
          >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4">
              <path d="M12 5v14M5 12h14" />
            </svg>
            + Add Row
          </button>
        </div>
      </div>

      <!-- Excel-Style Landscape Table -->
      <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-2xs">
        <table class="w-full text-left text-xs text-slate-700 border-collapse min-w-[900px]">
          <thead>
            <tr class="border-b border-slate-200 bg-slate-50 font-bold uppercase tracking-wider text-slate-500 text-[11px]">
              <th class="py-3 px-3 text-center w-10">#</th>
              <th class="py-3 px-3 min-w-[200px]">
                Expense Title / Item
              </th>
              <th class="py-3 px-3 w-36">Amount (₦)</th>
              <th class="py-3 px-3 min-w-[160px]">Category</th>
              <th class="py-3 px-3 w-36">Date</th>
              <th class="py-3 px-3 min-w-[180px]">Scope / Budget</th>
              <th class="py-3 px-3 min-w-[160px]">Notes</th>
              <th class="py-3 px-3 text-center w-20">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="(row, index) in rows" :key="row.id" class="hover:bg-slate-50/70 transition">
              <!-- Index -->
              <td class="py-2.5 px-3 text-center font-bold text-slate-400 text-xs">
                {{ index + 1 }}
              </td>

              <!-- Expense Title (Select or custom text input) -->
              <td class="py-2.5 px-3">
                <div class="flex items-center gap-1">
                  <div class="relative flex-1">
                    <input
                      v-model="row.name"
                      type="text"
                      list="expense-items-datalist"
                      placeholder="Type or select expense item…"
                      class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-900 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"
                    />
                    <datalist id="expense-items-datalist">
                      <option v-for="i in items" :key="i.id" :value="i.name" />
                    </datalist>
                  </div>
                  <button
                    type="button"
                    class="rounded-lg p-1.5 text-indigo-600 hover:bg-indigo-50 transition shrink-0"
                    title="Create custom item"
                    @click="targetRowIndexForModal = index; showItemModal = true"
                  >
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                      <path d="M12 5v14M5 12h14" />
                    </svg>
                  </button>
                </div>
              </td>

              <!-- Amount -->
              <td class="py-2.5 px-3">
                <div class="relative">
                  <span class="pointer-events-none absolute inset-y-0 left-2.5 flex items-center text-slate-400 text-xs">₦</span>
                  <input
                    v-model="row.amount"
                    type="number"
                    step="0.01"
                    placeholder="0.00"
                    class="w-full rounded-xl border border-slate-200 py-2 pl-6 pr-2.5 text-xs text-slate-900 font-semibold focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"
                  />
                </div>
              </td>

              <!-- Category -->
              <td class="py-2.5 px-3">
                <select
                  v-model="row.categoryId"
                  class="w-full rounded-xl border border-slate-200 bg-white px-2.5 py-2 text-xs text-slate-900 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"
                >
                  <option value="" disabled>Select category</option>
                  <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
              </td>

              <!-- Date -->
              <td class="py-2.5 px-3">
                <input
                  v-model="row.date"
                  type="date"
                  class="w-full rounded-xl border border-slate-200 px-2.5 py-2 text-xs text-slate-900 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"
                />
              </td>

              <!-- Scope / Budget Link -->
              <td class="py-2.5 px-3 space-y-1.5">
                <div class="flex items-center gap-1 text-[11px]">
                  <select
                    v-model="row.budgetLink"
                    class="rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-[11px] font-semibold text-slate-700 focus:outline-none"
                    @change="handleBudgetLinkChange(row)"
                  >
                    <option value="standalone">Standalone</option>
                    <option value="linked">Link Budget</option>
                  </select>

                  <select
                    v-if="row.budgetLink === 'standalone'"
                    v-model="row.expenseType"
                    class="rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-[11px] font-semibold text-slate-700 focus:outline-none"
                    @change="handleExpenseTypeChange(row)"
                  >
                    <option value="personal">Personal</option>
                    <option value="group">Group</option>
                  </select>
                </div>

                <!-- Secondary selector: Budget list OR Group list -->
                <div v-if="row.budgetLink === 'linked'">
                  <select
                    v-model="row.budgetId"
                    class="w-full rounded-lg border border-indigo-200 bg-indigo-50/50 px-2 py-1 text-[11px] font-medium text-indigo-900 focus:outline-none"
                  >
                    <option value="" disabled>{{ loadingBudgets ? 'Loading…' : 'Select Budget' }}</option>
                    <option v-for="b in budgets" :key="b.id" :value="b.id">{{ b.name }}</option>
                  </select>
                </div>

                <div v-else-if="row.expenseType === 'group'">
                  <select
                    v-model="row.groupId"
                    class="w-full rounded-lg border border-indigo-200 bg-indigo-50/50 px-2 py-1 text-[11px] font-medium text-indigo-900 focus:outline-none"
                  >
                    <option value="" disabled>{{ loadingGroups ? 'Loading…' : 'Select Group' }}</option>
                    <option v-for="g in groups" :key="g.id" :value="g.id" :title="g.name">
                      {{ truncateName(g.name, 20) }}
                    </option>
                  </select>
                </div>
              </td>

              <!-- Description -->
              <td class="py-2.5 px-3">
                <input
                  v-model="row.description"
                  type="text"
                  placeholder="Optional details…"
                  class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-900 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"
                />
              </td>

              <!-- Actions -->
              <td class="py-2.5 px-3 text-center">
                <div class="flex items-center justify-center gap-1">
                  <button
                    type="button"
                    class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-indigo-600 transition"
                    title="Duplicate Row"
                    @click="duplicateRow(index)"
                  >
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <rect x="9" y="9" width="13" height="13" rx="2" ry="2" />
                      <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
                    </svg>
                  </button>
                  <button
                    type="button"
                    class="rounded-lg p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600 transition"
                    title="Remove Row"
                    @click="removeRow(index)"
                  >
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                    </svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Add Row Button & Controls -->
      <div class="flex items-center justify-between border-t border-slate-100 pt-3">
        <button
          type="button"
          class="inline-flex items-center gap-1 text-xs font-semibold text-indigo-600 hover:text-indigo-700"
          @click="addRow"
        >
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="M12 5v14M5 12h14" />
          </svg>
          + Add another row
        </button>

        <div class="flex items-center gap-3">
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
            class="rounded-full bg-indigo-600 px-7 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-70 flex items-center gap-2"
          >
            <span v-if="loading">Saving…</span>
            <span v-else>Save {{ rows.length }} Expense{{ rows.length > 1 ? 's' : '' }}</span>
          </button>
        </div>
      </div>
    </form>
  </Modal>

  <CategoryFormModal v-model="showCategoryModal" type="expense" @created="handleCategoryCreated" />
  <ItemFormModal v-model="showItemModal" type="expense" @created="handleItemCreated" />
</template>