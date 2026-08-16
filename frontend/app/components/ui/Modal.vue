<script setup lang="ts">
interface Props {
  title: string
  subtitle?: string
  zIndex?: number | string
  maxWidth?: string
}

const props = withDefaults(defineProps<Props>(), {
  subtitle: '',
  zIndex: 50,
  maxWidth: 'max-w-2xl',
})

const isOpen = defineModel<boolean>({ default: false })

function close() {
  isOpen.value = false
}

function onKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape' && isOpen.value) {
    close()
  }
}

onMounted(() => document.addEventListener('keydown', onKeydown))
onUnmounted(() => document.removeEventListener('keydown', onKeydown))
</script>

<template>
  <Teleport to="body">
    <Transition name="modal-fade">
      <div
        v-if="isOpen"
        class="fixed inset-0 flex items-center justify-center px-3 py-6 sm:px-6"
        :style="{ zIndex: Number(zIndex) }"
      >
        <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-[2px]" @click="close" />

        <div
          class="relative max-h-[92vh] w-fit min-w-[300px] sm:min-w-[440px] max-w-[95vw] overflow-y-auto rounded-3xl border border-slate-100 bg-white p-6 sm:p-8 shadow-2xl shadow-slate-900/10 transition-all"
          :class="maxWidth"
        >
          <div class="flex items-start justify-between gap-4">
            <div>
              <h2 class="font-display text-xl font-semibold text-slate-900">{{ title }}</h2>
              <p v-if="subtitle" class="mt-1 text-sm text-slate-500">{{ subtitle }}</p>
            </div>
            <button
              type="button"
              class="rounded-full p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
              aria-label="Close"
              @click="close"
            >
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                <path d="M6 6l12 12M18 6L6 18" />
              </svg>
            </button>
          </div>

          <div class="mt-6">
            <slot />
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.15s ease;
}
.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}
</style>