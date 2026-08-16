<script setup lang="ts">
import Modal from '~/components/ui/Modal.vue'
import { useApi } from '~/composables/useApi'
import { useAuthStore } from '~/stores/auth'

export interface GroupMemberItem {
  id?: string
  group_id?: string
  user_id?: string | null
  fullname: string
  email?: string | null
  phone_number?: string | null
  permission?: 'viewer' | 'contributor' | 'editor' | 'full_access'
  status?: string
}

interface AppUser {
  id: string
  fullname: string
  email: string
  phone_number?: string | null
}

interface Props {
  loading?: boolean
  groupId: string
  groupOwnerId?: string | null
  initialData?: GroupMemberItem | null
  serverMessage?: string | null
  serverErrors?: Record<string, string> | null
}

const props = withDefaults(defineProps<Props>(), {
  loading: false,
  groupOwnerId: null,
  initialData: null,
  serverMessage: null,
  serverErrors: null,
})

const authStore = useAuthStore()
const isOpen = defineModel<boolean>({ default: false })

const emit = defineEmits<{
  saved: []
}>()

const isEditMode = computed(() => !!props.initialData?.id)

const isGroupOwner = computed(() => {
  if (!props.groupOwnerId || !authStore.user?.id) return true
  return String(props.groupOwnerId) === String(authStore.user.id)
})

const selectedUserId = ref<string | null>(null)
const fullname = ref('')
const email = ref('')
const phoneNumber = ref('')
const permission = ref<'viewer' | 'contributor' | 'editor' | 'full_access'>('viewer')

// App user live search
const userQuery = ref('')
const searchResults = ref<AppUser[]>([])
const searchingUsers = ref(false)
const showUserDropdown = ref(false)
let searchDebounce: any = null

const errors = ref<{
  fullname?: string
  permission?: string
}>({})

const localServerMessage = ref<string | null>(props.serverMessage)
const localServerErrors = ref<Record<string, string> | null>(props.serverErrors)
const submitting = ref(false)

const permissionOptions = [
  {
    id: 'viewer',
    title: 'Viewer (Read Only)',
    summary: 'Read Only',
    description: 'Can view group content and details only. (Default)',
    badgeClass: 'bg-slate-100 text-slate-700 border-slate-200',
  },
  {
    id: 'contributor',
    title: 'Contributor',
    summary: 'Read, Write',
    description: 'Can view group content and add new items.',
    badgeClass: 'bg-blue-50 text-blue-700 border-blue-200',
  },
  {
    id: 'editor',
    title: 'Editor',
    summary: 'Read, Write, Update',
    description: 'Can view, add, and edit group content.',
    badgeClass: 'bg-amber-50 text-amber-700 border-amber-200',
  },
  {
    id: 'full_access',
    title: 'Full Access',
    summary: 'Read, Write, Update, Delete',
    description: 'Can view, add, edit, and delete items & manage member settings.',
    badgeClass: 'bg-emerald-50 text-emerald-700 border-emerald-200',
  },
]

function onUserSearchInput() {
  clearTimeout(searchDebounce)
  if (!userQuery.value || userQuery.value.trim().length < 2) {
    searchResults.value = []
    showUserDropdown.value = false
    return
  }

  searchDebounce = setTimeout(async () => {
    searchingUsers.value = true
    try {
      const api = useApi()
      const res: any = await api.get(`users/search?query=${encodeURIComponent(userQuery.value.trim())}`)
      searchResults.value = Array.isArray(res) ? res : (res?.data || [])
      showUserDropdown.value = searchResults.value.length > 0
    } catch (err) {
      console.error('Failed to search users:', err)
    } finally {
      searchingUsers.value = false
    }
  }, 250)
}

function selectAppUser(user: AppUser) {
  selectedUserId.value = user.id
  fullname.value = user.fullname
  email.value = user.email || ''
  phoneNumber.value = user.phone_number || ''
  userQuery.value = user.fullname
  showUserDropdown.value = false
}

function clearSelectedUser() {
  selectedUserId.value = null
  fullname.value = ''
  email.value = ''
  phoneNumber.value = ''
  userQuery.value = ''
  searchResults.value = []
  showUserDropdown.value = false
}

