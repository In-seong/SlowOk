<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  variant?: 'primary' | 'outline' | 'danger'
  full?: boolean
  disabled?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'primary',
  full: false,
  disabled: false,
})

defineEmits<{
  click: [event: MouseEvent]
}>()

const variantClasses = computed(() => {
  const map: Record<string, string> = {
    primary: 'bg-[#4CAF50] text-white hover:bg-[#388E3C]',
    outline: 'bg-white border border-[#E0E0E0] text-[#555] hover:bg-[#F8F8F8]',
    danger: 'bg-[#FF4444] text-white',
  }
  return map[props.variant] || map.primary
})
</script>

<template>
  <button
    class="rounded-[12px] text-[15px] font-semibold py-3.5 px-6 active:scale-[0.98] transition-all"
    :class="[
      variantClasses,
      full ? 'w-full' : '',
      disabled ? 'opacity-50 cursor-not-allowed' : '',
    ]"
    :disabled="disabled"
    @click="$emit('click', $event)"
  >
    <slot />
  </button>
</template>
