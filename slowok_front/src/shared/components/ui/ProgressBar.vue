<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  value: number
  variant?: 'primary' | 'success' | 'warning' | 'danger'
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'primary',
})

const clampedValue = computed(() => Math.min(100, Math.max(0, props.value)))

const barColor = computed(() => {
  const map: Record<string, string> = {
    primary: 'bg-[#4CAF50]',
    success: 'bg-[#1FBD53]',
    warning: 'bg-[#F3940E]',
    danger: 'bg-[#FF4444]',
  }
  return map[props.variant] || map.primary
})
</script>

<template>
  <div class="bg-[#F0F0F0] rounded-full h-2.5 overflow-hidden">
    <div
      class="h-full rounded-full transition-all duration-300 ease-out"
      :class="barColor"
      :style="{ width: `${clampedValue}%` }"
    />
  </div>
</template>
