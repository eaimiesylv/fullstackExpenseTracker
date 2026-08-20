<script setup lang="ts">
import Modal from '~/components/ui/Modal.vue'
import { useApi } from '~/composables/useApi'

interface Props {
  billId: string | null
}

const props = defineProps<Props>()
const isOpen = defineModel<boolean>({ default: false })

const emit = defineEmits<{
  recordPayment: [bill: any]
  sendReminder: [bill: any]
}>()

const billDetail = ref<any>(null)
const loading = ref(true)

const participantStatusFilter = ref<'all' | 'full' | 'incomplete' | 'no_payment'>('all')

async function fetchBillDetails() {
  if (!props.billId) return
  loading.value = true
  try {
    const api = useApi()
    const res: any = await api.get(`bills/${props.billId}`)
    billDetail.value = res?.data || res
  } catch (err) {
    console.error('Failed to load bill details:', err)
  } finally {
    loading.value = false
  }
}

watch(() => props.billId, (id) => {
  if (id && isOpen.value) fetchBillDetails()
})

watch(isOpen, (open) => {
  if (open && props.billId) fetchBillDetails()
})

const filteredParticipants = computed(() => {
  if (!billDetail.value?.participants) return []
  if (participantStatusFilter.value === 'all') return billDetail.value.participants
  return billDetail.value.participants.filter(
    (p: any) => p.status === participantStatusFilter.value
  )
})

function formatCurrency(val: string | number) {
  const num = Number(val) || 0
  return new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN' }).format(num)
}

