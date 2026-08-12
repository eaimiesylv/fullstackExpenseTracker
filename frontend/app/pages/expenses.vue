<script setup lang="ts">
definePageMeta({
  middleware: ['auth'],
  title: 'Expenses',
  subtitle: 'Track every expense across your budgets.',
  headerAction: {
    label: 'Add Expense',
    endpoint: '/api/expenses',
    fields: [
      { name: 'name', label: 'Expense name', type: 'text', placeholder: 'e.g. Uber to airport', required: true },
      { name: 'amount', label: 'Amount', type: 'number', placeholder: 'e.g. 4200', required: true },
      { name: 'description', label: 'Note (optional)', type: 'textarea', placeholder: 'Any extra details' },
    ],
  },
})
</script>

<template>
  <div class="flex h-full min-h-[400px] items-center justify-center rounded-2xl border border-dashed border-slate-200">
    <p class="text-slate-400">Expenses content goes here.</p>
  </div>
</template>