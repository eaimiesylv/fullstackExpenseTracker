<script setup lang="ts">
definePageMeta({
  middleware: ['auth'],
  title: 'Budgets',
  subtitle: 'Set limits and track what you have left.',
  headerAction: {
    label: 'Create Budget',
    endpoint: '/api/budgets',
    fields: [
      { name: 'name', label: 'Budget name', type: 'text', placeholder: 'e.g. Food & Groceries', required: true },
      { name: 'amount', label: 'Limit amount', type: 'number', placeholder: 'e.g. 60000', required: true },
      { name: 'description', label: 'Description (optional)', type: 'textarea', placeholder: 'What is this budget for?' },
    ],
  },
})
</script>

<template>
  <div class="flex h-full min-h-[400px] items-center justify-center rounded-2xl border border-dashed border-slate-200">
    <p class="text-slate-400">Budgets content goes here.</p>
  </div>
</template>