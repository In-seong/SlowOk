<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/authStore'
import turtleImg from '@shared/assets/turtle.png'

const router = useRouter()
const authStore = useAuthStore()

const username = ref('')
const password = ref('')

const isFormValid = computed(() => username.value.trim() !== '' && password.value.trim() !== '')

async function handleLogin(): Promise<void> {
  const success = await authStore.login(username.value, password.value)
  if (success) {
    router.push({ name: 'home' })
  }
}
</script>

<template>
  <div class="min-h-screen bg-gradient-to-b from-[#E8F5E9] to-white flex flex-col">
    <!-- Logo Section -->
    <div class="flex-1 flex flex-col items-center justify-center pt-16 pb-8">
      <img :src="turtleImg" alt="느려도 괜찮아" class="w-[120px] h-[120px] object-contain mb-3" />
      <h1 class="text-[24px] font-bold text-[#333]">느려도 괜찮아</h1>
      <p class="text-[14px] text-[#888] mt-1">아이의 속도에 맞춘 사회성 발달 학습 플랫폼</p>
    </div>

    <!-- Login Card -->
    <div class="px-5 pb-8">
      <div class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] p-6">
        <!-- Error Message -->
        <div v-if="authStore.error" class="bg-[#FFE5E5] rounded-[8px] p-3 mb-4 flex items-center gap-2">
          <svg class="w-4 h-4 text-[#FF4444] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10" />
            <line x1="12" y1="8" x2="12" y2="12" />
            <line x1="12" y1="16" x2="12.01" y2="16" />
          </svg>
          <p class="text-[13px] text-[#FF4444]">{{ authStore.error }}</p>
        </div>

        <form @submit.prevent="handleLogin" class="flex flex-col gap-3">
          <input
            v-model="username"
            type="text"
            placeholder="아이디를 입력하세요"
            autocomplete="username"
            class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] px-4 py-3.5 text-[15px] text-[#333] placeholder-[#B0B0B0] outline-none focus:border-[#4CAF50] transition-colors"
          />

          <input
            v-model="password"
            type="password"
            placeholder="비밀번호를 입력하세요"
            autocomplete="current-password"
            class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] px-4 py-3.5 text-[15px] text-[#333] placeholder-[#B0B0B0] outline-none focus:border-[#4CAF50] transition-colors"
          />

          <button
            type="submit"
            :disabled="!isFormValid || authStore.loading"
            class="w-full bg-[#4CAF50] text-white rounded-[12px] py-3.5 text-[15px] font-semibold mt-1 transition-all active:scale-[0.98] hover:bg-[#388E3C] disabled:opacity-50 disabled:cursor-not-allowed disabled:active:scale-100"
          >
            {{ authStore.loading ? '로그인 중...' : '로그인' }}
          </button>
        </form>
      </div>

      <!-- Register Link -->
      <div class="flex items-center justify-center mt-6">
        <p class="text-[13px] text-[#888]">
          아직 계정이 없으신가요?
          <router-link :to="{ name: 'register' }" class="text-[#4CAF50] font-semibold ml-1">회원가입</router-link>
        </p>
      </div>

      <p class="text-center text-[11px] text-[#B0B0B0] mt-3">테스트 계정 — ID: test / PW: 123456</p>
    </div>
  </div>
</template>
