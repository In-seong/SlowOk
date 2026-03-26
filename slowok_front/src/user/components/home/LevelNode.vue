<script setup lang="ts">
import type { Challenge } from '@shared/types'

defineProps<{
  challenge: Challenge
  status: 'completed' | 'current' | 'locked'
  stars: number
}>()

const emit = defineEmits<{
  (e: 'play', challengeId: number): void
}>()
</script>

<template>
  <div class="flex flex-col items-center gap-1" style="width: 120px;">
    <!-- Title (상단) -->
    <span
      class="text-[11px] leading-snug text-center w-full line-clamp-2 mb-0.5"
      :class="status === 'locked' ? 'text-[#BDBDBD] font-medium' : 'text-[#333] font-bold'"
    >
      {{ challenge.title }}
    </span>

    <!-- Node Button -->
    <button
      :disabled="status === 'locked'"
      class="relative w-16 h-16 rounded-full flex items-center justify-center transition-all"
      :class="[
        status === 'completed'
          ? 'bg-[#4CAF50] shadow-[0_4px_0_#388E3C] active:shadow-[0_2px_0_#388E3C] active:translate-y-[2px]'
          : status === 'current'
            ? 'bg-[#4CAF50] shadow-[0_4px_0_#388E3C] active:shadow-[0_2px_0_#388E3C] active:translate-y-[2px] animate-bounce-gentle ring-4 ring-[#4CAF50]/20'
            : 'bg-[#E0E0E0] shadow-[0_4px_0_#BDBDBD] cursor-not-allowed'
      ]"
      @click="status !== 'locked' && emit('play', challenge.challenge_id)"
    >
      <!-- Completed: Check -->
      <svg v-if="status === 'completed'" class="w-7 h-7 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
        <path d="M5 13l4 4L19 7" />
      </svg>

      <!-- Current: Star icon -->
      <svg v-else-if="status === 'current'" class="w-7 h-7 text-white" viewBox="0 0 24 24" fill="currentColor">
        <path d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" />
      </svg>

      <!-- Locked: Lock -->
      <svg v-else class="w-6 h-6 text-[#9E9E9E]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
        <path d="M7 11V7a5 5 0 0110 0v4" />
      </svg>
    </button>

    <!-- Stars (하단, completed only) -->
    <div v-if="status === 'completed'" class="flex gap-0.5">
      <svg
        v-for="i in 3"
        :key="i"
        class="w-3.5 h-3.5"
        :class="i <= stars ? 'text-[#FFC107]' : 'text-[#E0E0E0]'"
        viewBox="0 0 24 24"
        fill="currentColor"
      >
        <path d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" />
      </svg>
    </div>
    <div v-else class="h-3.5" />
  </div>
</template>

<style scoped>
@keyframes bounce-gentle {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-4px); }
}
.animate-bounce-gentle {
  animation: bounce-gentle 2s ease-in-out infinite;
}
</style>
