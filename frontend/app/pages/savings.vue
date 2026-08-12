<script setup lang="ts">
definePageMeta({
  middleware: ['auth'],
  title: 'Record savings',
  subtitle: 'Log contributions toward your savings goals.',
  headerAction: {
    label: 'Create Saving Plan',
    endpoint: '/api/savings',
    fields: [
      { name: 'name', label: 'Plan name', type: 'text', placeholder: 'e.g. Weekly savings', required: true },
      { name: 'amount', label: 'Contribution amount', type: 'number', placeholder: 'e.g. 5000', required: true },
      { name: 'description', label: 'Description (optional)', type: 'textarea', placeholder: 'What is this plan for?' },
    ],
  },
})
</script>

<template>
  <div class="flex h-full min-h-[400px] items-center justify-center rounded-2xl border border-dashed border-slate-200">
    <p class="text-slate-400">Record savings content goes here.</p>
  </div>
</template>