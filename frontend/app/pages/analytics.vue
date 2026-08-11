<script setup lang="ts">
definePageMeta({
  middleware: ['auth'],
  title: 'Expenses',
  subtitle: 'Track every expense across your budgets.',
})
</script>

<template>
  <div class="flex h-full min-h-[400px] items-center justify-center rounded-2xl border border-dashed border-slate-200">
    <p class="text-slate-400">Expenses content goes here.</p>
  </div>
</template>