function populateForm() {
  if (props.initialData) {
    const d = props.initialData
    selectedUserId.value = d.user_id || null
    fullname.value = d.fullname || ''
    email.value = d.email || ''
    phoneNumber.value = d.phone_number || ''
    userQuery.value = d.fullname || ''
    permission.value = d.permission || 'viewer'
  } else {
    selectedUserId.value = null
    fullname.value = ''
    email.value = ''
    phoneNumber.value = ''
    userQuery.value = ''
    permission.value = 'viewer'
  }
  searchResults.value = []
  showUserDropdown.value = false
  errors.value = {}
  localServerMessage.value = null
  localServerErrors.value = null
}

watch(isOpen, (open) => {
  if (open) populateForm()
})

watch(() => props.initialData, () => {
  if (isOpen.value) populateForm()
}, { deep: true })

watch(() => props.serverMessage, (msg) => (localServerMessage.value = msg))
watch(() => props.serverErrors, (errs) => {
  localServerErrors.value = errs ? { ...errs } : null
})

function validate() {
  errors.value = {}
  if (!fullname.value.trim() && !email.value.trim()) {
    errors.value.fullname = 'Select a registered user or enter member details'
  }
  return Object.keys(errors.value).length === 0
}

async function handleSubmit() {
  localServerMessage.value = null

  if (!isGroupOwner.value) {
    localServerMessage.value = 'Forbidden: Only the original group creator/owner can add or edit members.'
    return
  }

  if (!validate()) return

  submitting.value = true
  try {
    const api = useApi()
    const payload = {
      user_id: selectedUserId.value,
      fullname: fullname.value.trim() || email.value.trim(),
      email: email.value.trim() || null,
      phone_number: phoneNumber.value.trim() || null,
      permission: permission.value,
    }

    if (props.initialData?.id) {
      await api.put(`groups/${props.groupId}/members/${props.initialData.id}`, payload)
    } else {
      await api.post(`groups/${props.groupId}/members`, payload)
    }

    emit('saved')
    isOpen.value = false
  } catch (error: any) {
    const apiError = error || {}
    localServerMessage.value = apiError.message || 'Failed to save group member.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <Modal
    v-model="isOpen"
    :z-index="60"
    :title="isEditMode ? 'Edit Group Member' : 'Add Group Member'"
    :subtitle="isEditMode ? 'Update member permission or details.' : 'Search app users or add member details to grant group access.'"
  >
    <form class="space-y-5" novalidate @submit.prevent="handleSubmit">
      <div
        v-if="localServerMessage"
        class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"
      >
        {{ localServerMessage }}
      </div>

      <!-- App User Live Search Field (when adding a member) -->
      <div v-if="!isEditMode" class="relative">
        <label for="search-app-user" class="mb-1.5 block text-sm font-medium text-slate-700">
          Find App User (Search Name, Email, or Phone)
        </label>
        <div class="relative">
          <input
            id="search-app-user"
            v-model="userQuery"
            type="text"
            placeholder="Type user's name, email or phone number…"
            class="w-full rounded-xl border border-slate-200 bg-white py-3 pl-4 pr-10 text-sm text-slate-900 placeholder:text-slate-400 transition focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"
            @input="onUserSearchInput"
          />
          <button
            v-if="userQuery"
            type="button"
            class="absolute inset-y-0 right-3 flex items-center text-slate-400 hover:text-slate-600"
            @click="clearSelectedUser"
          >
            ✕
          </button>
        </div>

        <!-- Search Results Dropdown -->
        <div
          v-if="showUserDropdown && searchResults.length > 0"
          class="absolute z-20 mt-1 max-h-48 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white p-1.5 shadow-lg"
        >
          <div
            v-for="u in searchResults"
            :key="u.id"
            class="flex cursor-pointer items-center justify-between rounded-lg p-2 hover:bg-indigo-50/70 transition"
            @click="selectAppUser(u)"
          >
            <div>
              <p class="text-sm font-semibold text-slate-900">{{ u.fullname }}</p>
              <p class="text-xs text-slate-500">{{ u.email }} <span v-if="u.phone_number">• {{ u.phone_number }}</span></p>
            </div>
            <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-bold text-indigo-700">Select</span>
          </div>
        </div>

        <!-- Selected App User Badge -->
        <div v-if="selectedUserId" class="mt-2 flex items-center justify-between rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-800">
          <span class="flex items-center gap-1.5">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="M20 6L9 17l-5-5" />
            </svg>
            App User Linked: {{ fullname }} ({{ email }})
          </span>
          <button type="button" class="text-emerald-700 hover:text-emerald-900" @click="clearSelectedUser">Clear</button>
        </div>
      </div>

      <!-- Member Details (Auto-filled or custom) -->
      <div>
        <label for="member-fullname" class="mb-1.5 block text-sm font-medium text-slate-700">Full Name</label>
        <input
          id="member-fullname"
          v-model="fullname"
          type="text"
          placeholder="e.g. Jane Doe"
          class="w-full rounded-xl border bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 transition focus:outline-none focus:ring-2"
          :class="errors.fullname || localServerErrors?.fullname
            ? 'border-rose-300 focus:border-rose-400 focus:ring-rose-200'
            : 'border-slate-200 focus:border-emerald-500 focus:ring-emerald-500/30'"
        />
        <p v-if="errors.fullname || localServerErrors?.fullname" class="mt-1.5 text-xs text-rose-600">
          {{ errors.fullname || localServerErrors?.fullname }}
        </p>
      </div>

      <!-- Email & Phone Number -->
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
          <label for="member-email" class="mb-1.5 block text-sm font-medium text-slate-700">Email Address (Optional)</label>
          <input
            id="member-email"
            v-model="email"
            type="email"
            placeholder="jane@example.com"
            class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 transition focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/30"
          />
        </div>

        <div>
          <label for="member-phone" class="mb-1.5 block text-sm font-medium text-slate-700">Phone Number (Optional)</label>
          <input
            id="member-phone"
            v-model="phoneNumber"
            type="tel"
            placeholder="+234 800 000 0000"
            class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 transition focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/30"
          />
        </div>
      </div>

      <!-- Layman Permission Selector -->
      <div>
        <label class="mb-2 block text-sm font-medium text-slate-700">
          Member Permission
        </label>
        <div class="grid grid-cols-1 gap-2.5">
          <label
            v-for="opt in permissionOptions"
            :key="opt.id"
            class="relative flex cursor-pointer items-start rounded-2xl border p-3.5 transition"
            :class="permission === opt.id
              ? 'border-indigo-500 bg-indigo-50/50 ring-2 ring-indigo-500/20'
              : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50/50'"
          >
            <input
              v-model="permission"
              type="radio"
              name="permission-option"
              :value="opt.id"
              class="sr-only"
            />

            <div class="flex h-5 items-center">
              <span
                class="flex h-4 w-4 items-center justify-center rounded-full border transition"
                :class="permission === opt.id ? 'border-indigo-600 bg-indigo-600' : 'border-slate-300 bg-white'"
              >
                <span v-if="permission === opt.id" class="h-1.5 w-1.5 rounded-full bg-white" />
              </span>
            </div>

            <div class="ml-3 flex-1">
              <div class="flex items-center justify-between">
                <span class="text-sm font-semibold text-slate-900">{{ opt.title }}</span>
                <span class="rounded-full px-2 py-0.5 text-[11px] font-medium border" :class="opt.badgeClass">
                  {{ opt.summary }}
                </span>
              </div>
              <p class="mt-0.5 text-xs text-slate-500">{{ opt.description }}</p>
            </div>
          </label>
        </div>
      </div>

      <!-- Buttons -->
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
          :disabled="submitting || !isGroupOwner"
          class="rounded-full bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-70"
          :title="!isGroupOwner ? 'Only the group owner can add or edit members' : ''"
        >
          {{ submitting ? 'Saving…' : (isEditMode ? 'Save Member' : 'Add Member') }}
        </button>
      </div>
    </form>
  </Modal>
</template>
