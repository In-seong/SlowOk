<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/authStore'

const router = useRouter()
const authStore = useAuthStore()

const username = ref('')
const password = ref('')

const isFormValid = computed(() => username.value.trim() !== '' && password.value.trim() !== '')

async function handleLogin(): Promise<void> {
  const success = await authStore.login(username.value, password.value)
  if (success) {
    if (authStore.needsProfileSelect) {
      router.push({ name: 'profile-select' })
    } else {
      router.push({ name: 'home' })
    }
  }
}
</script>

<template>
  <div class="min-h-screen bg-gradient-to-b from-[#E8F5E9] to-white flex flex-col">
    <!-- Logo Section -->
    <div class="flex-1 flex flex-col items-center justify-center pt-16 pb-8">
      <div class="w-[72px] h-[72px] rounded-full bg-[#4CAF50] flex items-center justify-center mb-5">
        <svg class="w-9 h-9 text-white" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M18 8V6" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
          <path d="M14.5 10L13 7.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
          <path d="M21.5 10L23 7.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
          <path d="M18 30C15.5 30 13.5 29.2 12 28C10 26 9 23.5 9 20C9 18.5 9.3 17.2 9.8 16C10.5 14.5 11.5 13.2 13 12.5C14 12 15.2 11.5 16.5 11.2C17 11.1 17.5 11 18 11C18.5 11 19 11.1 19.5 11.2C20.8 11.5 22 12 23 12.5C24.5 13.2 25.5 14.5 26.2 16C26.7 17.2 27 18.5 27 20C27 23.5 26 26 24 28C22.5 29.2 20.5 30 18 30Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
          <path d="M18 18V25M18 25L15.5 22.5M18 25L20.5 22.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </div>
      <h1 class="text-[24px] font-bold text-[#333]">느려도 괜찮아</h1>
      <p class="text-[14px] text-[#888] mt-1">맞춤형 학습지원 플랫폼</p>
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
