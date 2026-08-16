<script setup lang="ts">
import FormModal, { type FormField } from '~/components/ui/FormModal.vue'
import GroupMemberModal, { type GroupMemberItem } from '~/components/ui/GroupMemberModal.vue'
import Modal from '~/components/ui/Modal.vue'
import Pagination from '~/components/ui/Pagination.vue'
import { useApi } from '~/composables/useApi'
import { useAuthStore } from '~/stores/auth'

definePageMeta({
  middleware: ['auth'],
  title: 'Groups',
  subtitle: 'Manage your shared groups and collaborate with members.',
})

const authStore = useAuthStore()

interface GroupItem {
  id: string
  group_name: string
  description?: string | null
  owner_id?: string
  status?: string
  members_count?: number
  members?: GroupMemberItem[]
  created_at?: string
}

const groups = ref<GroupItem[]>([])
const loadingGroups = ref(true)

// Pagination state
const currentPage = ref(1)
const lastPage = ref(1)
const totalGroups = ref(0)
const perPage = ref(9)

const showGroupModal = ref(false)
const editingGroup = ref<GroupItem | null>(null)
const creatingGroup = ref(false)
const groupErrorMessage = ref<string | null>(null)
const groupFieldErrors = ref<Record<string, string> | null>(null)

const showDeleteGroupModal = ref(false)
const deletingGroupItem = ref<GroupItem | null>(null)
const deletingGroup = ref(false)

// Member management state
const activeGroupForMembers = ref<GroupItem | null>(null)
const members = ref<GroupMemberItem[]>([])
const loadingMembers = ref(false)
const memberSearchQuery = ref('')
const memberPermissionFilter = ref<string>('all')

const showMemberModal = ref(false)
const editingMember = ref<GroupMemberItem | null>(null)

const showDeleteMemberModal = ref(false)
const deletingMemberItem = ref<GroupMemberItem | null>(null)
const deletingMember = ref(false)

const showPermissionGuide = ref(false)

const isOwnerOfActiveGroup = computed(() => {
  if (!activeGroupForMembers.value) return false
  const activeG = activeGroupForMembers.value
  const user = authStore.user

  const ownerId = activeG.owner_id || (activeG as any).owner?.id

  // 1. Match logged-in user id with group owner_id
  if (user?.id && ownerId && String(ownerId) === String(user.id)) {
    return true
  }

  // 2. Match logged-in user email with group owner email
  const groupOwnerEmail = (activeG as any).owner?.email
  if (user?.email && groupOwnerEmail && groupOwnerEmail.toLowerCase() === user.email.toLowerCase()) {
    return true
  }

  // 3. Match logged-in user with owner member record in members array
  if (members.value && members.value.length > 0) {
    const ownerMember = members.value.find((m) => m.role === 'owner')
    if (ownerMember) {
      if (user?.id && ownerMember.user_id && String(ownerMember.user_id) === String(user.id)) return true
      if (user?.email && ownerMember.email && ownerMember.email.toLowerCase() === user.email.toLowerCase()) return true
    }
  }

  // 4. Fallback if user object is still initializing: if members has owner, default to owner access
  if (!user?.id && !user?.email && members.value && members.value.length > 0) {
    const ownerMember = members.value.find((m) => m.role === 'owner')
    if (ownerMember) return true
  }

  return false
})

const groupFields: FormField[] = [
  { name: 'group_name', label: 'Group name', type: 'text', placeholder: 'e.g. Family Savings', required: true },
  { name: 'description', label: 'Description (optional)', type: 'textarea', placeholder: 'What is this group for?' },
]

async function ensureUserProfileLoaded() {
  if (!authStore.user) {
    try {
      const api = useApi()
      const userRes: any = await api.get('user')
      const userData = userRes?.data || userRes
      if (userData && (userData.id || userData.email)) {
        authStore.setAuth(userData, authStore.token || 'authenticated')
      }
    } catch (e) {
      console.error('Failed to load user profile:', e)
    }
  }
}

