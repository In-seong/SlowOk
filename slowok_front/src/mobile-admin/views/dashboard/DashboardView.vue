<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@shared/api'
import { useToastStore } from '@shared/stores/toastStore'

const router = useRouter()
const toast = useToastStore()

interface DailyActivity {
  date: string
  day: string
  screenings: number
  learning_completed: number
  challenges: number
}

interface RecentScreening {
  result_id: number
  profile_name: string
  test_title: string
  score: number
  level: string | null
  date: string | null
}

interface RecentUser {
  account_id: number
  username: string
  name: string
  user_type: string
  date: string | null
}

interface WeeklyLearning {
  label: string
  completed: number
  started: number
}

interface MonthlyScreening {
  label: string
  count: number
  avg_score: number | null
}

interface DashboardData {
  total_users: number
  total_learners: number
  total_contents: number
  total_screenings: number
  completion_rate: number
  total_assignments: number
  completed_assignments: number
  daily_activity: DailyActivity[]
  recent_screenings: RecentScreening[]
  recent_users: RecentUser[]
  weekly_learning: WeeklyLearning[]
  monthly_screening: MonthlyScreening[]
}

const data = ref<DashboardData | null>(null)
const loading = ref(true)

onMounted(async () => {
  try {
    const res = await api.get('/admin/dashboard')
    if (res.data.success) data.value = res.data.data
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    toast.error(err.response?.data?.message || '대시보드 데이터를 불러오지 못했습니다.')
  } finally {
    loading.value = false
  }
})

function levelColor(level: string | null): string {
  switch (level) {
    case '우수': return 'text-[#4CAF50]'
    case '양호': return 'text-[#2196F3]'
    case '보통': return 'text-[#FF9800]'
    case '주의': return 'text-[#FF4444]'
    default: return 'text-[#888]'
  }
}

const maxDailyTotal = computed(() => {
  if (!data.value || data.value.daily_activity.length === 0) return 1
  return Math.max(...data.value.daily_activity.map(d => d.screenings + d.learning_completed + d.challenges), 1)
})

function barHeight(value: number): string {
  return `${Math.max((value / maxDailyTotal.value) * 100, 4)}%`
}

const maxMonthly = computed(() => {
  if (!data.value || data.value.monthly_screening.length === 0) return 1
  return Math.max(...data.value.monthly_screening.map(m => m.count), 1)
})
</script>

