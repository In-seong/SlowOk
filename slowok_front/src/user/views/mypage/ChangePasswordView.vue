<script setup lang="ts">
import { ref, computed } from 'vue'
import { useAuthStore } from '../../stores/authStore'
import BackHeader from '@shared/components/layout/BackHeader.vue'
import BottomNav from '@shared/components/layout/BottomNav.vue'

const authStore = useAuthStore()
const successMessage = ref('')

const form = ref({
  current_password: '',
  new_password: '',
  new_password_confirmation: '',
})

const passwordMismatch = computed(() =>
  form.value.new_password !== '' &&
  form.value.new_password_confirmation !== '' &&
  form.value.new_password !== form.value.new_password_confirmation,
)

const isFormValid = computed(() =>
  form.value.current_password !== '' &&
  form.value.new_password.length >= 8 &&
  form.value.new_password === form.value.new_password_confirmation,
)

async function handleSubmit(): Promise<void> {
  successMessage.value = ''
  authStore.error = null
  const success = await authStore.changePassword(
    form.value.current_password,
    form.value.new_password,
    form.value.new_password_confirmation,
  )
  if (success) {
    successMessage.value = '비밀번호가 변경되었습니다.'
    form.value.current_password = ''
    form.value.new_password = ''
    form.value.new_password_confirmation = ''
  }
}
</script>

<template>
  <div class="min-h-screen bg-[#F5F5F5] flex justify-center">
    <div class="w-full max-w-[402px] md:max-w-[600px] min-h-screen relative bg-[#F5F5F5] flex flex-col">
      <BackHeader title="비밀번호 변경" />

      <main class="flex-1 px-5 pb-[80px] pt-4 space-y-4 overflow-y-auto">
        <div class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] p-6">
          <!-- Success Message -->
          <div v-if="successMessage" class="bg-[#E8F5E9] rounded-[8px] p-3 mb-4 flex items-center gap-2">
            <svg class="w-4 h-4 text-[#4CAF50] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M22 11.08V12a10 10 0 11-5.93-9.14" /><polyline points="22 4 12 14.01 9 11.01" />
            </svg>
            <p class="text-[13px] text-[#4CAF50]">{{ successMessage }}</p>
          </div>

          <!-- Error Message -->
          <div v-if="authStore.error" class="bg-[#FFE5E5] rounded-[8px] p-3 mb-4 flex items-center gap-2">
            <svg class="w-4 h-4 text-[#FF4444] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" />
            </svg>
            <p class="text-[13px] text-[#FF4444]">{{ authStore.error }}</p>
          </div>

          <form @submit.prevent="handleSubmit" class="flex flex-col gap-4">
            <div>
              <label class="block text-[13px] font-medium text-[#555] mb-1.5">현재 비밀번호 <span class="text-[#FF4444]">*</span></label>
              <input
                v-model="form.current_password"
                type="password"
                required
                placeholder="현재 비밀번호를 입력하세요"
                class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] px-4 py-3.5 text-[15px] text-[#333] placeholder-[#B0B0B0] outline-none focus:border-[#4CAF50] transition-colors"
              />
            </div>

            <div>
              <label class="block text-[13px] font-medium text-[#555] mb-1.5">새 비밀번호 <span class="text-[#FF4444]">*</span></label>
              <input
                v-model="form.new_password"
                type="password"
                required
                placeholder="8자 이상 입력하세요"
                class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] px-4 py-3.5 text-[15px] text-[#333] placeholder-[#B0B0B0] outline-none focus:border-[#4CAF50] transition-colors"
              />
              <p v-if="form.new_password && form.new_password.length < 8" class="text-[12px] text-[#FF4444] mt-1">8자 이상 입력해주세요</p>
            </div>

            <div>
              <label class="block text-[13px] font-medium text-[#555] mb-1.5">새 비밀번호 확인 <span class="text-[#FF4444]">*</span></label>
              <input
                v-model="form.new_password_confirmation"
                type="password"
                required
                placeholder="새 비밀번호를 다시 입력하세요"
                class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] px-4 py-3.5 text-[15px] text-[#333] placeholder-[#B0B0B0] outline-none focus:border-[#4CAF50] transition-colors"
              />
              <p v-if="passwordMismatch" class="text-[12px] text-[#FF4444] mt-1">비밀번호가 일치하지 않습니다</p>
            </div>

            <button
              type="submit"
              :disabled="!isFormValid || authStore.loading"
              class="w-full bg-[#4CAF50] text-white rounded-[12px] py-3.5 text-[15px] font-semibold mt-1 transition-all active:scale-[0.98] hover:bg-[#388E3C] disabled:opacity-50 disabled:cursor-not-allowed disabled:active:scale-100"
            >
              {{ authStore.loading ? '변경 중...' : '비밀번호 변경' }}
            </button>
          </form>
        </div>
      </main>

      <BottomNav />
    </div>
  </div>
</template>