async function fetchGroups(page = 1) {
  loadingGroups.value = true
  try {
    const api = useApi()
    const res: any = await api.get(`groups?page=${page}&per_page=${perPage.value}`)
    groups.value = Array.isArray(res) ? res : (res?.data || [])

    if (res?.meta) {
      currentPage.value = res.meta.current_page || 1
      lastPage.value = res.meta.last_page || 1
      totalGroups.value = res.meta.total || 0
      perPage.value = res.meta.per_page || 9
    }
  } catch (err) {
    console.error('Failed to fetch groups:', err)
  } finally {
    loadingGroups.value = false
  }
}

onMounted(async () => {
  await ensureUserProfileLoaded()
  fetchGroups(1)
})

const totalMembersCount = computed(() => {
  return groups.value.reduce((acc, g) => acc + (g.members_count || g.members?.length || 0), 0)
})

const filteredMembers = computed(() => {
  return members.value.filter((m) => {
    if (memberPermissionFilter.value !== 'all' && m.permission !== memberPermissionFilter.value) {
      return false
    }
    if (memberSearchQuery.value.trim()) {
      const q = memberSearchQuery.value.toLowerCase()
      const nameMatch = m.fullname.toLowerCase().includes(q)
      const emailMatch = (m.email || '').toLowerCase().includes(q)
      const phoneMatch = (m.phone_number || '').toLowerCase().includes(q)
      if (!nameMatch && !emailMatch && !phoneMatch) return false
    }
    return true
  })
})

function openCreateGroupModal() {
  editingGroup.value = null
  groupErrorMessage.value = null
  groupFieldErrors.value = null
  showGroupModal.value = true
}

function openEditGroupModal(group: GroupItem) {
  editingGroup.value = group
  groupErrorMessage.value = null
  groupFieldErrors.value = null
  showGroupModal.value = true
}

async function handleSaveGroup(values: Record<string, string>) {
  creatingGroup.value = true
  groupErrorMessage.value = null
  groupFieldErrors.value = null

  try {
    const api = useApi()
    if (editingGroup.value?.id) {
      await api.put(`groups/${editingGroup.value.id}`, values)
    } else {
      await api.post('groups', values)
    }
    showGroupModal.value = false
    editingGroup.value = null
    await fetchGroups(currentPage.value)
  } catch (error: any) {
    const apiError = error || {}
    groupErrorMessage.value = apiError.message || 'Something went wrong. Please try again.'
    const errors = apiError.errors
    if (errors && typeof errors === 'object') {
      groupFieldErrors.value = Object.fromEntries(
        Object.entries(errors).map(([key, value]) => [
          key,
          Array.isArray(value) ? String(value[0]) : String(value),
        ])
      )
    }
  } finally {
    creatingGroup.value = false
  }
}

function promptDeleteGroup(group: GroupItem) {
  deletingGroupItem.value = group
  showDeleteGroupModal.value = true
}

async function confirmDeleteGroup() {
  if (!deletingGroupItem.value) return
  deletingGroup.value = true
  try {
    const api = useApi()
    await api.delete(`groups/${deletingGroupItem.value.id}`)
    showDeleteGroupModal.value = false
    deletingGroupItem.value = null
    if (activeGroupForMembers.value?.id === deletingGroupItem.value?.id) {
      activeGroupForMembers.value = null
    }
    await fetchGroups(currentPage.value)
  } catch (err) {
    console.error('Failed to delete group:', err)
  } finally {
    deletingGroup.value = false
  }
}

// Member Management Functions
async function openMemberManagement(group: GroupItem) {
  activeGroupForMembers.value = group
  memberSearchQuery.value = ''
  memberPermissionFilter.value = 'all'
  await ensureUserProfileLoaded()
  fetchMembers(group.id)
}

