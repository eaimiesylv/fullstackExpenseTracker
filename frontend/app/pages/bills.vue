<script setup lang="ts">
definePageMeta({
  middleware: ['auth'],
  title: 'Bills & Splits',
  subtitle: 'Track shared bills and who still owes.',
  headerAction: {
    label: 'Create Bill',
    endpoint: '/api/bills',
    fields: [
      { name: 'name', label: 'Bill name', type: 'text', placeholder: 'e.g. Dinner', required: true },
      { name: 'amount', label: 'Total amount', type: 'number', placeholder: 'e.g. 20000', required: true },
      { name: 'description', label: 'Description (optional)', type: 'textarea', placeholder: 'Any extra details' },
    ],
  },
})
</script>

<template>
  <div class="flex h-full min-h-[400px] items-center justify-center rounded-2xl border border-dashed border-slate-200">
    <p class="text-slate-400">Bills & splits content goes here.</p>
  </div>
</template>