<script setup lang="ts">
import Modal from '~/components/ui/Modal.vue'
import CategoryFormModal, { type CategoryOption } from '~/components/ui/CategoryFormModal.vue'
import ItemFormModal, { type ItemOption } from '~/components/ui/ItemFormModal.vue'
import { useApi } from '~/composables/useApi'

export interface InitialNeedData {
  id?: string
  name?: string
  purpose?: string | null
  itemId?: string
  item_id?: string
  type?: 'personal' | 'group'
  visibilityType?: 'all_members' | 'selected_individuals'
  visibility_type?: 'all_members' | 'selected_individuals'
  visibleUserIds?: string[]
  visible_user_ids?: string[]
  amount?: string | number
  categoryId?: string
  category_id?: string
  category?: { id: string; category_name: string } | null
  startDate?: string | null
  start_date?: string | null
  endDate?: string | null
  end_date?: string | null
  groupId?: string | null
  group_id?: string | null
  group?: { id: string; group_name: string } | null
  allowMemberContribution?: boolean
  allow_member_contribution?: boolean
  status?: string
}

interface Props {
  loading?: boolean
  serverMessage?: string | null
  serverErrors?: Record<string, string> | null
  initialData?: InitialNeedData | null
}

const props = withDefaults(defineProps<Props>(), {
  loading: false,
  serverMessage: null,
  serverErrors: null,
  initialData: null,
})

const isOpen = defineModel<boolean>({ default: false })

export interface SingleNeedPayload {
  id?: string
  name: string
  purpose?: string
  type: 'personal' | 'group'
  visibilityType?: 'all_members' | 'selected_individuals'
  visibleUserIds?: string[]
  amount: string
  categoryId: string
  itemId?: string
  startDate?: string
  endDate?: string
  groupId?: string
  allowMemberContribution?: boolean
  status?: string
}

export interface NeedPayload {
  needs?: SingleNeedPayload[]
  // Single need backward compatibility
  id?: string
  name?: string
  purpose?: string
  type?: 'personal' | 'group'
  visibilityType?: 'all_members' | 'selected_individuals'
  visibleUserIds?: string[]
  amount?: string
  categoryId?: string
  itemId?: string
  startDate?: string
  endDate?: string
  groupId?: string
  allowMemberContribution?: boolean
  status?: string
}

const emit = defineEmits<{
  submit: [payload: NeedPayload]
}>()

const isEditMode = computed(() => !!props.initialData?.id)

interface GroupMemberOption {
  id: string
  user_id?: string
  fullname: string
  email?: string
}

// Need Form Row Interface for Bulk Array Creation
interface NeedFormRow {
  selectedItemId: string
  name: string
  purpose: string
  type: 'personal' | 'group'
  visibilityType: 'all_members' | 'selected_individuals'
  visibleUserIds: string[]
  amount: string
  categoryId: string
  startDate: string
  endDate: string
  groupId: string
  status: string
  groupMembers: GroupMemberOption[]
  loadingMembers: boolean
}

function createEmptyRow(): NeedFormRow {
  return {
    selectedItemId: '',
    name: '',
    purpose: '',
    type: 'personal',
    visibilityType: 'all_members',
    visibleUserIds: [],
    amount: '',
    categoryId: '',
    startDate: '',
    endDate: '',
    groupId: '',
    status: 'pending',
    groupMembers: [],
    loadingMembers: false,
  }
}

const needRows = ref<NeedFormRow[]>([createEmptyRow()])
const activeRowIndexIndexForModal = ref<number>(0)

const showCategoryModal = ref(false)
const showItemModal = ref(false)

const errors = ref<Record<string, string>>({})
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

interface GroupOption {
  id: string
  name: string
}
const groups = ref<GroupOption[]>([])
const loadingGroups = ref(false)
let groupsLoaded = false

async function loadItems() {
  if (itemsLoaded) return
  loadingItems.value = true
  try {
    const api = useApi()
    const res: any = await api.get('items?type=need')
    const list = Array.isArray(res) ? res : (res?.data || [])
    items.value = list.map((i: any) => ({
      id: i.id,
      name: i.name,
    }))
    itemsLoaded = true
  } catch (err) {
    console.error('Failed to load items', err)
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
    console.error('Failed to load categories', err)
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
    console.error('Failed to load groups', err)
  } finally {
    loadingGroups.value = false
  }
}

