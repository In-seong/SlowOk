<script setup lang="ts">
import { useToastStore } from '@shared/stores/toastStore'

const toast = useToastStore()

function typeClasses(type: 'success' | 'error' | 'warning') {
  switch (type) {
    case 'success':
      return 'bg-primary text-white'
    case 'error':
      return 'bg-red-500 text-white'
    case 'warning':
      return 'bg-secondary text-white'
  }
}
</script>

<template>
  <Teleport to="body">
    <div class="fixed top-4 left-1/2 -translate-x-1/2 z-[9999] flex flex-col items-center gap-2">
      <TransitionGroup name="toast">
        <div
          v-for="msg in toast.messages"
          :key="msg.id"
          :class="['px-5 py-3 rounded-lg shadow-lg text-sm font-medium min-w-[200px] text-center cursor-pointer', typeClasses(msg.type)]"
          @click="toast.remove(msg.id)"
        >
          {{ msg.text }}
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<style scoped>
.toast-enter-active {
  transition: all 0.3s ease;
}
.toast-leave-active {
  transition: all 0.2s ease;
}
.toast-enter-from {
  opacity: 0;
  transform: translateY(-20px);
}
.toast-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}
</style>
