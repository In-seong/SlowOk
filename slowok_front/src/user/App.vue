<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterView } from 'vue-router'
import { useAuthStore } from './stores/authStore'
import ToastContainer from '@shared/components/ui/ToastContainer.vue'
import { initFcmBridge } from '@shared/utils/fcmBridge'

const authStore = useAuthStore()
const initialized = ref(false)

onMounted(async () => {
  if (authStore.isLoggedIn) {
    await authStore.fetchUser()
    initFcmBridge()
  }
  initialized.value = true
})
</script>

<template>
  <div class="max-w-[402px] md:max-w-[600px] mx-auto min-h-screen bg-[#FAFAFA] relative">
    <template v-if="initialized">
      <RouterView />
    </template>
    <div v-else class="min-h-screen flex items-center justify-center">
      <div class="w-8 h-8 border-3 border-[#4CAF50] border-t-transparent rounded-full animate-spin"></div>
    </div>
  </div>
  <ToastContainer />
</template>
