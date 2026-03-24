<script setup lang="ts">
import { useRouter } from 'vue-router'

const router = useRouter()
</script>

<template>
  <header class="fixed top-0 left-1/2 -translate-x-1/2 w-full max-w-[402px] h-[66px] bg-white z-40 flex justify-between items-center px-5">
    <div class="flex items-center gap-2">
      <div class="w-9 h-9 bg-[#4CAF50] rounded-full flex items-center justify-center">
        <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M7 20h10" />
          <path d="M10 20c5.5-2.5.8-6.4 3-10" />
          <path d="M9.5 9.4c1.1.8 1.8 2.2 2.3 3.7-2 .4-3.5.4-4.8-.3-1.2-.6-2.3-1.9-3-4.2 2.8-.5 4.4 0 5.5.8z" />
          <path d="M14.1 6a7 7 0 00-1.1 4c1.9-.1 3.3-.6 4.3-1.4 1-1 1.6-2.3 1.7-4.6-2.7.1-4 1-4.9 2z" />
        </svg>
      </div>
      <span class="text-[17px] font-bold text-[#333]">느려도 괜찮아</span>
    </div>
    <div class="flex items-center">
      <button class="p-2 relative" @click="router.push({ name: 'notifications' })">
        <svg class="w-6 h-6 text-[#555]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9" />
          <path d="M13.73 21a2 2 0 01-3.46 0" />
        </svg>
        <slot name="badge" />
      </button>
    </div>
  </header>
</template>
