<script setup lang="ts">
interface Props {
  currentPage: number
  lastPage: number
  total?: number
  perPage?: number
  loading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  total: 0,
  perPage: 10,
  loading: false,
})

const emit = defineEmits<{
  change: [page: number]
  'update:currentPage': [page: number]
}>()

const fromItem = computed(() => {
  if (!props.total || props.total === 0) return 0
  return (props.currentPage - 1) * props.perPage + 1
})

const toItem = computed(() => {
  if (!props.total || props.total === 0) return 0
  return Math.min(props.currentPage * props.perPage, props.total)
})

const pageNumbers = computed(() => {
  const pages: (number | string)[] = []
  const totalPages = props.lastPage
  const current = props.currentPage

  if (totalPages <= 7) {
    for (let i = 1; i <= totalPages; i++) {
      pages.push(i)
    }
  } else {
    pages.push(1)
    if (current > 3) {
      pages.push('...')
    }

    const start = Math.max(2, current - 1)
    const end = Math.min(totalPages - 1, current + 1)

    for (let i = start; i <= end; i++) {
      pages.push(i)
    }

    if (current < totalPages - 2) {
      pages.push('...')
    }
    pages.push(totalPages)
  }

  return pages
})

function goToPage(page: number) {
  if (page < 1 || page > props.lastPage || page === props.currentPage || props.loading) return
  emit('update:currentPage', page)
  emit('change', page)
}
</script>

<template>
  <div v-if="lastPage > 1 || total > 0" class="flex flex-col items-center justify-between gap-4 rounded-2xl bg-white px-4 py-3 border border-slate-100 sm:flex-row shadow-2xs">
    <div class="text-xs font-medium text-slate-500">
      <span v-if="total > 0">
        Showing <span class="font-bold text-slate-900">{{ fromItem }}</span> to <span class="font-bold text-slate-900">{{ toItem }}</span> of <span class="font-bold text-slate-900">{{ total }}</span> items
      </span>
      <span v-else>No items to display</span>
    </div>

    <div v-if="lastPage > 1" class="flex items-center gap-1">
      <!-- Previous Button -->
      <button
        type="button"
        class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50 hover:text-slate-900 disabled:cursor-not-allowed disabled:opacity-40"
        :disabled="currentPage <= 1 || loading"
        @click="goToPage(currentPage - 1)"
      >
        <span class="sr-only">Previous Page</span>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
          <path d="M15 18l-6-6 6-6" />
        </svg>
      </button>

      <!-- Page Numbers -->
      <template v-for="(p, index) in pageNumbers" :key="index">
        <span
          v-if="p === '...'"
          class="flex h-8 w-8 items-center justify-center text-xs text-slate-400 font-medium"
        >
          …
        </span>
        <button
          v-else
          type="button"
          class="inline-flex h-8 min-w-[32px] px-2 items-center justify-center rounded-lg text-xs font-semibold transition"
          :class="p === currentPage
            ? 'bg-indigo-600 text-white shadow-sm'
            : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 hover:text-slate-900'"
          :disabled="loading"
          @click="goToPage(p as number)"
        >
          {{ p }}
        </button>
      </template>

      <!-- Next Button -->
      <button
        type="button"
        class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50 hover:text-slate-900 disabled:cursor-not-allowed disabled:opacity-40"
        :disabled="currentPage >= lastPage || loading"
        @click="goToPage(currentPage + 1)"
      >
        <span class="sr-only">Next Page</span>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
          <path d="M9 18l6-6-6-6" />
        </svg>
      </button>
    </div>
  </div>
</template>
