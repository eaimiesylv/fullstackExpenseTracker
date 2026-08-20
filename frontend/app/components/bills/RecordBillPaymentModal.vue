<script setup lang="ts">
import Modal from '~/components/ui/Modal.vue'
import { useApi } from '~/composables/useApi'

export interface ParticipantItem {
  id: string
  name: string
  email?: string
  amount_assigned: number
  amount_paid: number
  outstanding: number
}

interface Props {
  billId: string | null
  billTitle?: string
  allowPartialPayment?: boolean
  participants: ParticipantItem[]
  loading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  allowPartialPayment: true,
  loading: false,
})

const isOpen = defineModel<boolean>({ default: false })

const emit = defineEmits<{
  submit: [payload: {
    participant_id?: string
    payer_name?: string
    amount: number
    payment_method: string
    payment_date: string
    notes?: string
  }]
}>()

const payerType = ref<'member' | 'guest'>('member')
const memberSearch = ref('')
const selectedParticipantId = ref('')
const guestName = ref('')
const amount = ref('')
const paymentMethod = ref('bank_transfer')
const paymentDate = ref(new Date().toISOString().split('T')[0])
const notes = ref('')

const localError = ref<string | null>(null)

function initSelection() {
  if (props.participants && props.participants.length > 0) {
    payerType.value = 'member'
    const pendingParticipant = props.participants.find((p) => p.outstanding > 0) || props.participants[0]
    selectedParticipantId.value = pendingParticipant?.id || ''
    if (pendingParticipant && pendingParticipant.outstanding > 0) {
      amount.value = String(pendingParticipant.outstanding)
    }
  } else {
    payerType.value = 'guest'
    selectedParticipantId.value = ''
  }
}

watch(isOpen, (open) => {
  if (open) {
    memberSearch.value = ''
    guestName.value = ''
    amount.value = ''
    paymentMethod.value = 'bank_transfer'
    paymentDate.value = new Date().toISOString().split('T')[0]
    notes.value = ''
    localError.value = null
    initSelection()
  }
})

watch(() => props.participants, () => {
  if (isOpen.value) initSelection()
}, { deep: true })

const filteredParticipants = computed(() => {
  if (!memberSearch.value.trim()) return props.participants
  const q = memberSearch.value.trim().toLowerCase()
  return props.participants.filter(
    (p) => (p.name && p.name.toLowerCase().includes(q)) || (p.email && p.email.toLowerCase().includes(q))
  )
})

const selectedParticipant = computed(() => {
  return props.participants.find((p) => p.id === selectedParticipantId.value)
})

watch(selectedParticipantId, (newId) => {
  const p = props.participants.find((item) => item.id === newId)
  if (p && p.outstanding > 0) {
    amount.value = String(p.outstanding)
  }
})

function formatCurrency(val: number) {
  return new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN' }).format(val || 0)
}

function handleSubmit() {
  localError.value = null
  const numAmount = Number(amount.value) || 0

  if (numAmount <= 0) {
    localError.value = 'Please enter a valid payment amount.'
    return
  }

  if (payerType.value === 'member' && !selectedParticipantId.value) {
    localError.value = 'Please select a registered member.'
    return
  }

  if (payerType.value === 'guest' && !guestName.value.trim()) {
    localError.value = 'Please enter the guest contributor name.'
    return
  }

  // Check partial payment restriction if partial payment is disabled
  if (payerType.value === 'member' && selectedParticipant.value) {
    const outstanding = selectedParticipant.value.outstanding
    if (!props.allowPartialPayment && numAmount < outstanding) {
      localError.value = `Partial payment is disabled for this bill. Full outstanding amount (${formatCurrency(outstanding)}) must be paid.`
      return
    }
  }

  emit('submit', {
    participant_id: payerType.value === 'member' ? selectedParticipantId.value : undefined,
    payer_name: payerType.value === 'guest' ? guestName.value.trim() : undefined,
    amount: numAmount,
    payment_method: paymentMethod.value,
    payment_date: paymentDate.value,
    notes: notes.value || undefined,
  })
}
</script>

