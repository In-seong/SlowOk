<script setup lang="ts">
import { useRouter } from 'vue-router'

const router = useRouter()
</script>

<template>
  <header class="fixed top-0 left-1/2 -translate-x-1/2 w-full max-w-[402px] h-[66px] bg-[#E8F5E9] z-40 flex justify-between items-center px-5 border-b border-[#C8E6C9]">
    <div class="flex items-center gap-2">
      <img :src="'/images/turtle.png'" alt="느려도 괜찮아" class="w-[52px] h-[52px] -my-2" />
      <span class="text-[17px] font-bold text-[#333]"><slot name="title">느려도 괜찮아</slot></span>
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
