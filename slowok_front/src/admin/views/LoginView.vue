<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAdminAuthStore } from '../stores/adminAuthStore'

const router = useRouter()
const authStore = useAdminAuthStore()

const username = ref('')
const password = ref('')

async function handleLogin() {
  const success = await authStore.login(username.value, password.value)
  if (success) {
    router.push({ name: 'dashboard' })
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-100">
    <div class="bg-white rounded-xl shadow-lg p-8 w-full max-w-md">
      <div class="text-center mb-8">
        <h1 class="text-2xl font-bold text-gray-800">관리자 로그인</h1>
        <p class="text-gray-500 mt-1">느려도 괜찮아 관리 시스템</p>
      </div>

      <form @submit.prevent="handleLogin" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">아이디</label>
          <input v-model="username" type="text" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent" required />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">비밀번호</label>
          <input v-model="password" type="password" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent" required />
        </div>
        <p v-if="authStore.error" class="text-red-500 text-sm">{{ authStore.error }}</p>
        <button type="submit" :disabled="authStore.loading" class="w-full bg-primary text-white py-3 rounded-lg font-medium hover:bg-primary-hover transition-colors disabled:opacity-50">
          {{ authStore.loading ? '로그인 중...' : '로그인' }}
        </button>
      </form>

      <div class="mt-6 text-center text-xs text-gray-400">
        <p>테스트 계정 — ID: admin / PW: 123456</p>
      </div>
    </div>
  </div>
</template>
