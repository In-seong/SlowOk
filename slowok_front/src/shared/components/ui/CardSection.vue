<script setup lang="ts">
interface Props {
  class?: string
}

defineProps<Props>()
</script>

<template>
  <div
    class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] p-5"
    :class="$props.class"
  >
    <slot />
  </div>
</template>
