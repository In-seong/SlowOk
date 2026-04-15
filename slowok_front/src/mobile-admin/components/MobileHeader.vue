<script setup lang="ts">
import { computed } from 'vue'
import { useAdminAuthStore } from '@admin/stores/adminAuthStore'

interface Props {
  title?: string
  showInstitutionSelector?: boolean
  showNotification?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  title: '',
  showInstitutionSelector: false,
  showNotification: true,
})

const emit = defineEmits<{
  institutionClick: []
}>()

const authStore = useAdminAuthStore()

const displayTitle = computed(() => {
  if (props.title) return props.title
  return authStore.currentInstitutionName
})
</script>

<template>
  <header class="sticky top-0 z-40 flex items-center justify-between h-[56px] px-4 bg-white border-b border-[#E8E8E8]">
    <button
      v-if="showInstitutionSelector && authStore.isMaster"
      class="flex items-center gap-1 text-[17px] font-bold text-[#333] active:opacity-70 transition-opacity"
      @click="emit('institutionClick')"
    >
      {{ displayTitle }}
      <svg class="w-4 h-4 text-[#888]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M6 9l6 6 6-6" />
      </svg>
    </button>
    <h1 v-else class="text-[17px] font-bold text-[#333]">{{ displayTitle }}</h1>

    <div class="flex items-center gap-2">
      <slot name="actions" />
      <button v-if="showNotification" class="w-[44px] h-[44px] flex items-center justify-center">
        <svg class="w-6 h-6 text-[#555]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
          <path d="M13.73 21a2 2 0 0 1-3.46 0" />
        </svg>
      </button>
    </div>
  </header>
</template>