<template>
  <div class="min-h-screen bg-[#FAFAFA]">
    <header class="sticky top-0 z-40 bg-white border-b border-[#E8E8E8] h-[56px] flex items-center px-4">
      <button @click="router.back()" class="w-10 h-10 flex items-center justify-center">
        <svg class="w-5 h-5 text-[#333]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
      </button>
      <h1 class="flex-1 text-center text-[16px] font-bold text-[#333]">대시보드</h1>
      <div class="w-10" />
    </header>

    <div v-if="loading" class="flex justify-center py-20">
      <div class="w-8 h-8 border-3 border-[#4CAF50] border-t-transparent rounded-full animate-spin" />
    </div>

    <div v-else-if="data" class="px-4 py-5 space-y-5">
      <!-- 요약 카드 -->
      <div class="grid grid-cols-2 gap-3">
        <div class="bg-white rounded-[16px] shadow-sm border border-[#E8E8E8] p-4 text-center">
          <p class="text-[22px] font-bold text-[#333]">{{ data.total_learners }}</p>
          <p class="text-[12px] text-[#888] mt-0.5">학습자</p>
        </div>
        <div class="bg-white rounded-[16px] shadow-sm border border-[#E8E8E8] p-4 text-center">
          <p class="text-[22px] font-bold text-[#333]">{{ data.total_screenings }}</p>
          <p class="text-[12px] text-[#888] mt-0.5">진단 완료</p>
        </div>
        <div class="bg-white rounded-[16px] shadow-sm border border-[#E8E8E8] p-4 text-center">
          <p class="text-[22px] font-bold text-[#4CAF50]">{{ data.completion_rate ?? 0 }}%</p>
          <p class="text-[12px] text-[#888] mt-0.5">학습 완료율</p>
        </div>
        <div class="bg-white rounded-[16px] shadow-sm border border-[#E8E8E8] p-4 text-center">
          <p class="text-[22px] font-bold text-[#333]">{{ data.total_contents }}</p>
          <p class="text-[12px] text-[#888] mt-0.5">콘텐츠</p>
        </div>
      </div>

      <!-- 최근 7일 활동 -->
      <div v-if="data.daily_activity.length > 0" class="bg-white rounded-[16px] shadow-sm border border-[#E8E8E8] p-4">
        <p class="text-[15px] font-semibold text-[#333] mb-3">최근 7일 활동</p>
        <div class="flex items-end gap-2 h-[120px]">
          <div v-for="d in data.daily_activity" :key="d.date" class="flex-1 flex flex-col items-center gap-1">
            <div class="w-full flex flex-col items-center justify-end h-[90px]">
              <div class="w-full max-w-[24px] bg-[#4CAF50] rounded-t-[4px]" :style="{ height: barHeight(d.screenings + d.learning_completed + d.challenges) }" />
            </div>
            <span class="text-[10px] text-[#888]">{{ d.day }}</span>
          </div>
        </div>
      </div>

      <!-- 월간 진단 현황 -->
      <div v-if="data.monthly_screening.length > 0" class="bg-white rounded-[16px] shadow-sm border border-[#E8E8E8] p-4">
        <p class="text-[15px] font-semibold text-[#333] mb-3">월간 진단 현황</p>
        <div class="flex items-end gap-2 h-[120px]">
          <div v-for="m in data.monthly_screening" :key="m.label" class="flex-1 flex flex-col items-center gap-1">
            <div class="w-full flex flex-col items-center justify-end h-[90px]">
              <div class="w-full max-w-[24px] bg-[#2196F3] rounded-t-[4px]" :style="{ height: `${Math.max((m.count / maxMonthly) * 100, 4)}%` }" />
            </div>
            <span class="text-[10px] text-[#888]">{{ m.label }}</span>
          </div>
        </div>
      </div>

      <!-- 최근 진단 결과 -->
      <div v-if="data.recent_screenings.length > 0" class="bg-white rounded-[16px] shadow-sm border border-[#E8E8E8] overflow-hidden">
        <div class="px-4 pt-4 pb-2">
          <p class="text-[15px] font-semibold text-[#333]">최근 진단 결과</p>
        </div>
        <div class="divide-y divide-[#F0F0F0]">
          <div v-for="item in data.recent_screenings" :key="item.result_id" class="px-4 py-3 flex items-center justify-between">
            <div>
              <p class="text-[14px] font-semibold text-[#333]">{{ item.profile_name }}</p>
              <p class="text-[12px] text-[#888] mt-0.5">{{ item.test_title }}</p>
            </div>
            <div class="text-right">
              <p class="text-[14px] font-semibold text-[#333]">{{ item.score }}점</p>
              <p class="text-[12px] font-medium mt-0.5" :class="levelColor(item.level)">{{ item.level || '-' }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- 최근 가입자 -->
      <div v-if="data.recent_users.length > 0" class="bg-white rounded-[16px] shadow-sm border border-[#E8E8E8] overflow-hidden">
        <div class="px-4 pt-4 pb-2">
          <p class="text-[15px] font-semibold text-[#333]">최근 가입자</p>
        </div>
        <div class="divide-y divide-[#F0F0F0]">
          <div v-for="u in data.recent_users" :key="u.account_id" class="px-4 py-3 flex items-center justify-between">
            <div>
              <p class="text-[14px] font-semibold text-[#333]">{{ u.name }}</p>
              <p class="text-[12px] text-[#888] mt-0.5">{{ u.username }}</p>
            </div>
            <p class="text-[12px] text-[#888]">{{ u.date || '-' }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