async function fetchMembers(groupId: string) {
  loadingMembers.value = true
  try {
    const api = useApi()
    const res: any = await api.get(`groups/${groupId}/members`)
    members.value = Array.isArray(res) ? res : (res?.data || [])
  } catch (err) {
    console.error('Failed to fetch members:', err)
  } finally {
    loadingMembers.value = false
  }
}

function openAddMemberModal() {
  editingMember.value = null
  showMemberModal.value = true
}

function openEditMemberModal(member: GroupMemberItem) {
  editingMember.value = member
  showMemberModal.value = true
}

async function updateMemberPermission(member: GroupMemberItem, newPermission: string) {
  if (!activeGroupForMembers.value || member.permission === newPermission) return
  const oldPermission = member.permission
  member.permission = newPermission as any

  try {
    const api = useApi()
    await api.put(`groups/${activeGroupForMembers.value.id}/members/${member.id}`, {
      permission: newPermission,
    })
    fetchMembers(activeGroupForMembers.value.id)
    fetchGroups(currentPage.value)
  } catch (err) {
    member.permission = oldPermission
    console.error('Failed to update permission:', err)
  }
}

function promptRemoveMember(member: GroupMemberItem) {
  deletingMemberItem.value = member
  showDeleteMemberModal.value = true
}

async function confirmRemoveMember() {
  if (!activeGroupForMembers.value || !deletingMemberItem.value?.id) return
  deletingMember.value = true
  try {
    const api = useApi()
    await api.delete(`groups/${activeGroupForMembers.value.id}/members/${deletingMemberItem.value.id}`)
    showDeleteMemberModal.value = false
    deletingMemberItem.value = null
    await fetchMembers(activeGroupForMembers.value.id)
    await fetchGroups(currentPage.value)
  } catch (err) {
    console.error('Failed to remove member:', err)
  } finally {
    deletingMember.value = false
  }
}

function getPermissionBadgeClass(permission?: string) {
  switch (permission) {
    case 'full_access':
      return 'bg-emerald-50 text-emerald-700 border-emerald-200'
    case 'editor':
      return 'bg-amber-50 text-amber-700 border-amber-200'
    case 'contributor':
      return 'bg-blue-50 text-blue-700 border-blue-200'
    case 'viewer':
    default:
      return 'bg-slate-100 text-slate-700 border-slate-200'
  }
}

function formatPermissionLabel(permission?: string) {
  switch (permission) {
    case 'full_access':
      return 'Full Access'
    case 'editor':
      return 'Editor'
    case 'contributor':
      return 'Contributor'
    case 'viewer':
    default:
      return 'Viewer (Read Only)'
  }
}

