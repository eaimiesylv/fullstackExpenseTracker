<script setup lang="ts">
import Modal from '~/components/ui/Modal.vue'

interface Props {
  billId: string | null
  billTitle?: string
  loading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  loading: false,
})

const isOpen = defineModel<boolean>({ default: false })

const emit = defineEmits<{
  submit: [payload: {
    reminder_type: 'now' | 'custom'
    frequency?: string
    reminder_start_date?: string
    reminder_interval_days?: number
  }]
}>()

const reminderType = ref<'now' | 'custom'>('now')
const reminderStartDate = ref(new Date().toISOString().split('T')[0])
const reminderIntervalDays = ref(3)

const intervalOptions = [
  { value: 1, label: 'Every 1 day (Daily)' },
  { value: 2, label: 'Every 2 days' },
  { value: 3, label: 'Every 3 days' },
  { value: 5, label: 'Every 5 days' },
  { value: 7, label: 'Every 7 days (Weekly)' },
  { value: 14, label: 'Every 14 days (Bi-weekly)' },
]

watch(isOpen, (open) => {
  if (open) {
    reminderType.value = 'now'
    reminderStartDate.value = new Date().toISOString().split('T')[0]
    reminderIntervalDays.value = 3
  }
})

function handleSubmit() {
  emit('submit', {
    reminder_type: reminderType.value,
    frequency: reminderType.value === 'custom' ? `Every ${reminderIntervalDays.value} days` : undefined,
    reminder_start_date: reminderType.value === 'custom' ? reminderStartDate.value : undefined,
    reminder_interval_days: reminderType.value === 'custom' ? Number(reminderIntervalDays.value) : undefined,
  })
}
</script>

<template>
  <Modal v-model="isOpen" title="Send Email Reminder" :subtitle="billTitle ? `Remind participants for ${billTitle}` : 'Send payment reminder email to pending participants.'">
    <div class="space-y-5">
      <!-- Feature Availability Notice Tip Banner -->
      <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-xs text-amber-900 shadow-2xs flex items-start gap-3">
        <div class="rounded-full bg-amber-100 p-2 text-amber-700 shrink-0">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
            <line x1="12" y1="9" x2="12" y2="13" />
            <line x1="12" y1="17" x2="12.01" y2="17" />
          </svg>
        </div>
        <div class="space-y-1">
          <h4 class="font-bold text-amber-950 text-sm">Feature Not Available</h4>
          <p class="leading-relaxed">
            Automated and instant email reminder features are <strong>not available on your current account tier</strong>. Please contact support to upgrade your subscription or enable email messaging integration for your organization.
          </p>
          <a
            href="mailto:support@expense.app?subject=Upgrade%20Account%20For%20Email%20Reminders"
            class="inline-block mt-1 font-bold text-amber-900 underline hover:text-amber-950"
          >
            Contact Support &rarr;
          </a>
        </div>
      </div>
      <div>
        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">Reminder Option</label>
        <div class="grid grid-cols-2 gap-3">
          <button
            type="button"
            class="flex flex-col items-center justify-center rounded-2xl border p-4 text-center transition"
            :class="reminderType === 'now' ? 'border-indigo-500 bg-indigo-50/70 text-indigo-900 shadow-xs' : 'border-slate-200 hover:bg-slate-50 text-slate-700'"
            @click="reminderType = 'now'"
          >
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="mb-2 text-indigo-600">
              <path d="m22 2-7 20-4-9-9-4Z" />
              <path d="M22 2 11 13" />
            </svg>
            <span class="text-sm font-bold">Send Now</span>
            <span class="mt-1 text-[11px] text-slate-500">Send reminder email immediately to members with incomplete payment</span>
          </button>

          <button
            type="button"
            class="flex flex-col items-center justify-center rounded-2xl border p-4 text-center transition"
            :class="reminderType === 'custom' ? 'border-indigo-500 bg-indigo-50/70 text-indigo-900 shadow-xs' : 'border-slate-200 hover:bg-slate-50 text-slate-700'"
            @click="reminderType = 'custom'"
          >
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="mb-2 text-indigo-600">
              <circle cx="12" cy="12" r="10" />
              <polyline points="12 6 12 12 16 14" />
            </svg>
            <span class="text-sm font-bold">Custom Schedule</span>
            <span class="mt-1 text-[11px] text-slate-500">Set start date & days interval frequency</span>
          </button>
        </div>
      </div>

      <!-- Custom Schedule Controls (Start date & Days interval) -->
      <div v-if="reminderType === 'custom'" class="space-y-4 rounded-2xl border border-slate-100 bg-slate-50/80 p-4">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <!-- Interval Start Date -->
          <div>
            <label for="reminder-start-date" class="mb-1.5 block text-xs font-semibold text-slate-700">Interval Start Date</label>
            <input
              id="reminder-start-date"
              v-model="reminderStartDate"
              type="date"
              class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs text-slate-900 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"
            />
          </div>

          <!-- Days Interval -->
          <div>
            <label for="reminder-interval" class="mb-1.5 block text-xs font-semibold text-slate-700">Reminder Days Interval</label>
            <select
              id="reminder-interval"
              v-model="reminderIntervalDays"
              class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs text-slate-900 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"
            >
              <option v-for="opt in intervalOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </div>
        </div>

        <!-- Target Notice Banner -->
        <div class="rounded-xl border border-indigo-100 bg-indigo-50/60 p-3 text-xs text-indigo-800 flex items-start gap-2">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="shrink-0 mt-0.5 text-indigo-600">
            <circle cx="12" cy="12" r="10" />
            <line x1="12" y1="16" x2="12" y2="12" />
            <line x1="12" y1="8" x2="12.01" y2="8" />
          </svg>
          <p>
            Reminders will automatically be sent <strong>only to members with incomplete or pending payments</strong> starting on <strong>{{ reminderStartDate }}</strong> every <strong>{{ reminderIntervalDays }} days</strong>.
          </p>
        </div>
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
          type="button"
          :disabled="loading"
          class="rounded-full bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-70"
          @click="handleSubmit"
        >
          {{ loading ? 'Saving…' : (reminderType === 'now' ? 'Send Reminder Email Now' : 'Save Custom Schedule') }}
        </button>
      </div>
    </div>
  </Modal>
</template>
