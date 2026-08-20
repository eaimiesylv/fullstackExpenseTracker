<script setup lang="ts">
import Modal from '~/components/ui/Modal.vue'

export interface GroupMember {
  id: string
  name: string
  email?: string
}

interface Props {
  members: GroupMember[]
  totalAmount: string
  existingAmounts?: Record<string, string>
}

const props = withDefaults(defineProps<Props>(), {
  existingAmounts: () => ({}),
})

const isOpen = defineModel<boolean>({ default: false })

const emit = defineEmits<{
  submit: [amounts: Record<string, string>]
}>()

const amounts = ref<Record<string, string>>({})

function suggestEqualSplit() {
  const total = Number(props.totalAmount) || 0
  const count = props.members.length || 1
  const share = count ? (total / count).toFixed(2) : '0'
  amounts.value = Object.fromEntries(props.members.map((m) => [m.id, share]))
}

watch(isOpen, (open) => {
  if (!open) return
  const hasExisting = Object.keys(props.existingAmounts).length > 0
  amounts.value = hasExisting
    ? { ...props.existingAmounts }
    : Object.fromEntries(props.members.map((m) => [m.id, '']))
})

const sum = computed(() =>
  props.members.reduce((total, m) => total + (Number(amounts.value[m.id]) || 0), 0)
)

const difference = computed(() => Number(props.totalAmount || 0) - sum.value)
const matchesTotal = computed(() => Math.abs(difference.value) < 0.01)

function handleSubmit() {
  emit('submit', { ...amounts.value })
  isOpen.value = false
}
</script>

<template>
  <Modal v-model="isOpen" title="Custom Split" subtitle="Set custom amount for each group member.">
    <div class="space-y-4">
      <div class="flex items-center justify-between">
        <p class="text-xs text-slate-500">{{ members.length }} members in group</p>
        <button
          type="button"
          class="text-xs font-medium text-emerald-600 hover:text-emerald-700"
          @click="suggestEqualSplit"
        >
          Split equally as a start
        </button>
      </div>

      <div class="max-h-72 space-y-2.5 overflow-y-auto pr-1">
        <div v-for="member in members" :key="member.id" class="flex items-center justify-between gap-3 rounded-xl border border-slate-100 p-2.5 hover:border-slate-200">
          <div class="flex items-center gap-3 min-w-0">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-xs font-bold text-indigo-700">
              {{ member.name.slice(0, 2).toUpperCase() }}
            </span>
            <div class="flex flex-col min-w-0">
              <span class="text-sm font-semibold text-slate-900 truncate">{{ member.name }}</span>
              <span v-if="member.email" class="text-xs text-slate-400 truncate">{{ member.email }}</span>
            </div>
          </div>

          <div class="relative w-32 shrink-0">
            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-xs text-slate-400">₦</span>
            <input
              v-model="amounts[member.id]"
              type="number"
              step="0.01"
              placeholder="0.00"
              class="w-full rounded-lg border border-slate-200 py-2 pl-7 pr-2 text-right text-sm text-slate-900 placeholder:text-slate-400 transition focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"
            />
          </div>
        </div>

        <p v-if="members.length === 0" class="py-6 text-center text-sm text-slate-400">
          No members found in this group.
        </p>
      </div>

      <div
        class="flex items-center justify-between rounded-xl px-4 py-3 text-sm"
        :class="matchesTotal ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'"
      >
        <span>Total split</span>
        <span class="font-mono font-medium">
          ₦{{ sum.toLocaleString() }} / ₦{{ Number(totalAmount || 0).toLocaleString() }}
        </span>
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
          type="button"
          class="rounded-full bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
          @click="handleSubmit"
        >
          Save Split
        </button>
      </div>
    </div>
  </Modal>
</template>