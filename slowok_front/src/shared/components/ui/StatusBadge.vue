<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  label: string
  variant?: 'success' | 'warning' | 'danger' | 'primary' | 'info' | 'default'
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'default',
})

const variantClasses = computed(() => {
  const map: Record<string, string> = {
    success: 'bg-[#E8F9EE] text-[#1FBD53]',
    warning: 'bg-[#FFF4E5] text-[#F3940E]',
    danger: 'bg-[#FFE5E5] text-[#FF4444]',
    primary: 'bg-[#E8F5E9] text-[#4CAF50]',
    info: 'bg-[#E3F2FD] text-[#2196F3]',
    default: 'bg-[#F0F0F0] text-[#888]',
  }
  return map[props.variant] || map.default
})
</script>

<template>
  <span
    class="rounded-full px-2.5 py-1 text-[11px] font-semibold inline-flex"
    :class="variantClasses"
  >
    {{ label }}
  </span>
</template>