async function fetchRowGroupMembers(row: NeedFormRow) {
  if (!row.groupId) return
  row.loadingMembers = true
  try {
    const api = useApi()
    const res: any = await api.get(`groups/${row.groupId}/members`)
    const members = Array.isArray(res) ? res : (res?.data || [])
    row.groupMembers = members.map((m: any) => ({
      id: m.user_id || m.id,
      user_id: m.user_id || m.id,
      fullname: m.fullname || m.name || m.email || 'Group Member',
      email: m.email,
    }))
  } catch (err) {
    console.error('Failed to fetch group members for need visibility:', err)
  } finally {
    row.loadingMembers = false
  }
}

function truncateName(str: string, max = 20) {
  if (!str) return ''
  return str.length > max ? str.slice(0, max) + '…' : str
}

function addNeedRow() {
  needRows.value.push(createEmptyRow())
}

function removeNeedRow(index: number) {
  if (needRows.value.length > 1) {
    needRows.value.splice(index, 1)
  }
}

function handleCategoryCreated(category: CategoryOption) {
  categories.value.push(category)
  if (needRows.value[activeRowIndexIndexForModal.value]) {
    needRows.value[activeRowIndexIndexForModal.value].categoryId = category.id
  }
}

function handleItemCreated(item: ItemOption) {
  items.value.push(item)
  const targetRow = needRows.value[activeRowIndexIndexForModal.value]
  if (targetRow) {
    targetRow.selectedItemId = item.id
    targetRow.name = item.name
  }
}

function handleItemChange(index: number) {
  const row = needRows.value[index]
  if (!row) return
  const found = items.value.find((i) => i.id === row.selectedItemId)
  if (found) {
    row.name = found.name
  }
}

function toggleVisibleUser(row: NeedFormRow, userId: string) {
  const idx = row.visibleUserIds.indexOf(userId)
  if (idx > -1) {
    row.visibleUserIds.splice(idx, 1)
  } else {
    row.visibleUserIds.push(userId)
  }
}

function populateForm() {
  if (props.initialData) {
    const d = props.initialData
    const visibleArr = Array.isArray(d.visibleUserIds || d.visible_user_ids)
      ? (d.visibleUserIds || d.visible_user_ids)!
      : []

    const row: NeedFormRow = {
      selectedItemId: d.itemId || d.item_id || '',
      name: d.name || '',
      purpose: d.purpose || '',
      type: d.type || 'personal',
      visibilityType: d.visibilityType || d.visibility_type || 'all_members',
      visibleUserIds: visibleArr,
      amount: d.amount ? String(d.amount) : '',
      categoryId: d.categoryId || d.category_id || d.category?.id || '',
      startDate: d.startDate || d.start_date || '',
      endDate: d.endDate || d.end_date || '',
      groupId: d.groupId || d.group_id || d.group?.id || '',
      status: d.status || 'pending',
      groupMembers: [],
      loadingMembers: false,
    }

    needRows.value = [row]

    if (d.type === 'group' && d.groupId) {
      loadGroups()
      fetchRowGroupMembers(row)
    }
  } else {
    needRows.value = [createEmptyRow()]
  }
  errors.value = {}
  localServerMessage.value = null
  localServerErrors.value = null
}

watch(isOpen, (open) => {
  if (open) {
    populateForm()
    loadItems()
    loadCategories()
  }
})

watch(() => props.initialData, () => {
  if (isOpen.value) populateForm()
}, { deep: true })

watch(() => props.serverMessage, (msg) => (localServerMessage.value = msg))
watch(() => props.serverErrors, (errs) => {
  localServerErrors.value = errs ? { ...errs } : null
})

