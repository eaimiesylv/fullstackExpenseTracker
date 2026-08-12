<script setup lang="ts">
import { BRAND_NAME, BRAND_HREF } from '~/config/brand'

const entries = [
  { label: 'Brown paid weekly savings', amount: '+₦5,000', tone: 'up' as const },
  { label: 'Dinner split settled', amount: '+₦20,000', tone: 'up' as const },
  { label: 'Food budget spend', amount: '-₦4,200', tone: 'down' as const },
  { label: 'Bayo joined group', amount: 'New', tone: 'neutral' as const },
]

const brandName = BRAND_NAME
const brandHref = BRAND_HREF
</script>

<template>
  <div class="grad-final relative hidden h-full flex-col justify-between overflow-hidden p-10 text-white lg:flex">
    <div class="flex items-center gap-2">
      <NuxtLink :to="brandHref" class="flex items-center gap-2">
        <span class="flex h-8 w-8 items-center justify-center rounded-lg" style="background: var(--emerald)">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
            <path d="M4 4h16M4 12h10M4 20h16" stroke="white" stroke-width="2.4" stroke-linecap="round" />
          </svg>
        </span>
        <span class="font-display text-lg font-bold tracking-tight">{{ brandName }}</span>
      </NuxtLink>
    </div>

    <div>
      <p class="font-display max-w-sm text-3xl font-semibold leading-tight">
        Every amount, split fairly and tracked together.
      </p>
      <p class="mt-3 max-w-sm text-sm text-slate-300">
        Budgets, shared bills, and Ajo-style contributions, all in one ledger your group can see.
      </p>

      <div class="mt-8 space-y-2.5">
        <div
          v-for="(entry, i) in entries"
          :key="entry.label"
          class="ledger-row flex items-center justify-between rounded-xl border border-white/10 bg-white/5 px-4 py-3 backdrop-blur-sm"
          :style="{ animationDelay: `${i * 150}ms` }"
        >
          <span class="text-sm text-slate-200">{{ entry.label }}</span>
          <span
            class="font-mono text-sm font-medium"
            :class="{
              'text-emerald-400': entry.tone === 'up',
              'text-rose-400': entry.tone === 'down',
              'text-slate-400': entry.tone === 'neutral',
            }"
          >
            {{ entry.amount }}
          </span>
        </div>
      </div>
    </div>

    <p class="text-xs text-slate-400">© {{ new Date().getFullYear() }} {{ brandName }}</p>
  </div>
</template>