<template>
  <Modal v-model="isOpen" title="Record Bill Payment" :subtitle="billTitle ? `Record payment for ${billTitle}` : 'Record a payment collected for this bill.'">
    <form class="space-y-4" @submit.prevent="handleSubmit">
      <div v-if="localError" class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs text-rose-700 font-medium">
        {{ localError }}
      </div>

      <!-- Payer Segmented Switcher (Registered Member vs Guest) -->
      <div>
        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">Payer Type</label>
        <div class="grid grid-cols-2 gap-2 rounded-xl bg-slate-100 p-1">
          <button
            type="button"
            class="rounded-lg py-2 text-xs font-semibold transition"
            :class="payerType === 'member' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
            @click="payerType = 'member'"
          >
            Registered Member ({{ participants.length }})
          </button>
          <button
            type="button"
            class="rounded-lg py-2 text-xs font-semibold transition"
            :class="payerType === 'guest' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
            @click="payerType = 'guest'"
          >
            Guest Contributor
          </button>
        </div>
      </div>

      <!-- Select Registered Member with Search Filter -->
      <div v-if="payerType === 'member'" class="space-y-2">
        <label for="member-select" class="block text-sm font-medium text-slate-700">Registered Participant</label>

        <!-- Member Search Filter Input -->
        <div v-if="participants.length > 3" class="relative">
          <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="11" cy="11" r="8" />
              <path d="m21 21-4.3-4.3" />
            </svg>
          </span>
          <input
            id="member-search"
            v-model="memberSearch"
            type="text"
            placeholder="Search participant by name or email…"
            class="w-full rounded-xl border border-slate-200 bg-white py-2 pl-9 pr-3 text-xs text-slate-900 placeholder:text-slate-400 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"
          />
        </div>

        <select
          id="member-select"
          v-model="selectedParticipantId"
          class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 transition focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"
        >
          <option value="" disabled>Select a participant</option>
          <option v-for="p in filteredParticipants" :key="p.id" :value="p.id">
            {{ p.name }} {{ p.email ? `(${p.email})` : '' }} — Outstanding: {{ formatCurrency(p.outstanding) }}
          </option>
        </select>
        <p v-if="filteredParticipants.length === 0" class="text-xs text-amber-600">
          No matching registered members found for this bill.
        </p>
      </div>

      <!-- Guest Name Input -->
      <div v-else>
        <label for="guest-name" class="mb-1.5 block text-sm font-medium text-slate-700">Guest Name</label>
        <input
          id="guest-name"
          v-model="guestName"
          type="text"
          placeholder="e.g. Samuel (Guest Contributor)"
          class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"
        />
      </div>

      <!-- Amount & Date -->
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label for="pay-amount" class="mb-1.5 block text-sm font-medium text-slate-700">Amount Paid (₦)</label>
          <div class="relative">
            <span class="pointer-events-none absolute inset-y-0 left-3.5 flex items-center text-sm text-slate-400">₦</span>
            <input
              id="pay-amount"
              v-model="amount"
              type="number"
              step="0.01"
              placeholder="0.00"
              class="w-full rounded-xl border border-slate-200 py-3 pl-8 pr-3 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"
            />
          </div>
        </div>

        <div>
          <label for="pay-date" class="mb-1.5 block text-sm font-medium text-slate-700">Payment Date</label>
          <input
            id="pay-date"
            v-model="paymentDate"
            type="date"
            class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-900 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"
          />
        </div>
      </div>

      <!-- Payment Method -->
      <div>
        <label for="pay-method" class="mb-1.5 block text-sm font-medium text-slate-700">Payment Method</label>
        <select
          id="pay-method"
          v-model="paymentMethod"
          class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"
        >
          <option value="bank_transfer">Bank Transfer</option>
          <option value="cash">Cash</option>
          <option value="card">Debit/Credit Card</option>
          <option value="pos">POS</option>
        </select>
      </div>

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
          class="rounded-full bg-emerald-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:opacity-70"
        >
          {{ loading ? 'Saving…' : 'Record Payment' }}
        </button>
      </div>
    </form>
  </Modal>
</template>
