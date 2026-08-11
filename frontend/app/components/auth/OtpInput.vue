<script setup lang="ts">
interface Props {
  length?: number
  error?: string
}

const props = withDefaults(defineProps<Props>(), {
  length: 6,
  error: '',
})

const emit = defineEmits<{
  complete: [code: string]
}>()

const digits = defineModel<string[]>({
  default: () => Array(6).fill(''),
})

const inputRefs = ref<(HTMLInputElement | null)[]>([])

function setInputRef(el: unknown, index: number) {
  inputRefs.value[index] = el as HTMLInputElement | null
}

function onInput(index: number, event: Event) {
  const target = event.target as HTMLInputElement
  const value = target.value.replace(/\D/g, '').slice(-1)

  digits.value[index] = value

  if (value && index < props.length - 1) {
    inputRefs.value[index + 1]?.focus()
  }

  if (digits.value.every((d) => d !== '')) {
    emit('complete', digits.value.join(''))
  }
}

function onKeydown(index: number, event: KeyboardEvent) {
  if (event.key === 'Backspace' && !digits.value[index] && index > 0) {
    inputRefs.value[index - 1]?.focus()
  }
  if (event.key === 'ArrowLeft' && index > 0) {
    inputRefs.value[index - 1]?.focus()
  }
  if (event.key === 'ArrowRight' && index < props.length - 1) {
    inputRefs.value[index + 1]?.focus()
  }
}

function onPaste(event: ClipboardEvent) {
  event.preventDefault()
  const pasted = event.clipboardData?.getData('text').replace(/\D/g, '').slice(0, props.length) ?? ''
  if (!pasted) return

  const next = Array(props.length).fill('')
  pasted.split('').forEach((char, i) => {
    next[i] = char
  })
  digits.value = next

  const lastIndex = Math.min(pasted.length, props.length) - 1
  inputRefs.value[lastIndex]?.focus()

  if (next.every((d) => d !== '')) {
    emit('complete', next.join(''))
  }
}
</script>

<template>
  <div>
    <div class="flex justify-between gap-2 sm:gap-3">
      <input
        v-for="(_, index) in length"
        :key="index"
        :ref="(el) => setInputRef(el, index)"
        v-model="digits[index]"
        type="text"
        inputmode="numeric"
        maxlength="1"
        class="h-14 w-12 rounded-xl border text-center text-xl font-semibold text-slate-900 transition focus:outline-none focus:ring-2 sm:w-14"
        :class="error
          ? 'border-rose-300 focus:border-rose-400 focus:ring-rose-200'
          : 'border-slate-200 focus:border-emerald-500 focus:ring-emerald-500/30'"
        @input="onInput(index, $event)"
        @keydown="onKeydown(index, $event)"
        @paste="onPaste"
      />
    </div>

    <p v-if="error" class="mt-2 text-center text-xs text-rose-600">{{ error }}</p>
  </div>
</template>