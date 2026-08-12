<script setup lang="ts">
import Modal from '~/components/ui/Modal.vue'

export interface FormField {
  name: string
  label: string
  type?: 'text' | 'number' | 'textarea'
  placeholder?: string
  required?: boolean
}

interface Props {
  title: string
  fields: FormField[]
  submitLabel?: string
  loading?: boolean
  serverMessage?: string | null
  serverErrors?: Record<string, string> | null
}

const props = withDefaults(defineProps<Props>(), {
  submitLabel: 'Create',
  loading: false,
  serverMessage: null,
  serverErrors: null,
})

const isOpen = defineModel<boolean>({ default: false })

const emit = defineEmits<{
  submit: [values: Record<string, string>]
}>()

const values = ref<Record<string, string>>({})
const errors = ref<Record<string, string>>({})
const localServerMessage = ref<string | null>(props.serverMessage)
const localServerErrors = ref<Record<string, string> | null>(props.serverErrors)

function resetValues() {
  values.value = Object.fromEntries(props.fields.map((f) => [f.name, '']))
  errors.value = {}
  localServerMessage.value = null
  localServerErrors.value = null
}

watch(() => props.serverMessage, (newMessage) => {
  localServerMessage.value = newMessage
})

watch(() => props.serverErrors, (newErrors) => {
  localServerErrors.value = newErrors ? { ...newErrors } : null
  if (newErrors && Object.keys(newErrors).length) {
    localServerMessage.value = null
  }
})

watch(isOpen, (open) => {
  if (open) {
    resetValues()
  }
})

function resetServerError(fieldName: string) {
  localServerMessage.value = null

  if (!localServerErrors.value || !localServerErrors.value[fieldName]) return
  const nextErrors = { ...localServerErrors.value }
  delete nextErrors[fieldName]
  localServerErrors.value = Object.keys(nextErrors).length ? nextErrors : null
}

function validate() {
  errors.value = {}
  for (const field of props.fields) {
    if (field.required && !values.value[field.name]?.trim()) {
      errors.value[field.name] = `${field.label} is required`
    }
  }
  return Object.keys(errors.value).length === 0
}

function handleSubmit() {
  errors.value = {}
  localServerMessage.value = null

  if (!validate()) return

  emit('submit', { ...values.value })
}
</script>

<template>
  <Modal v-model="isOpen" :title="title">
    <form class="space-y-4" novalidate @submit.prevent="handleSubmit">
      <div v-if="localServerMessage && !localServerErrors" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
        {{ localServerMessage }}
      </div>
      <div v-for="field in fields" :key="field.name">
        <label :for="field.name" class="mb-1.5 block text-sm font-medium text-slate-700">
          {{ field.label }}
        </label>

        <textarea
          v-if="field.type === 'textarea'"
          :id="field.name"
          v-model="values[field.name]"
          :placeholder="field.placeholder"
          rows="3"
          class="w-full rounded-xl border px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 transition focus:outline-none focus:ring-2"
          :class="errors[field.name] || localServerErrors?.[field.name]
            ? 'border-rose-300 focus:border-rose-400 focus:ring-rose-200'
            : 'border-slate-200 focus:border-emerald-500 focus:ring-emerald-500/30'"
          @input="resetServerError(field.name)"
        />
        <input
          v-else
          :id="field.name"
          v-model="values[field.name]"
          :type="field.type || 'text'"
          :placeholder="field.placeholder"
          class="w-full rounded-xl border px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 transition focus:outline-none focus:ring-2"
          :class="errors[field.name] || localServerErrors?.[field.name]
            ? 'border-rose-300 focus:border-rose-400 focus:ring-rose-200'
            : 'border-slate-200 focus:border-emerald-500 focus:ring-emerald-500/30'"
          @input="resetServerError(field.name)"
        />

        <p v-if="errors[field.name] || localServerErrors?.[field.name]" class="mt-1.5 text-xs text-rose-600">
          {{ errors[field.name] || localServerErrors?.[field.name] }}
        </p>
      </div>

      <div class="flex justify-end gap-3 pt-2">
        <button
          type="button"
          class="rounded-full px-5 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50"
          @click="isOpen = false"
        >
          Cancel
        </button>
        <button
          type="submit"
          :disabled="loading"
          class="grad-cta rounded-full px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:brightness-105 disabled:cursor-not-allowed disabled:opacity-70"
        >
          {{ loading ? 'Saving…' : submitLabel }}
        </button>
      </div>
    </form>
  </Modal>
</template>