<script setup lang="ts">
interface Props {
  title: string
  actionLabel?: string
  actionTo?: string
}

withDefaults(defineProps<Props>(), {
  actionLabel: '',
  actionTo: '',
})
</script>

<template>
  <div class="flex items-center justify-between px-1 mb-3">
    <h2 class="text-[16px] font-semibold text-[#333]">{{ title }}</h2>
    <router-link
      v-if="actionLabel && actionTo"
      :to="actionTo"
      class="flex items-center gap-0.5 text-[12px] text-[#888]"
    >
      {{ actionLabel }}
      <svg class="w-3.5 h-3.5 text-[#888]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M9 18l6-6-6-6" />
      </svg>
    </router-link>
    <span
      v-else-if="actionLabel"
      class="flex items-center gap-0.5 text-[12px] text-[#888]"
    >
      {{ actionLabel }}
      <svg class="w-3.5 h-3.5 text-[#888]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M9 18l6-6-6-6" />
      </svg>
    </span>
  </div>
</template>
