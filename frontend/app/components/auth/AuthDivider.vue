<script setup lang="ts">
interface Props {
  label?: string
}

withDefaults(defineProps<Props>(), {
  label: 'or continue with',
})
</script>

<template>
  <div class="flex items-center gap-3">
    <span class="h-px flex-1 bg-slate-200" />
    <span class="text-xs font-medium uppercase tracking-wide text-slate-400">{{ label }}</span>
    <span class="h-px flex-1 bg-slate-200" />
  </div>
</template>