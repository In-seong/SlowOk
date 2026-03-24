<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/authStore'
import api from '@shared/api'
import type { ChildDashboardData } from '@shared/types'
import BackHeader from '@shared/components/layout/BackHeader.vue'
import BottomNav from '@shared/components/layout/BottomNav.vue'
import ProgressBar from '@shared/components/ui/ProgressBar.vue'
import { useToastStore } from '@shared/stores/toastStore'

const router = useRouter()
const authStore = useAuthStore()
const toast = useToastStore()

const children = ref<ChildDashboardData[]>([])
const pageLoading = ref(true)

onMounted(async () => {
  try {
    const response = await api.get('/user/parent-dashboard')
    if (response.data.success) {
      children.value = response.data.data
    }
  } catch (e: any) {
    toast.error(e.response?.data?.message || '데이터를 불러오지 못했습니다.')
  } finally {
    pageLoading.value = false
  }
})

function switchToChild(profileId: number): void {
  authStore.switchProfile(profileId)
  router.push({ name: 'home' })
}

function getLearningPercent(child: ChildDashboardData): number {
  if (child.learning.total === 0) return 0
  return Math.round((child.learning.completed / child.learning.total) * 100)
}

function formatDate(dateStr: string | null): string {
  if (!dateStr) return '-'
  const d = new Date(dateStr)
  return `${d.getMonth() + 1}/${d.getDate()}`
}
</script>

<template>
  <div class="min-h-screen bg-[#F5F5F5] flex justify-center">
    <div class="w-full max-w-[402px] min-h-screen relative bg-[#F5F5F5] flex flex-col">
      <BackHeader title="자녀 학습현황" />

      <main class="flex-1 px-5 pb-[80px] pt-[60px] space-y-4 overflow-y-auto">
        <!-- Loading -->
        <div v-if="pageLoading" class="flex flex-col items-center justify-center py-20">
          <div class="w-8 h-8 border-3 border-[#4CAF50] border-t-transparent rounded-full animate-spin"></div>
          <p class="text-[13px] text-[#888] mt-3">불러오는 중...</p>
        </div>

        <template v-else>
          <!-- Empty State -->
          <div v-if="children.length === 0" class="text-center py-20">
            <p class="text-[14px] text-[#888] mb-4">등록된 자녀가 없습니다</p>
            <button
              class="bg-[#4CAF50] text-white rounded-[12px] px-6 py-3 text-[14px] font-semibold"
              @click="router.push({ name: 'add-child' })"
            >
              자녀 추가하기
            </button>
          </div>

          <!-- Child Cards -->
          <div v-for="child in children" :key="child.profile_id" class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] p-5 space-y-4">
            <!-- Child Header -->
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-[#4CAF50] flex items-center justify-center">
                  <span class="text-[16px] font-bold text-white">{{ (child.decrypted_name || child.name).charAt(0) }}</span>
                </div>
                <div>
                  <h3 class="text-[15px] font-bold text-[#333]">{{ child.decrypted_name || child.name }}</h3>
                  <p v-if="child.latest_activity_at" class="text-[11px] text-[#999]">
                    최근 활동: {{ formatDate(child.latest_activity_at) }}
                  </p>
                </div>
              </div>
              <button
                class="text-[12px] text-[#4CAF50] font-semibold px-3 py-1.5 rounded-[8px] border border-[#4CAF50] hover:bg-[#E8F5E9] transition-colors"
                @click="switchToChild(child.profile_id)"
              >
                전환
              </button>
            </div>

            <!-- Screening -->
            <div v-if="child.latest_screening" class="bg-[#F8F8F8] rounded-[12px] p-3">
              <p class="text-[12px] text-[#888] mb-1">최근 진단</p>
              <div class="flex items-center justify-between">
                <span class="text-[13px] text-[#333]">{{ child.latest_screening.test_title || '진단검사' }}</span>
                <div class="flex items-center gap-2">
                  <span class="text-[14px] font-bold text-[#4CAF50]">{{ child.latest_screening.score }}점</span>
                  <span
                    class="text-[11px] px-2 py-0.5 rounded-full"
                    :class="child.latest_screening.level === '우수' ? 'bg-[#E8F5E9] text-[#4CAF50]' : child.latest_screening.level === '보통' ? 'bg-[#FFF3E0] text-[#FF9800]' : 'bg-[#FFE5E5] text-[#F44336]'"
                  >
                    {{ child.latest_screening.level }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Learning Progress -->
            <div>
              <div class="flex items-center justify-between mb-2">
                <p class="text-[12px] text-[#888]">학습 진행</p>
                <span class="text-[12px] text-[#555]">{{ child.learning.completed }}/{{ child.learning.total }} 완료</span>
              </div>
              <ProgressBar :value="getLearningPercent(child)" variant="success" />
            </div>

            <!-- Challenge Stats -->
            <div class="flex gap-3">
              <div class="flex-1 bg-[#FFF3E0] rounded-[10px] py-2.5 text-center">
                <p class="text-[16px] font-bold text-[#FF9800]">{{ child.challenge.total }}</p>
                <p class="text-[11px] text-[#999] mt-0.5">챌린지 시도</p>
              </div>
              <div class="flex-1 bg-[#E8F5E9] rounded-[10px] py-2.5 text-center">
                <p class="text-[16px] font-bold text-[#4CAF50]">{{ child.challenge.passed }}</p>
                <p class="text-[11px] text-[#999] mt-0.5">챌린지 합격</p>
              </div>
            </div>

            <!-- 녹음 보기 버튼 -->
            <button
              class="w-full flex items-center justify-center gap-2 py-2.5 bg-[#F8F8F8] rounded-[10px] text-[13px] text-[#555] font-medium hover:bg-[#F0F0F0] transition-colors"
              @click="router.push({ name: 'child-recordings', params: { id: child.profile_id } })"
            >
              <svg class="w-4 h-4 text-[#888]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M12 18.75a6 6 0 006-6v-1.5m-6 7.5a6 6 0 01-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 01-3-3V4.5a3 3 0 116 0v8.25a3 3 0 01-3 3z" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
              녹음 듣기 / 피드백
            </button>
          </div>
        </template>
      </main>

      <BottomNav />
    </div>
  </div>
</template>
