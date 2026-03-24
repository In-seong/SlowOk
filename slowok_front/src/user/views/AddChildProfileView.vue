<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/authStore'
import BackHeader from '@shared/components/layout/BackHeader.vue'

const router = useRouter()
const authStore = useAuthStore()

const form = ref({
  name: '',
  birth_date: '',
  phone: '',
})

const isFormValid = computed(() => form.value.name.trim() !== '')

async function handleSubmit(): Promise<void> {
  const data: { name: string; birth_date?: string; phone?: string } = {
    name: form.value.name,
  }
  if (form.value.birth_date) {
    data.birth_date = form.value.birth_date
  }
  if (form.value.phone) {
    data.phone = form.value.phone
  }

  const success = await authStore.addChildProfile(data)
  if (success) {
    router.push({ name: 'profile-select' })
  }
}
</script>

<template>
  <div class="min-h-screen bg-gradient-to-b from-[#E8F5E9] to-white flex flex-col">
    <!-- Header -->
    <BackHeader title="자녀 프로필 추가" />

    <!-- Content -->
    <div class="flex-1 px-5 pt-4 pb-8">
      <p class="text-[14px] text-[#888] mb-4">자녀의 정보를 입력해주세요</p>

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

        <form @submit.prevent="handleSubmit" class="flex flex-col gap-4">
          <!-- Name (필수) -->
          <div>
            <label class="block text-[13px] font-medium text-[#555] mb-1.5">이름 <span class="text-[#FF4444]">*</span></label>
            <input
              v-model="form.name"
              type="text"
              placeholder="자녀의 이름을 입력하세요"
              required
              class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] px-4 py-3.5 text-[15px] text-[#333] placeholder-[#B0B0B0] outline-none focus:border-[#4CAF50] transition-colors"
            />
          </div>

          <!-- Birth Date (선택) -->
          <div>
            <label class="block text-[13px] font-medium text-[#555] mb-1.5">생년월일</label>
            <input
              v-model="form.birth_date"
              type="date"
              class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] px-4 py-3.5 text-[15px] text-[#333] placeholder-[#B0B0B0] outline-none focus:border-[#4CAF50] transition-colors"
            />
          </div>

          <!-- Phone (선택) -->
          <div>
            <label class="block text-[13px] font-medium text-[#555] mb-1.5">연락처</label>
            <input
              v-model="form.phone"
              type="tel"
              placeholder="010-0000-0000"
              class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] px-4 py-3.5 text-[15px] text-[#333] placeholder-[#B0B0B0] outline-none focus:border-[#4CAF50] transition-colors"
            />
          </div>

          <!-- Submit Button -->
          <button
            type="submit"
            :disabled="!isFormValid || authStore.loading"
            class="w-full bg-[#4CAF50] text-white rounded-[12px] py-3.5 text-[15px] font-semibold mt-1 transition-all active:scale-[0.98] hover:bg-[#388E3C] disabled:opacity-50 disabled:cursor-not-allowed disabled:active:scale-100"
          >
            {{ authStore.loading ? '추가 중...' : '자녀 추가' }}
          </button>
        </form>
      </div>
    </div>
  </div>
</template>
