<script setup lang="ts">
interface Props {
  label: string
  value: string
  last?: boolean
}

withDefaults(defineProps<Props>(), {
  last: false,
})
</script>

<template>
  <div
    class="flex items-center justify-between py-2.5"
    :class="!last ? 'border-b border-[#F5F5F5]' : ''"
  >
    <span class="text-[13px] text-[#888]">{{ label }}</span>
    <span class="text-[13px] font-semibold text-[#333]">{{ value }}</span>
  </div>
</template>