function formatDate(dateStr?: string | null) {
  if (!dateStr) return 'N/A'
  return new Date(dateStr).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

function getStatusBadgeClass(status?: string) {
  const st = (status || 'no_payment').toLowerCase()
  switch (st) {
    case 'full':
    case 'complete':
    case 'completed':
      return 'bg-emerald-50 text-emerald-700 border-emerald-200'
    case 'incomplete':
    case 'partial':
      return 'bg-amber-50 text-amber-700 border-amber-200'
    case 'no_payment':
    default:
      return 'bg-rose-50 text-rose-700 border-rose-200'
  }
}

function getStatusLabel(status?: string) {
  const st = (status || 'no_payment').toLowerCase()
  switch (st) {
    case 'full':
    case 'complete':
    case 'completed':
      return 'Completed'
    case 'incomplete':
    case 'partial':
      return 'Incomplete'
    case 'no_payment':
    default:
      return 'No Payment'
  }
}
</script>

<template>
  <Modal
    v-model="isOpen"
    :z-index="50"
    max-width="max-w-3xl"
    :title="billDetail ? billDetail.title || billDetail.name : 'Bill & Split Details'"
    subtitle="Track who has paid, partial payment counts, and outstanding balances."
  >
    <div v-if="loading" class="space-y-4 py-6">
      <div class="h-20 animate-pulse rounded-2xl bg-slate-50" />
      <div class="h-44 animate-pulse rounded-2xl bg-slate-50" />
    </div>

    <div v-else-if="billDetail" class="space-y-6">
      <!-- Summary Header Grid -->
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-4 rounded-2xl border border-slate-100 bg-slate-50/70 p-4">
        <div>
          <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Bill Amount</span>
          <p class="mt-1 text-xl font-bold text-slate-900">{{ formatCurrency(billDetail.amount) }}</p>
          <p class="text-[11px] text-slate-500 mt-0.5">Due: {{ formatDate(billDetail.due_date) }}</p>
        </div>

        <div>
          <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Collected</span>
          <p class="mt-1 text-xl font-bold text-emerald-600">{{ formatCurrency(billDetail.total_collected) }}</p>
          <p class="text-[11px] text-slate-500 mt-0.5">Collected so far</p>
        </div>

        <div>
          <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Outstanding Balance</span>
          <p class="mt-1 text-xl font-bold text-rose-600">{{ formatCurrency(billDetail.total_outstanding) }}</p>
          <p class="text-[11px] text-slate-500 mt-0.5">Remaining to collect</p>
        </div>

        <div class="flex flex-col items-end justify-between">
          <span class="rounded-full px-3 py-1 text-xs font-bold border capitalize" :class="getStatusBadgeClass(billDetail.computed_status || billDetail.status)">
            {{ getStatusLabel(billDetail.computed_status || billDetail.status) }}
          </span>

          <div class="flex items-center gap-1.5 mt-2">
            <button
              type="button"
              class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-emerald-700"
              @click="emit('recordPayment', billDetail)"
            >
              + Record Payment
            </button>
            <button
              type="button"
              class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-indigo-700"
              @click="emit('sendReminder', billDetail)"
            >
              Remind
            </button>
          </div>
        </div>
      </div>

      <!-- Tabular Participant Breakdown -->
      <div class="space-y-3">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
          <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Participant Breakdown</h4>

          <!-- Participant Status Filter Bar -->
          <div class="flex items-center gap-1 bg-slate-100 p-1 rounded-xl text-xs">
            <button
              type="button"
              class="px-2.5 py-1 rounded-lg font-semibold transition"
              :class="participantStatusFilter === 'all' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-900'"
              @click="participantStatusFilter = 'all'"
            >
              All ({{ billDetail.participants?.length || 0 }})
            </button>
            <button
              type="button"
              class="px-2.5 py-1 rounded-lg font-semibold transition"
              :class="participantStatusFilter === 'full' ? 'bg-white text-emerald-700 shadow-xs' : 'text-slate-500 hover:text-slate-900'"
              @click="participantStatusFilter = 'full'"
            >
              Completed
            </button>
            <button
              type="button"
              class="px-2.5 py-1 rounded-lg font-semibold transition"
              :class="participantStatusFilter === 'incomplete' ? 'bg-white text-amber-700 shadow-xs' : 'text-slate-500 hover:text-slate-900'"
              @click="participantStatusFilter = 'incomplete'"
            >
              Incomplete
            </button>
            <button
              type="button"
              class="px-2.5 py-1 rounded-lg font-semibold transition"
              :class="participantStatusFilter === 'no_payment' ? 'bg-white text-rose-700 shadow-xs' : 'text-slate-500 hover:text-slate-900'"
              @click="participantStatusFilter = 'no_payment'"
            >
              No Payment
            </button>
          </div>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white">
          <table class="w-full text-left text-xs border-collapse">
            <thead class="bg-slate-50 font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200">
              <tr>
                <th scope="col" class="py-3 pl-4 pr-3">Participant Name & Email</th>
                <th scope="col" class="px-3 py-3">Assigned (₦)</th>
                <th scope="col" class="px-3 py-3">Paid (₦)</th>
                <th scope="col" class="px-3 py-3">Outstanding (₦)</th>
                <th scope="col" class="py-3 pl-3 pr-4 text-right">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="p in filteredParticipants" :key="p.id" class="hover:bg-slate-50/70 transition">
                <!-- Name & Email in smaller font size -->
                <td class="py-3 pl-4 pr-3">
                  <div class="flex items-center gap-2.5">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-[10px] font-bold text-indigo-700">
                      {{ (p.name || 'M').slice(0, 2).toUpperCase() }}
                    </span>
                    <div class="flex flex-col">
                      <span class="font-bold text-slate-900 text-xs">{{ p.name }}</span>
                      <span v-if="p.email" class="text-[11px] text-slate-400 font-normal">{{ p.email }}</span>
                    </div>
                  </div>
                </td>

                <td class="px-3 py-3 font-semibold text-slate-800 whitespace-nowrap">
                  {{ formatCurrency(p.amount_assigned) }}
                </td>

                <td class="px-3 py-3 font-bold text-emerald-600 whitespace-nowrap">
                  {{ formatCurrency(p.amount_paid) }}
                </td>

                <td class="px-3 py-3 font-bold text-rose-600 whitespace-nowrap">
                  {{ formatCurrency(p.outstanding) }}
                </td>

                <td class="py-3 pl-3 pr-4 text-right whitespace-nowrap">
                  <span class="rounded-full px-2.5 py-0.5 text-[10px] font-bold border capitalize" :class="getStatusBadgeClass(p.status)">
                    {{ getStatusLabel(p.status) }}
                  </span>
                </td>
              </tr>

              <tr v-if="filteredParticipants.length === 0">
                <td colspan="5" class="py-6 text-center text-xs text-slate-400">
                  No participants match this status filter.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="flex justify-end border-t border-slate-100 pt-4">
        <button
          type="button"
          class="rounded-full bg-slate-100 px-5 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-200 transition"
          @click="isOpen = false"
        >
          Close
        </button>
      </div>
    </div>
  </Modal>
</template>
