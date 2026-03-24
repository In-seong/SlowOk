<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/authStore'
import BackHeader from '@shared/components/layout/BackHeader.vue'
import BottomNav from '@shared/components/layout/BottomNav.vue'

const router = useRouter()
const authStore = useAuthStore()
const successMessage = ref('')

const form = ref({
  name: '',
  phone: '',
  email: '',
})

onMounted(() => {
  const p = authStore.activeProfile
  if (p) {
    form.value.name = p.decrypted_name || p.name || ''
    form.value.phone = p.decrypted_phone || p.phone || ''
    form.value.email = p.decrypted_email || p.email || ''
  }
})

async function handleSubmit(): Promise<void> {
  successMessage.value = ''
  authStore.error = null
  const data: { name?: string; phone?: string; email?: string } = {}
  if (form.value.name) data.name = form.value.name
  data.phone = form.value.phone || undefined
  data.email = form.value.email || undefined

  const success = await authStore.updateProfile(data)
  if (success) {
    successMessage.value = '프로필이 수정되었습니다.'
  }
}
</script>

<template>
  <div class="min-h-screen bg-[#F5F5F5] flex justify-center">
    <div class="w-full max-w-[402px] min-h-screen relative bg-[#F5F5F5] flex flex-col">
      <BackHeader title="프로필 수정" />

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
              <label class="block text-[13px] font-medium text-[#555] mb-1.5">이름 <span class="text-[#FF4444]">*</span></label>
              <input
                v-model="form.name"
                type="text"
                required
                class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] px-4 py-3.5 text-[15px] text-[#333] placeholder-[#B0B0B0] outline-none focus:border-[#4CAF50] transition-colors"
              />
            </div>

            <div>
              <label class="block text-[13px] font-medium text-[#555] mb-1.5">연락처</label>
              <input
                v-model="form.phone"
                type="tel"
                placeholder="010-0000-0000"
                class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] px-4 py-3.5 text-[15px] text-[#333] placeholder-[#B0B0B0] outline-none focus:border-[#4CAF50] transition-colors"
              />
            </div>

            <div>
              <label class="block text-[13px] font-medium text-[#555] mb-1.5">이메일</label>
              <input
                v-model="form.email"
                type="email"
                placeholder="example@email.com"
                class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] px-4 py-3.5 text-[15px] text-[#333] placeholder-[#B0B0B0] outline-none focus:border-[#4CAF50] transition-colors"
              />
            </div>

            <button
              type="submit"
              :disabled="!form.name.trim() || authStore.loading"
              class="w-full bg-[#4CAF50] text-white rounded-[12px] py-3.5 text-[15px] font-semibold mt-1 transition-all active:scale-[0.98] hover:bg-[#388E3C] disabled:opacity-50 disabled:cursor-not-allowed disabled:active:scale-100"
            >
              {{ authStore.loading ? '저장 중...' : '저장' }}
            </button>
          </form>
        </div>

        <!-- 비밀번호 변경 바로가기 -->
        <button
          class="w-full bg-white rounded-[16px] shadow-[0_0_6px_rgba(0,0,0,0.06)] px-4 py-3.5 flex items-center justify-between"
          @click="router.push({ name: 'change-password' })"
        >
          <span class="text-[14px] text-[#333]">비밀번호 변경</span>
          <svg class="w-4 h-4 text-[#CCC]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M9 18l6-6-6-6" />
          </svg>
        </button>
      </main>

      <BottomNav />
    </div>
  </div>
</template>
