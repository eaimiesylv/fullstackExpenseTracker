<script setup lang="ts">
definePageMeta({
  middleware: ['auth'],
  title: 'Groups',
  subtitle: 'Manage your shared groups and collaborate with members.',
  headerAction: {
    label: 'Create Group',
    endpoint: '/api/groups',
    fields: [
      { name: 'name', label: 'Group name', type: 'text', placeholder: 'e.g. Family Savings', required: true },
      { name: 'description', label: 'Description (optional)', type: 'textarea', placeholder: 'What is this group for?' },
    ],
  },
})
</script>

<template>
  <div class="flex h-full min-h-[400px] items-center justify-center rounded-2xl border border-dashed border-slate-200">
    <p class="text-slate-400">Groups content goes here.</p>
  </div>
</template>