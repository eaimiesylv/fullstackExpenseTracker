<script setup lang="ts">
import Modal from '~/components/ui/Modal.vue'
import { useApi } from '~/composables/useApi'

interface Props {
  budgetId: string
  budgetName?: string
  groupId?: string | null
}

const props = defineProps<Props>()
const isOpen = defineModel<boolean>({ default: false })

const emit = defineEmits<{
  saved: []
}>()

const contributorType = ref<'registered' | 'guest'>('registered')
const selectedUserId = ref('')
const guestName = ref('')
const amount = ref('')
const paymentMethod = ref('bank_transfer')
const notes = ref('')
const contributionDate = ref(new Date().toISOString().split('T')[0])

interface UserOption {
  id: string
  name: string
  email?: string
}

const availableUsers = ref<UserOption[]>([])
const loadingUsers = ref(false)

const submitting = ref(false)
const serverMessage = ref<string | null>(null)

async function loadMembersAndUsers() {
  loadingUsers.value = true
  try {
    const api = useApi()
    const me: any = await api.get('user')
    const userList: UserOption[] = [{
      id: me.id,
      name: `${me.fullname || me.email} (You)`,
      email: me.email,
    }]

    if (props.groupId) {
      try {
        const groupRes: any = await api.get(`groups/${props.groupId}`)
        const members = groupRes?.data?.members || groupRes?.members || []
        for (const m of members) {
          if (m.user_id && m.user_id !== me.id) {
            userList.push({
              id: m.user_id,
              name: m.user?.fullname || m.name || m.email,
              email: m.email,
            })
          }
        }
      } catch (err) {
        console.error('Failed to fetch group members:', err)
      }
    }

    availableUsers.value = userList
    if (userList.length > 0 && !selectedUserId.value) {
      selectedUserId.value = userList[0].id
    }
  } catch (err) {
    console.error('Failed to load user info:', err)
  } finally {
    loadingUsers.value = false
  }
}

function reset() {
  contributorType.value = 'registered'
  selectedUserId.value = ''
  guestName.value = ''
  amount.value = ''
  paymentMethod.value = 'bank_transfer'
  notes.value = ''
  contributionDate.value = new Date().toISOString().split('T')[0]
  serverMessage.value = null
}

watch(isOpen, (open) => {
  if (open) {
    reset()
    loadMembersAndUsers()
  }
})

async function handleSubmit() {
  if (!amount.value || Number(amount.value) <= 0) {
    serverMessage.value = 'Please enter a valid contribution amount.'
    return
  }

  if (contributorType.value === 'guest' && !guestName.value.trim()) {
    serverMessage.value = 'Please enter the guest contributor name.'
    return
  }

  submitting.value = true
  serverMessage.value = null

  try {
    const api = useApi()
    await api.post(`budgets/${props.budgetId}/contributions`, {
      amount: amount.value,
      contributor_type: contributorType.value,
      user_id: contributorType.value === 'registered' ? selectedUserId.value : null,
      contributor_name: contributorType.value === 'guest' ? guestName.value.trim() : null,
      payment_method: paymentMethod.value,
      notes: notes.value.trim() || null,
      contribution_date: contributionDate.value,
    })

    emit('saved')
    isOpen.value = false
  } catch (err: any) {
    serverMessage.value = err?.message || 'Failed to record contribution.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <Modal
    v-model="isOpen"
    :z-index="60"
    :title="`Add Contribution — ${budgetName || 'Budget'}`"
    subtitle="Record a contribution from a registered app member or guest contributor."
  >
    <form class="space-y-4" novalidate @submit.prevent="handleSubmit">
      <div
        v-if="serverMessage"
        class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"
      >
        {{ serverMessage }}
      </div>

      <!-- Contributor Type Switcher -->
      <div>
        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">
          Contributor Type
        </label>
        <div class="grid grid-cols-2 gap-2 rounded-xl bg-slate-100 p-1">
          <button
            type="button"
            class="rounded-lg py-2 text-xs font-semibold transition"
            :class="contributorType === 'registered' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
            @click="contributorType = 'registered'"
          >
            Registered Member
          </button>
          <button
            type="button"
            class="rounded-lg py-2 text-xs font-semibold transition"
            :class="contributorType === 'guest' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
            @click="contributorType = 'guest'"
          >
            Guest Contributor
          </button>
        </div>
      </div>

      <!-- If Registered Member -->
      <div v-if="contributorType === 'registered'">
        <label for="contrib-user-select" class="mb-1 block text-sm font-medium text-slate-700">
          Select Member
        </label>
        <select
          id="contrib-user-select"
          v-model="selectedUserId"
          class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"
        >
          <option value="" disabled>{{ loadingUsers ? 'Loading members…' : 'Select a registered user' }}</option>
          <option v-for="u in availableUsers" :key="u.id" :value="u.id">{{ u.name }}</option>
        </select>
      </div>

      <!-- If Guest Contributor -->
      <div v-else>
        <label for="guest-name-input" class="mb-1 block text-sm font-medium text-slate-700">
          Guest Contributor Name
        </label>
        <input
          id="guest-name-input"
          v-model="guestName"
          type="text"
          placeholder="e.g. Uncle Bob, Anonymous Supporter"
          class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"
        />
      </div>

      <div>
        <label for="contrib-amount" class="mb-1 block text-sm font-medium text-slate-700">
          Contribution Amount (₦)
        </label>
        <div class="relative">
          <span class="pointer-events-none absolute inset-y-0 left-3.5 flex items-center text-sm text-slate-400">₦</span>
          <input
            id="contrib-amount"
            v-model="amount"
            type="number"
            step="0.01"
            placeholder="0.00"
            class="w-full rounded-xl border border-slate-200 py-2.5 pl-8 pr-4 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"
          />
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label for="contrib-method" class="mb-1 block text-sm font-medium text-slate-700">Payment Method</label>
          <select
            id="contrib-method"
            v-model="paymentMethod"
            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
          >
            <option value="bank_transfer">Bank Transfer</option>
            <option value="cash">Cash</option>
            <option value="card">Card / Online</option>
            <option value="other">Other</option>
          </select>
        </div>

        <div>
          <label for="contrib-date" class="mb-1 block text-sm font-medium text-slate-700">Date</label>
          <input
            id="contrib-date"
            v-model="contributionDate"
            type="date"
            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
          />
        </div>
      </div>

      <div>
        <label for="contrib-notes" class="mb-1 block text-sm font-medium text-slate-700">Notes (Optional)</label>
        <textarea
          id="contrib-notes"
          v-model="notes"
          rows="2"
          placeholder="e.g. Sent via Mobile App transfer…"
          class="w-full rounded-xl border border-slate-200 bg-white p-3 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"
        />
      </div>

      <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
        <button
          type="button"
          class="rounded-full px-5 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition"
          @click="isOpen = false"
        >
          Cancel
        </button>
        <button
          type="submit"
          :disabled="submitting"
          class="rounded-full bg-indigo-600 px-6 py-2 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700 transition disabled:opacity-70"
        >
          {{ submitting ? 'Recording…' : 'Record Contribution' }}
        </button>
      </div>
    </form>
  </Modal>
</template>
