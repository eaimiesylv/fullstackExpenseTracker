<script setup lang="ts">
import Modal from '~/components/ui/Modal.vue'
import { useApi } from '~/composables/useApi'

interface Props {
  /** e.g. 'expense', 'budget', 'bill' — drives the heading and the endpoint used */
  type: string
}

const props = defineProps<Props>()

const isOpen = defineModel<boolean>({ default: false })

export interface CategoryOption {
  id: string
  name: string
}

const emit = defineEmits<{
  created: [category: CategoryOption]
}>()

const name = ref('')
const description = ref('')
const errors = ref<{ name?: string }>({})
const serverMessage = ref<string | null>(null)
const loading = ref(false)

const typeLabel = computed(() => props.type.charAt(0).toUpperCase() + props.type.slice(1))
const title = computed(() => `Create ${typeLabel.value} Category`)
const subtitle = computed(() => `Add a new category for your ${props.type} items.`)

function resetForm() {
  name.value = ''
  description.value = ''
  errors.value = {}
  serverMessage.value = null
}

watch(isOpen, (open) => {
  if (open) resetForm()
})

function validate() {
  errors.value = {}
  if (!name.value.trim()) errors.value.name = 'Category name is required'
  return Object.keys(errors.value).length === 0
}

async function handleSubmit() {
  serverMessage.value = null
  if (!validate()) return

  loading.value = true
  try {
    const api = useApi()
    const res: any = await api.post('categories', {
      category_name: name.value.trim(),
      category_description: description.value ? description.value.trim() : undefined,
      category_type: props.type,
    })
    const categoryData = res?.data || res
    const created: CategoryOption = {
      id: categoryData.id,
      name: categoryData.category_name || categoryData.name || name.value.trim(),
    }

    emit('created', created)
    isOpen.value = false
  } catch (error: any) {
    serverMessage.value = error?.message || 'Could not create category. Please try again.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <Modal v-model="isOpen" :title="title" :subtitle="subtitle">
    <form class="space-y-5" novalidate @submit.prevent="handleSubmit">
      <div v-if="serverMessage" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
        {{ serverMessage }}
      </div>

      <div>
        <label for="category-name" class="mb-1.5 block text-sm font-medium text-slate-700">Category name</label>
        <input
          id="category-name"
          v-model="name"
          type="text"
          placeholder="e.g. Groceries"
          class="w-full rounded-xl border px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 transition focus:outline-none focus:ring-2"
          :class="errors.name
            ? 'border-rose-300 focus:border-rose-400 focus:ring-rose-200'
            : 'border-slate-200 focus:border-emerald-500 focus:ring-emerald-500/30'"
        />
        <p v-if="errors.name" class="mt-1.5 text-xs text-rose-600">{{ errors.name }}</p>
      </div>

      <div>
        <label for="category-description" class="mb-1.5 block text-sm font-medium text-slate-700">Description (optional)</label>
        <textarea
          id="category-description"
          v-model="description"
          rows="3"
          placeholder="What is this category for?"
          class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 transition focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/30"
        />
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
          type="submit"
          :disabled="loading"
          class="rounded-full bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-70"
        >
          {{ loading ? 'Saving…' : 'Create Category' }}
        </button>
      </div>
    </form>
  </Modal>
</template>