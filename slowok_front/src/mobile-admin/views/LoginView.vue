<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAdminAuthStore } from '@admin/stores/adminAuthStore'
import FormInput from '@shared/components/form/FormInput.vue'
import ActionButton from '@shared/components/ui/ActionButton.vue'

const router = useRouter()
const authStore = useAdminAuthStore()

const username = ref('')
const password = ref('')
const loading = ref(false)
const errorMsg = ref('')

async function handleLogin() {
  if (!username.value.trim() || !password.value.trim()) {
    errorMsg.value = '아이디와 비밀번호를 입력해주세요.'
    return
  }
  loading.value = true
  errorMsg.value = ''
  try {
    const success = await authStore.login(username.value, password.value)
    if (success) {
      router.replace({ name: 'home' })
    } else {
      errorMsg.value = authStore.error || '로그인에 실패했습니다.'
    }
  } catch {
    errorMsg.value = '로그인 중 오류가 발생했습니다.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen flex flex-col items-center justify-center px-6 bg-[#FAFAFA]">
    <div class="w-full max-w-[360px]">
      <div class="text-center mb-10">
        <h1 class="text-[20px] font-bold text-[#4CAF50]">느려도 괜찮아</h1>
        <p class="text-[14px] text-[#888] mt-1">관리자</p>
      </div>

      <form class="space-y-4" @submit.prevent="handleLogin">
        <FormInput
          v-model="username"
          placeholder="아이디"
        />
        <FormInput
          v-model="password"
          type="password"
          placeholder="비밀번호"
        />

        <p v-if="errorMsg" class="text-[13px] text-[#FF4444] text-center">{{ errorMsg }}</p>

        <ActionButton
          :full="true"
          :disabled="loading"
          @click="handleLogin"
        >
          {{ loading ? '로그인 중...' : '로그인' }}
        </ActionButton>
      </form>
    </div>
  </div>
</template>
