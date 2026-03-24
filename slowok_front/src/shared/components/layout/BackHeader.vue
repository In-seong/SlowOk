<script setup lang="ts">
import { useRouter } from 'vue-router'

interface Props {
  title: string
  onBack?: () => void
}

const props = defineProps<Props>()
const router = useRouter()

function handleBack(): void {
  if (props.onBack) {
    props.onBack()
  } else {
    router.back()
  }
}
</script>

<template>
  <header class="sticky top-0 z-40 flex items-center h-[56px] px-4 bg-white">
    <button class="p-2 -ml-2" @click="handleBack">
      <svg class="w-6 h-6 text-[#333]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M15 18l-6-6 6-6" />
      </svg>
    </button>
    <h1 class="flex-1 text-center text-[17px] font-semibold text-[#333] pr-8">{{ title }}</h1>
  </header>
</template>
