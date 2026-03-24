<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/authStore'
import BackHeader from '@shared/components/layout/BackHeader.vue'

const router = useRouter()
const authStore = useAuthStore()

const form = ref({
  name: '',
  username: '',
  password: '',
  password_confirmation: '',
  phone: '',
  email: '',
  user_type: 'LEARNER' as 'LEARNER' | 'PARENT',
  invite_code: '',
})

const isFormValid = computed(() => {
  return (
    form.value.name.trim() !== '' &&
    form.value.username.trim() !== '' &&
    form.value.password.trim() !== '' &&
    form.value.password_confirmation.trim() !== '' &&
    form.value.password === form.value.password_confirmation
  )
})

const passwordMismatch = computed(() => {
  return (
    form.value.password_confirmation.length > 0 &&
    form.value.password !== form.value.password_confirmation
  )
})

async function handleRegister(): Promise<void> {
  const success = await authStore.register(form.value)
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
    <!-- Header -->
    <BackHeader title="회원가입" />

    <!-- Content -->
    <div class="flex-1 px-5 pt-4 pb-8">
      <!-- Welcome Text -->
      <p class="text-[14px] text-[#888] mb-4">느려도 괜찮아에 오신 것을 환영합니다</p>

      <!-- Form Card -->
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

        <form @submit.prevent="handleRegister" class="flex flex-col gap-4">
          <!-- User Type Toggle -->
          <div>
            <label class="block text-[13px] font-medium text-[#555] mb-1.5">유형 선택</label>
            <div class="flex gap-2">
              <button
                type="button"
                @click="form.user_type = 'LEARNER'"
                class="flex-1 flex items-center justify-center gap-1.5 rounded-[10px] px-3 py-2.5 text-[14px] font-semibold transition-all"
                :class="form.user_type === 'LEARNER'
                  ? 'border-[1.5px] border-[#4CAF50] bg-[#E8F5E9] text-[#4CAF50]'
                  : 'border-[1.5px] border-transparent bg-[#F8F8F8] text-[#555]'"
              >
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                </svg>
                학습자
              </button>
              <button
                type="button"
                @click="form.user_type = 'PARENT'"
                class="flex-1 flex items-center justify-center gap-1.5 rounded-[10px] px-3 py-2.5 text-[14px] font-semibold transition-all"
                :class="form.user_type === 'PARENT'
                  ? 'border-[1.5px] border-[#4CAF50] bg-[#E8F5E9] text-[#4CAF50]'
                  : 'border-[1.5px] border-transparent bg-[#F8F8F8] text-[#555]'"
              >
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                학부모
              </button>
            </div>
          </div>

          <!-- Invite Code -->
          <div>
            <label class="block text-[13px] font-medium text-[#555] mb-1.5">초대코드</label>
            <input
              v-model="form.invite_code"
              type="text"
              placeholder="기관 초대코드를 입력하세요 (선택)"
              class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] px-4 py-3.5 text-[15px] text-[#333] placeholder-[#B0B0B0] outline-none focus:border-[#4CAF50] transition-colors"
            />
            <p class="text-[11px] text-[#999] mt-1">기관에서 발급한 초대코드가 있다면 입력하세요</p>
          </div>

          <!-- Name -->
          <div>
            <label class="block text-[13px] font-medium text-[#555] mb-1.5">이름</label>
            <input
              v-model="form.name"
              type="text"
              placeholder="이름을 입력하세요"
              required
              class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] px-4 py-3.5 text-[15px] text-[#333] placeholder-[#B0B0B0] outline-none focus:border-[#4CAF50] transition-colors"
            />
          </div>

          <!-- Username -->
          <div>
            <label class="block text-[13px] font-medium text-[#555] mb-1.5">아이디</label>
            <input
              v-model="form.username"
              type="text"
              placeholder="아이디를 입력하세요"
              autocomplete="username"
              required
              class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] px-4 py-3.5 text-[15px] text-[#333] placeholder-[#B0B0B0] outline-none focus:border-[#4CAF50] transition-colors"
            />
          </div>

          <!-- Password -->
          <div>
            <label class="block text-[13px] font-medium text-[#555] mb-1.5">비밀번호</label>
            <input
              v-model="form.password"
              type="password"
              placeholder="비밀번호를 입력하세요"
              autocomplete="new-password"
              required
              class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] px-4 py-3.5 text-[15px] text-[#333] placeholder-[#B0B0B0] outline-none focus:border-[#4CAF50] transition-colors"
            />
          </div>

          <!-- Password Confirmation -->
          <div>
            <label class="block text-[13px] font-medium text-[#555] mb-1.5">비밀번호 확인</label>
            <input
              v-model="form.password_confirmation"
              type="password"
              placeholder="비밀번호를 다시 입력하세요"
              autocomplete="new-password"
              required
              class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] px-4 py-3.5 text-[15px] text-[#333] placeholder-[#B0B0B0] outline-none focus:border-[#4CAF50] transition-colors"
              :class="passwordMismatch ? 'border-[#FF4444] focus:border-[#FF4444]' : ''"
            />
            <p v-if="passwordMismatch" class="text-[12px] text-[#FF4444] mt-1">비밀번호가 일치하지 않습니다</p>
          </div>

          <!-- Phone -->
          <div>
            <label class="block text-[13px] font-medium text-[#555] mb-1.5">연락처</label>
            <input
              v-model="form.phone"
              type="tel"
              placeholder="010-0000-0000"
              class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] px-4 py-3.5 text-[15px] text-[#333] placeholder-[#B0B0B0] outline-none focus:border-[#4CAF50] transition-colors"
            />
          </div>

          <!-- Email -->
          <div>
            <label class="block text-[13px] font-medium text-[#555] mb-1.5">이메일</label>
            <input
              v-model="form.email"
              type="email"
              placeholder="example@email.com"
              class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] px-4 py-3.5 text-[15px] text-[#333] placeholder-[#B0B0B0] outline-none focus:border-[#4CAF50] transition-colors"
            />
          </div>

          <!-- Submit Button -->
          <button
            type="submit"
            :disabled="!isFormValid || authStore.loading"
            class="w-full bg-[#4CAF50] text-white rounded-[12px] py-3.5 text-[15px] font-semibold mt-1 transition-all active:scale-[0.98] hover:bg-[#388E3C] disabled:opacity-50 disabled:cursor-not-allowed disabled:active:scale-100"
          >
            {{ authStore.loading ? '가입 중...' : '회원가입' }}
          </button>
        </form>
      </div>

      <!-- Login Link -->
      <div class="flex items-center justify-center mt-5 pb-4">
        <p class="text-[13px] text-[#888]">
          이미 계정이 있으신가요?
          <router-link :to="{ name: 'login' }" class="text-[#4CAF50] font-semibold ml-1">로그인</router-link>
        </p>
      </div>
    </div>
  </div>
</template>