function validate(): boolean {
  errors.value = {}
  let valid = true

  needRows.value.forEach((row, index) => {
    if (!row.name.trim()) {
      errors.value[`row_${index}_name`] = 'Select or create a need item'
      valid = false
    }
    if (!row.amount) {
      errors.value[`row_${index}_amount`] = 'Amount is required'
      valid = false
    }
    if (!row.categoryId) {
      errors.value[`row_${index}_categoryId`] = 'Select a category'
      valid = false
    }
    if (row.type === 'group' && !row.groupId) {
      errors.value[`row_${index}_groupId`] = 'Select a group'
      valid = false
    }
    if (row.type === 'group' && row.visibilityType === 'selected_individuals' && row.visibleUserIds.length === 0) {
      errors.value[`row_${index}_visibility`] = 'Select at least one member who can see this need.'
      valid = false
    }
  })

  return valid
}

function handleSubmit() {
  localServerMessage.value = null
  if (!validate()) return

  if (isEditMode.value) {
    const single = needRows.value[0]
    const payload: NeedPayload = {
      id: props.initialData?.id,
      name: single.name,
      purpose: single.purpose || undefined,
      type: single.type,
      visibilityType: single.visibilityType,
      visibleUserIds: single.visibilityType === 'selected_individuals' ? single.visibleUserIds : undefined,
      amount: single.amount,
      categoryId: single.categoryId,
      itemId: single.selectedItemId || undefined,
      startDate: single.startDate || undefined,
      endDate: single.endDate || undefined,
      status: single.status,
    }
    if (single.type === 'group') payload.groupId = single.groupId
    emit('submit', payload)
  } else {
    // Bulk array submission
    const list: SingleNeedPayload[] = needRows.value.map((r) => {
      const p: SingleNeedPayload = {
        name: r.name,
        purpose: r.purpose || undefined,
        type: r.type,
        visibilityType: r.visibilityType,
        visibleUserIds: r.visibilityType === 'selected_individuals' ? r.visibleUserIds : undefined,
        amount: r.amount,
        categoryId: r.categoryId,
        itemId: r.selectedItemId || undefined,
        startDate: r.startDate || undefined,
        endDate: r.endDate || undefined,
        status: r.status,
      }
      if (r.type === 'group') p.groupId = r.groupId
      return p
    })

    emit('submit', { needs: list })
  }
}
</script>