function formatDate(dateStr?: string | null) {
  if (!dateStr) return 'Recently'
  return new Date(dateStr).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header Action Bar -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h2 class="text-xl font-bold text-slate-900">Your Groups</h2>
        <p class="text-sm text-slate-500">Manage shared groups, collaborate, and configure member permissions.</p>
      </div>

      <div class="flex items-center gap-2">
        <button
          type="button"
          class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
          @click="showPermissionGuide = !showPermissionGuide"
        >
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
          </svg>
          {{ showPermissionGuide ? 'Hide Permission Guide' : 'Permission Guide' }}
        </button>

        <button
          type="button"
          class="inline-flex items-center justify-center gap-2 rounded-full bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/30"
          @click="openCreateGroupModal"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
            <path d="M12 5v14M5 12h14" />
          </svg>
          Create Group
        </button>
      </div>
    </div>

    <!-- Summary Stats Bar -->
    <div v-if="!loadingGroups && groups.length > 0" class="grid grid-cols-1 gap-4 sm:grid-cols-3">
      <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
        <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Total Groups</span>
        <div class="mt-1 flex items-baseline justify-between">
          <span class="text-2xl font-bold text-slate-900">{{ totalGroups || groups.length }}</span>
          <span class="text-xs font-medium text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full">Active</span>
        </div>
      </div>
      <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
        <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Page Members</span>
        <div class="mt-1 flex items-baseline justify-between">
          <span class="text-2xl font-bold text-indigo-600">{{ totalMembersCount }}</span>
          <span class="text-xs font-medium text-slate-500">On current page</span>
        </div>
      </div>
      <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
        <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Permission Levels</span>
        <div class="mt-1 flex items-baseline justify-between">
          <span class="text-2xl font-bold text-emerald-600">4 Levels</span>
          <span class="text-xs font-medium text-slate-500">Viewer to Full Access</span>
        </div>
      </div>
    </div>

    <!-- Layman Permission Guide Card -->
    <Transition name="expand">
      <div v-if="showPermissionGuide" class="rounded-2xl border border-indigo-100 bg-indigo-50/40 p-5 space-y-3">
        <div class="flex items-center justify-between">
          <h3 class="text-sm font-bold text-indigo-900 flex items-center gap-2">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
            </svg>
            Layman Permission Levels Explained
          </h3>
          <span class="text-xs text-indigo-600 font-medium">Default permission: Viewer</span>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <div class="rounded-xl border border-slate-200/80 bg-white p-3.5 shadow-2xs">
            <div class="flex items-center justify-between">
              <span class="text-xs font-bold text-slate-900">Viewer</span>
              <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600 border border-slate-200">Read Only</span>
            </div>
            <p class="mt-1.5 text-xs text-slate-500">Can view group content and details only.</p>
          </div>

          <div class="rounded-xl border border-blue-200/80 bg-white p-3.5 shadow-2xs">
            <div class="flex items-center justify-between">
              <span class="text-xs font-bold text-blue-900">Contributor</span>
              <span class="rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-semibold text-blue-700 border border-blue-200">Read, Write</span>
            </div>
            <p class="mt-1.5 text-xs text-slate-500">Can view group content and add new items.</p>
          </div>

          <div class="rounded-xl border border-amber-200/80 bg-white p-3.5 shadow-2xs">
            <div class="flex items-center justify-between">
              <span class="text-xs font-bold text-amber-900">Editor</span>
              <span class="rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700 border border-amber-200">Read, Write, Update</span>
            </div>
            <p class="mt-1.5 text-xs text-slate-500">Can view, add, and edit group content.</p>
          </div>

          <div class="rounded-xl border border-emerald-200/80 bg-white p-3.5 shadow-2xs">
            <div class="flex items-center justify-between">
              <span class="text-xs font-bold text-emerald-900">Full Access</span>
              <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 border border-emerald-200">Full CRUD</span>
            </div>
            <p class="mt-1.5 text-xs text-slate-500">Can view, add, edit, and delete items & member settings.</p>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Loading Skeletons -->
    <div v-if="loadingGroups" class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
      <div v-for="i in 3" :key="i" class="h-48 animate-pulse rounded-2xl border border-slate-100 bg-slate-50/50 p-5" />
    </div>

    <!-- Empty State -->
    <div
      v-else-if="groups.length === 0"
      class="flex h-full min-h-[350px] flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 p-8 text-center"
    >
      <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-indigo-50 text-indigo-600">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
          <circle cx="9" cy="7" r="4" />
          <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
          <path d="M16 3.13a4 4 0 0 1 0 7.75" />
        </svg>
      </div>
      <h3 class="text-base font-semibold text-slate-900">No Groups Created Yet</h3>
      <p class="mt-1 max-w-sm text-sm text-slate-500">
        Create a group to invite family, friends, or roommates and manage shared expenses together.
      </p>
      <button
        type="button"
        class="mt-5 flex items-center gap-2 rounded-full bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
        @click="openCreateGroupModal"
      >
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
          <path d="M12 5v14M5 12h14" />
        </svg>
        Create Group
      </button>
    </div>

    <!-- Groups Grid -->
    <div v-else class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
      <div
        v-for="group in groups"
        :key="group.id"
        class="group relative flex flex-col justify-between rounded-2xl border border-slate-100 bg-white p-5 shadow-sm transition hover:border-slate-200 hover:shadow-md"
        :class="activeGroupForMembers?.id === group.id ? 'ring-2 ring-indigo-500/30 border-indigo-200' : ''"
      >
        <div>
          <div class="flex items-start justify-between gap-2">
            <h3 class="text-lg font-bold text-slate-900">{{ group.group_name }}</h3>
            <div class="flex items-center gap-1.5">
              <span
                v-if="authStore.user?.id && group.owner_id === authStore.user.id"
                class="rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-bold text-indigo-800 uppercase tracking-wide"
              >
                Owner
              </span>
              <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                  <circle cx="9" cy="7" r="4" />
                </svg>
                {{ group.members_count ?? group.members?.length ?? 1 }} members
              </span>
            </div>
          </div>

          <p v-if="group.description" class="mt-2 text-sm text-slate-500 line-clamp-2">
            {{ group.description }}
          </p>
          <p v-else class="mt-2 text-xs italic text-slate-400">No description provided.</p>

          <!-- Member Previews -->
          <div v-if="group.members && group.members.length > 0" class="mt-4 flex items-center gap-1.5 overflow-hidden">
            <div
              v-for="m in group.members.slice(0, 4)"
              :key="m.id"
              class="flex h-7 w-7 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600 ring-2 ring-white"
              :title="`${m.fullname} (${formatPermissionLabel(m.permission)})`"
            >
              {{ m.fullname ? m.fullname.charAt(0).toUpperCase() : 'M' }}
            </div>
            <span v-if="group.members.length > 4" class="text-xs font-medium text-slate-400">
              +{{ group.members.length - 4 }} more
            </span>
          </div>
        </div>

        <div class="mt-5 border-t border-slate-100 pt-3 flex items-center justify-between">
          <button
            type="button"
            class="inline-flex items-center gap-1.5 text-xs font-semibold text-indigo-600 hover:text-indigo-700"
            @click="openMemberManagement(group)"
          >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
              <path d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
            Manage Members
          </button>

          <div v-if="!authStore.user?.id || group.owner_id === authStore.user?.id" class="flex items-center gap-1 opacity-90 sm:opacity-0 sm:group-hover:opacity-100 transition">
            <button
              type="button"
              class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-50 hover:text-indigo-600 transition"
              title="Edit Group"
              @click="openEditGroupModal(group)"
            >
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 20h9M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z" />
              </svg>
            </button>
            <button
              type="button"
              class="rounded-lg p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600 transition"
              title="Delete Group"
              @click="promptDeleteGroup(group)"
            >
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Reusable Server-Side Pagination Bar -->
    <Pagination
      v-if="!loadingGroups && lastPage > 1"
      v-model:current-page="currentPage"
      :last-page="lastPage"
      :total="totalGroups"
      :per-page="perPage"
      :loading="loadingGroups"
      @change="fetchGroups"
    />

    <!-- Active Group Member Management Reader Panel / Modal -->
    <Modal
      v-if="activeGroupForMembers"
      :model-value="!!activeGroupForMembers"
      :z-index="50"
      max-width="max-w-3xl"
      :title="`Group Members — ${activeGroupForMembers.group_name}`"
      subtitle="View group members, invite collaborators, edit permissions, or remove members."
      @update:model-value="activeGroupForMembers = null"
    >
      <div class="space-y-4">
        <!-- Controls Bar: Search & Permission Filter & Add Member -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-3">
          <div class="relative flex-1">
            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8" />
                <path d="m21 21-4.3-4.3" />
              </svg>
            </span>
            <input
              v-model="memberSearchQuery"
              type="text"
              placeholder="Search member name or contact…"
              class="w-full rounded-xl border border-slate-200 bg-white py-2 pl-9 pr-3 text-xs text-slate-900 placeholder:text-slate-400 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"
            />
          </div>

          <div class="flex items-center gap-2">
            <select
              v-model="memberPermissionFilter"
              class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
            >
              <option value="all">All Permissions</option>
              <option value="viewer">Viewer Only</option>
              <option value="contributor">Contributor Only</option>
              <option value="editor">Editor Only</option>
              <option value="full_access">Full Access Only</option>
            </select>

            <button
              v-if="isOwnerOfActiveGroup"
              type="button"
              class="inline-flex items-center gap-1.5 rounded-full bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-indigo-700 whitespace-nowrap"
              @click="openAddMemberModal"
            >
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                <path d="M12 5v14M5 12h14" />
              </svg>
              Add Member
            </button>
            <span
              v-else
              class="rounded-full bg-slate-100 px-3 py-1.5 text-[11px] font-semibold text-slate-500 border border-slate-200"
            >
              View Only Mode
            </span>
          </div>
        </div>

        <!-- Members Loading -->
        <div v-if="loadingMembers" class="space-y-2 py-4">
          <div v-for="i in 3" :key="i" class="h-12 animate-pulse rounded-xl bg-slate-50" />
        </div>

        <!-- Empty Members -->
        <div v-else-if="members.length === 0" class="py-8 text-center text-slate-400">
          <p class="text-sm">No members in this group yet.</p>
          <button
            v-if="isOwnerOfActiveGroup"
            type="button"
            class="mt-3 text-xs font-semibold text-indigo-600 hover:text-indigo-700"
            @click="openAddMemberModal"
          >
            + Add the first member
          </button>
        </div>

        <!-- Filter Empty State -->
        <div v-else-if="filteredMembers.length === 0" class="py-6 text-center text-slate-400">
          <p class="text-xs">No members match your search or filter.</p>
          <button
            type="button"
            class="mt-2 text-xs font-semibold text-indigo-600 hover:text-indigo-700"
            @click="memberSearchQuery = ''; memberPermissionFilter = 'all'"
          >
            Reset search
          </button>
        </div>

        <!-- Members Table / List with Invitation / Added Dates -->
        <div v-else class="space-y-3 max-h-[420px] overflow-y-auto pr-1">
          <div
            v-for="member in filteredMembers"
            :key="member.id"
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between rounded-xl border border-slate-100 bg-white p-3.5 transition hover:border-slate-200"
          >
            <div class="flex items-center gap-3">
              <div class="flex h-9 w-9 items-center justify-center rounded-full bg-indigo-50 font-bold text-indigo-700 text-sm ring-2 ring-indigo-500/10">
                {{ member.fullname ? member.fullname.charAt(0).toUpperCase() : 'M' }}
              </div>
              <div>
                <div class="flex items-center gap-2">
                  <span class="text-sm font-semibold text-slate-900">{{ member.fullname }}</span>
                  <span
                    v-if="member.role === 'owner'"
                    class="rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-bold text-indigo-800 uppercase tracking-wide"
                  >
                    Owner
                  </span>
                </div>
                <div class="flex items-center gap-2 text-xs text-slate-400 mt-0.5">
                  <span>{{ member.email || member.phone_number || 'No contact details' }}</span>
                  <span>•</span>
                  <span class="text-slate-500 font-medium" :title="member.joined_at || member.created_at || ''">
                    Added {{ formatDate(member.joined_at || member.created_at) }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Permission Switcher & Actions -->
            <div class="flex items-center gap-2 self-end sm:self-auto">
              <!-- Permission dropdown for Owner vs Badge for non-owners -->
              <div v-if="isOwnerOfActiveGroup && member.role !== 'owner'" class="relative">
                <select
                  :value="member.permission || 'viewer'"
                  class="appearance-none rounded-full border px-3 py-1 pr-6 text-xs font-semibold cursor-pointer transition focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                  :class="getPermissionBadgeClass(member.permission)"
                  @change="updateMemberPermission(member, ($event.target as HTMLSelectElement).value)"
                >
                  <option value="viewer" class="bg-white text-slate-900">Viewer (Read Only)</option>
                  <option value="contributor" class="bg-white text-slate-900">Contributor (Read, Write)</option>
                  <option value="editor" class="bg-white text-slate-900">Editor (Read, Write, Update)</option>
                  <option value="full_access" class="bg-white text-slate-900">Full Access (Read, Write, Update, Delete)</option>
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-2 flex items-center">
                  <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="m6 9 6 6 6-6" />
                  </svg>
                </div>
              </div>

              <span
                v-else
                class="rounded-full px-3 py-1 text-xs font-semibold border"
                :class="getPermissionBadgeClass(member.permission)"
              >
                {{ formatPermissionLabel(member.permission) }}
              </span>

              <!-- Remove Member Trash Button -->
              <button
                v-if="isOwnerOfActiveGroup && member.role !== 'owner'"
                type="button"
                class="rounded-lg p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600 transition"
                title="Remove member"
                @click="promptRemoveMember(member)"
              >
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                  <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                </svg>
              </button>
            </div>
          </div>
        </div>

        <div class="flex justify-end border-t border-slate-100 pt-4">
          <button
            type="button"
            class="rounded-full bg-slate-100 px-5 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-200 transition"
            @click="activeGroupForMembers = null"
          >
            Done
          </button>
        </div>
      </div>
    </Modal>

    <!-- Create / Edit Group Modal -->
    <FormModal
      v-model="showGroupModal"
      :title="editingGroup ? 'Edit Group' : 'Create Group'"
      :fields="groupFields"
      :loading="creatingGroup"
      :server-message="groupErrorMessage"
      :server-errors="groupFieldErrors"
      @submit="handleSaveGroup"
    />

    <!-- Add / Edit Member Modal -->
    <GroupMemberModal
      v-if="activeGroupForMembers"
      v-model="showMemberModal"
      :group-id="activeGroupForMembers.id"
      :group-owner-id="activeGroupForMembers.owner_id"
      :initial-data="editingMember"
      @saved="fetchMembers(activeGroupForMembers.id); fetchGroups(currentPage)"
    />

    <!-- Delete Group Confirmation Modal -->
    <Modal
      v-model="showDeleteGroupModal"
      :z-index="70"
      title="Delete Group"
      subtitle="Are you sure you want to delete this group? All group data will be removed."
    >
      <div class="space-y-4">
        <p v-if="deletingGroupItem" class="font-semibold text-slate-900">
          {{ deletingGroupItem.group_name }}
        </p>
        <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
          <button
            type="button"
            class="rounded-full px-5 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50"
            @click="showDeleteGroupModal = false"
          >
            Cancel
          </button>
          <button
            type="button"
            :disabled="deletingGroup"
            class="rounded-full bg-rose-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-700 disabled:opacity-70"
            @click="confirmDeleteGroup"
          >
            {{ deletingGroup ? 'Deleting…' : 'Delete Group' }}
          </button>
        </div>
      </div>
    </Modal>

    <!-- Remove Member Confirmation Modal -->
    <Modal
      v-model="showDeleteMemberModal"
      :z-index="70"
      title="Remove Member"
      subtitle="Are you sure you want to remove this member from the group?"
    >
      <div class="space-y-4">
        <p v-if="deletingMemberItem" class="font-semibold text-slate-900">
          {{ deletingMemberItem.fullname }}
        </p>
        <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
          <button
            type="button"
            class="rounded-full px-5 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50"
            @click="showDeleteMemberModal = false"
          >
            Cancel
          </button>
          <button
            type="button"
            :disabled="deletingMember"
            class="rounded-full bg-rose-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-700 disabled:opacity-70"
            @click="confirmRemoveMember"
          >
            {{ deletingMember ? 'Removing…' : 'Remove Member' }}
          </button>
        </div>
      </div>
    </Modal>
  </div>
</template>

<style scoped>
.expand-enter-active,
.expand-leave-active {
  transition: all 0.25s ease;
}
.expand-enter-from,
.expand-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}
</style>
