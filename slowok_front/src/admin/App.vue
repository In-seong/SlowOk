<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { RouterView, useRoute } from 'vue-router'
import { useAdminAuthStore } from './stores/adminAuthStore'
import AdminLayout from './components/AdminLayout.vue'
import ToastContainer from '@shared/components/ui/ToastContainer.vue'

const route = useRoute()
const authStore = useAdminAuthStore()

const initialized = ref(false)

const showLayout = computed(() => authStore.isLoggedIn && route.name !== 'login')

onMounted(async () => {
  if (authStore.isLoggedIn) {
    await authStore.fetchUser()
  }
  initialized.value = true
})
</script>

<template>
  <!-- 초기화 중 로딩 -->
  <div v-if="!initialized && authStore.isLoggedIn" class="flex items-center justify-center min-h-screen bg-[#F5F5F5]">
    <div class="text-center">
      <div class="w-8 h-8 border-3 border-[#4CAF50] border-t-transparent rounded-full animate-spin mx-auto"></div>
      <p class="text-[14px] text-[#888] mt-3">로딩 중...</p>
    </div>
  </div>
  <!-- 초기화 완료 후 렌더링 -->
  <template v-else>
    <AdminLayout v-if="showLayout">
      <RouterView />
    </AdminLayout>
    <RouterView v-else />
  </template>
  <ToastContainer />
</template>
