<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterView } from 'vue-router'
import { useAdminAuthStore } from '@admin/stores/adminAuthStore'
import ToastContainer from '@shared/components/ui/ToastContainer.vue'

const authStore = useAdminAuthStore()
const initialized = ref(false)

onMounted(async () => {
  if (authStore.isLoggedIn) {
    await authStore.fetchUser()
  }
  initialized.value = true
})
</script>

<template>
  <div class="max-w-[600px] mx-auto min-h-screen bg-[#FAFAFA] relative">
    <div v-if="!initialized && authStore.isLoggedIn" class="flex items-center justify-center min-h-screen">
      <div class="text-center">
        <div class="w-8 h-8 border-3 border-[#4CAF50] border-t-transparent rounded-full animate-spin mx-auto"></div>
        <p class="text-[14px] text-[#888] mt-3">로딩 중...</p>
      </div>
    </div>
    <RouterView v-else />
  </div>
  <ToastContainer />
</template>
