<script setup lang="ts">
import Modal from '~/components/ui/Modal.vue'
import ItemFormModal, { type ItemOption } from '~/components/ui/ItemFormModal.vue'
import { useApi } from '~/composables/useApi'

export interface BudgetItem {
  id: string
  item_id?: string
  name: string
  amount: string
}

interface Props {
  existingItems?: BudgetItem[]
}

const props = withDefaults(defineProps<Props>(), {
  existingItems: () => [],
})

const isOpen = defineModel<boolean>({ default: false })

const emit = defineEmits<{
  submit: [items: BudgetItem[]]
}>()

const items = ref<BudgetItem[]>([])
const error = ref('')
const showItemModal = ref(false)

interface RegisteredItem {
  id: string
  name: string
}

const registeredItems = ref<RegisteredItem[]>([])
const loadingItems = ref(false)
let itemsLoaded = false

async function loadRegisteredItems() {
  if (itemsLoaded) return
  loadingItems.value = true
  try {
    const api = useApi()
    const res: any = await api.get('items?type=budget_item')
    const list = Array.isArray(res) ? res : (res?.data || [])
    registeredItems.value = list.map((i: any) => ({
      id: i.id,
      name: i.name,
    }))
    itemsLoaded = true
  } catch (err) {
    console.error('Failed to load budget line items:', err)
  } finally {
    loadingItems.value = false
  }
}

function newRow(): BudgetItem {
  return { id: crypto.randomUUID(), item_id: '', name: '', amount: '' }
}

watch(isOpen, (open) => {
  if (!open) return
  loadRegisteredItems()
  items.value = props.existingItems.length
    ? props.existingItems.map((i) => ({ ...i }))
    : [newRow()]
  error.value = ''
})

function addRow() {
  items.value.push(newRow())
}

function removeRow(id: string) {
  items.value = items.value.filter((i) => i.id !== id)
}

function handleSelectItemChange(itemRow: BudgetItem, selectedId: string) {
  const found = registeredItems.value.find((r) => r.id === selectedId)
  if (found) {
    itemRow.item_id = found.id
    itemRow.name = found.name
  }
}

function handleItemCreated(newItem: ItemOption) {
  registeredItems.value.push(newItem)
  let targetRow = items.value.find((r) => !r.name.trim())
  if (!targetRow) {
    targetRow = newRow()
    items.value.push(targetRow)
  }
  targetRow.item_id = newItem.id
  targetRow.name = newItem.name
}

const total = computed(() =>
  items.value.reduce((sum, i) => sum + (Number(i.amount) || 0), 0)
)

function handleSubmit() {
  const validItems = items.value.filter((i) => i.name.trim() && i.amount)

  if (validItems.length === 0) {
    error.value = 'Add at least one budget item with a name and amount'
    return
  }

  emit('submit', validItems)
  isOpen.value = false
}
</script>

<template>
  <Modal v-model="isOpen" title="Budget Line Items" subtitle="Break this budget down into sub-items.">
    <div class="space-y-4">
      <div class="flex items-center justify-between border-b border-slate-100 pb-2">
        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Select Line Items</span>
        <button
          type="button"
          class="inline-flex items-center gap-1 text-xs font-medium text-emerald-600 hover:text-emerald-700"
          @click="showItemModal = true"
        >
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round">
            <path d="M12 5v14M5 12h14" />
          </svg>
          Create line item
        </button>
      </div>

      <div class="max-h-72 space-y-3 overflow-y-auto pr-1">
        <div v-for="item in items" :key="item.id" class="flex items-center gap-2">
          <!-- Select registered item -->
          <div class="flex-1 space-y-1">
            <select
              :value="item.item_id || ''"
              class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-900 transition focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/30"
              @change="handleSelectItemChange(item, ($event.target as HTMLSelectElement).value)"
            >
              <option value="" disabled>{{ loadingItems ? 'Loading items…' : 'Select a line item' }}</option>
              <option v-for="r in registeredItems" :key="r.id" :value="r.id">{{ r.name }}</option>
            </select>
          </div>

          <div class="relative w-32 shrink-0">
            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-xs text-slate-400">₦</span>
            <input
              v-model="item.amount"
              type="number"
              step="0.01"
              placeholder="0.00"
              class="w-full rounded-xl border border-slate-200 py-2 text-xs text-slate-900 placeholder:text-slate-400 pl-7 pr-2 transition focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/30"
            />
          </div>

          <button
            type="button"
            class="shrink-0 rounded-lg p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600"
            aria-label="Remove item"
            :disabled="items.length === 1"
            :class="items.length === 1 ? 'opacity-30' : ''"
            @click="removeRow(item.id)"
          >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13" />
            </svg>
          </button>
        </div>
      </div>

      <button
        type="button"
        class="flex items-center gap-1.5 text-xs font-semibold text-indigo-600 hover:text-indigo-700"
        @click="addRow"
      >
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
          <path d="M12 5v14M5 12h14" />
        </svg>
        Add another line item
      </button>

      <p v-if="error" class="text-xs text-rose-600">{{ error }}</p>

      <div class="flex items-center justify-between rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700 font-semibold">
        <span>Total Calculated Budget</span>
        <span class="font-mono">₦{{ total.toLocaleString() }}</span>
      </div>

      <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
        <button
          type="button"
          class="rounded-full px-5 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-50"
          @click="isOpen = false"
        >
          Cancel
        </button>
        <button
          type="button"
          class="rounded-full bg-indigo-600 px-6 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-indigo-700"
          @click="handleSubmit"
        >
          Save Line Items
        </button>
      </div>
    </div>
  </Modal>

  <!-- Create Item Sub-Modal for budget line items -->
  <ItemFormModal v-model="showItemModal" type="budget_item" @created="handleItemCreated" />
</template>