<template>
  <Modal
    v-model="isOpen"
    max-width="max-w-2xl"
    :title="isEditMode ? 'Edit Need' : 'Create Needs'"
    :subtitle="isEditMode ? 'Update your personal or shared need details and visibility.' : 'Create one or multiple essential personal and group needs.'"
  >
    <form class="space-y-6" novalidate @submit.prevent="handleSubmit">
      <div
        v-if="localServerMessage"
        class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"
      >
        {{ localServerMessage }}
      </div>

      <!-- Need Entries Array List -->
      <div v-for="(row, idx) in needRows" :key="idx" class="relative space-y-4 rounded-2xl border border-slate-200/80 bg-slate-50/50 p-4">
        <div class="flex items-center justify-between border-b border-slate-200/60 pb-2">
          <span class="text-xs font-bold text-slate-700 uppercase tracking-wider">
            Need Entry #{{ idx + 1 }}
          </span>
          <button
            v-if="needRows.length > 1 && !isEditMode"
            type="button"
            class="text-xs font-semibold text-rose-600 hover:text-rose-700"
            @click="removeNeedRow(idx)"
          >
            Remove Entry
          </button>
        </div>

        <!-- Need Name Selection / Create Item Modal Link -->
        <div>
          <div class="mb-1 flex items-center justify-between">
            <label :for="`need-name-${idx}`" class="block text-xs font-semibold text-slate-700">Need Name / Item</label>
            <button
              type="button"
              class="flex items-center gap-1 text-xs font-medium text-emerald-600 hover:text-emerald-700"
              @click="activeRowIndexIndexForModal = idx; showItemModal = true"
            >
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round">
                <path d="M12 5v14M5 12h14" />
              </svg>
              Create item
            </button>
          </div>
          <select
            :id="`need-name-${idx}`"
            v-model="row.selectedItemId"
            class="w-full rounded-xl border bg-white px-3.5 py-2.5 text-xs text-slate-900 transition focus:outline-none focus:ring-2"
            :class="errors[`row_${idx}_name`] ? 'border-rose-300 focus:ring-rose-200' : 'border-slate-200 focus:ring-emerald-500/30'"
            @change="handleItemChange(idx)"
          >
            <option value="" disabled>{{ loadingItems ? 'Loading items…' : 'Select a need item' }}</option>
            <option v-for="i in items" :key="i.id" :value="i.id">{{ i.name }}</option>
          </select>
          <p v-if="errors[`row_${idx}_name`]" class="mt-1 text-xs text-rose-600">{{ errors[`row_${idx}_name`] }}</p>
        </div>

        <!-- Need Amount & Category -->
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <div>
            <label :for="`need-amount-${idx}`" class="mb-1 block text-xs font-semibold text-slate-700">Amount (₦)</label>
            <div class="relative">
              <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-xs text-slate-400">₦</span>
              <input
                :id="`need-amount-${idx}`"
                v-model="row.amount"
                type="number"
                step="0.01"
                placeholder="0.00"
                class="w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-7 pr-3 text-xs text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30"
              />
            </div>
            <p v-if="errors[`row_${idx}_amount`]" class="mt-1 text-xs text-rose-600">{{ errors[`row_${idx}_amount`] }}</p>
          </div>

          <div>
            <div class="mb-1 flex items-center justify-between">
              <label :for="`need-category-${idx}`" class="block text-xs font-semibold text-slate-700">Category</label>
              <button
                type="button"
                class="flex items-center gap-1 text-xs font-medium text-emerald-600 hover:text-emerald-700"
                @click="activeRowIndexIndexForModal = idx; showCategoryModal = true"
              >
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round">
                  <path d="M12 5v14M5 12h14" />
                </svg>
                Create category
              </button>
            </div>
            <select
              :id="`need-category-${idx}`"
              v-model="row.categoryId"
              class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/30"
            >
              <option value="" disabled>{{ loadingCategories ? 'Loading categories…' : 'Select category' }}</option>
              <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
            <p v-if="errors[`row_${idx}_categoryId`]" class="mt-1 text-xs text-rose-600">{{ errors[`row_${idx}_categoryId`] }}</p>
          </div>
        </div>

        <!-- Optional Purpose Field -->
        <div>
          <label :for="`need-purpose-${idx}`" class="mb-1 block text-xs font-semibold text-slate-700">Purpose / Explanation (optional)</label>
          <textarea
            :id="`need-purpose-${idx}`"
            v-model="row.purpose"
            rows="2"
            placeholder="Explain why this need is essential..."
            class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30"
          />
        </div>

        <!-- Type & Group Selection -->
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <div>
            <label class="mb-1 block text-xs font-semibold text-slate-700">Scope Type</label>
            <div class="grid grid-cols-2 gap-2">
              <button
                type="button"
                class="rounded-xl border px-2.5 py-2 text-xs font-semibold transition"
                :class="row.type === 'personal' ? 'border-indigo-400 bg-indigo-50 text-indigo-700' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                @click="row.type = 'personal'"
              >
                Personal
              </button>
              <button
                type="button"
                class="rounded-xl border px-2.5 py-2 text-xs font-semibold transition"
                :class="row.type === 'group' ? 'border-indigo-400 bg-indigo-50 text-indigo-700' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                @click="row.type = 'group'; loadGroups()"
              >
                Group
              </button>
            </div>
          </div>

          <div v-if="row.type === 'group'">
            <label :for="`need-group-${idx}`" class="mb-1 block text-xs font-semibold text-slate-700">Group</label>
            <select
              :id="`need-group-${idx}`"
              v-model="row.groupId"
              class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
              @change="fetchRowGroupMembers(row)"
            >
              <option value="" disabled>{{ loadingGroups ? 'Loading groups…' : 'Select a group' }}</option>
              <option v-for="g in groups" :key="g.id" :value="g.id" :title="g.name">
                {{ truncateName(g.name, 20) }}
              </option>
            </select>
            <p v-if="errors[`row_${idx}_groupId`]" class="mt-1 text-xs text-rose-600">{{ errors[`row_${idx}_groupId`] }}</p>
          </div>
        </div>

        <!-- Group Need Visibility Restriction Controls (Requirement #7) -->
        <div v-if="row.type === 'group' && row.groupId" class="rounded-xl border border-indigo-100 bg-indigo-50/40 p-3.5 space-y-3">
          <div>
            <label class="block text-xs font-bold text-indigo-950 uppercase tracking-wider">
              Who Can See This Need?
            </label>
            <p class="text-[11px] text-indigo-700 mt-0.5">Control visibility within the group.</p>
          </div>

          <div class="grid grid-cols-2 gap-2">
            <button
              type="button"
              class="rounded-xl border p-2.5 text-left text-xs font-semibold transition"
              :class="row.visibilityType === 'all_members' ? 'border-indigo-500 bg-white text-indigo-900 ring-1 ring-indigo-500/20 shadow-2xs' : 'border-slate-200 bg-white/80 text-slate-600 hover:bg-white'"
              @click="row.visibilityType = 'all_members'"
            >
              <span class="block font-bold">Every Group Member</span>
              <span class="text-[10px] text-slate-500 font-normal">All active members can see this need</span>
            </button>

            <button
              type="button"
              class="rounded-xl border p-2.5 text-left text-xs font-semibold transition"
              :class="row.visibilityType === 'selected_individuals' ? 'border-indigo-500 bg-white text-indigo-900 ring-1 ring-indigo-500/20 shadow-2xs' : 'border-slate-200 bg-white/80 text-slate-600 hover:bg-white'"
              @click="row.visibilityType = 'selected_individuals'"
            >
              <span class="block font-bold">Selected Individuals</span>
              <span class="text-[10px] text-slate-500 font-normal">Restrict visibility to specific members</span>
            </button>
          </div>

          <!-- Member Checkboxes when Selected Individuals is active -->
          <div v-if="row.visibilityType === 'selected_individuals'" class="space-y-2 pt-1 border-t border-indigo-100">
            <span class="text-[11px] font-semibold text-slate-700">Select allowed members:</span>

            <div v-if="row.loadingMembers" class="text-xs text-slate-400 py-1">
              Loading members…
            </div>

            <div v-else-if="row.groupMembers.length === 0" class="text-xs text-slate-400 italic py-1">
              No members found in this group.
            </div>

            <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-36 overflow-y-auto pr-1">
              <label
                v-for="m in row.groupMembers"
                :key="m.id"
                class="flex items-center gap-2 rounded-lg bg-white p-2 border border-slate-200/80 cursor-pointer transition hover:border-indigo-300"
              >
                <input
                  type="checkbox"
                  :checked="row.visibleUserIds.includes(m.id)"
                  class="rounded text-indigo-600 focus:ring-indigo-500/20"
                  @change="toggleVisibleUser(row, m.id)"
                />
                <span class="text-xs font-medium text-slate-900 truncate" :title="m.fullname">{{ m.fullname }}</span>
              </label>
            </div>

            <p v-if="errors[`row_${idx}_visibility`]" class="mt-1 text-xs text-rose-600">{{ errors[`row_${idx}_visibility`] }}</p>
          </div>
        </div>
      </div>

      <!-- Add Row Button for Creation Mode -->
      <button
        v-if="!isEditMode"
        type="button"
        class="w-full rounded-2xl border border-dashed border-slate-300 bg-white py-3 text-center text-xs font-bold text-indigo-600 hover:border-indigo-400 hover:bg-indigo-50/50 transition flex items-center justify-center gap-1.5"
        @click="addNeedRow"
      >
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
          <path d="M12 5v14M5 12h14" />
        </svg>
        Add Another Need Entry
      </button>

      <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
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
          class="rounded-full bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-70"
        >
          {{ loading ? 'Saving…' : (isEditMode ? 'Save Changes' : `Save ${needRows.length > 1 ? `${needRows.length} Needs` : 'Need'}`) }}
        </button>
      </div>
    </form>
  </Modal>

  <CategoryFormModal v-model="showCategoryModal" type="need" @created="handleCategoryCreated" />
  <ItemFormModal v-model="showItemModal" type="need" @created="handleItemCreated" />
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