<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import api from '@shared/api'
import { useToastStore } from '@shared/stores/toastStore'

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

const data = ref<DashboardData>({
  total_users: 0,
  total_learners: 0,
  total_contents: 0,
  total_screenings: 0,
  completion_rate: 0,
  total_assignments: 0,
  completed_assignments: 0,
  daily_activity: [],
  recent_screenings: [],
  recent_users: [],
  weekly_learning: [],
  monthly_screening: [],
})
const loading = ref(true)

onMounted(async () => {
  try {
    const response = await api.get('/admin/dashboard')
    if (response.data.success) {
      data.value = response.data.data
    }
  } catch (e: any) {
    toast.error(e.response?.data?.message || '대시보드 데이터를 불러오지 못했습니다.')
  } finally {
    loading.value = false
  }
})

// 차트 바 높이 계산 (최대값 기준)
const maxDailyTotal = computed(() => {
  if (data.value.daily_activity.length === 0) return 1
  const max = Math.max(
    ...data.value.daily_activity.map(
      (d) => d.screenings + d.learning_completed + d.challenges,
    ),
  )
  return max || 1
})

function barHeight(value: number): string {
  const pct = Math.max((value / maxDailyTotal.value) * 100, 4)
  return `${pct}%`
}

function levelColor(level: string | null): string {
  switch (level) {
    case '우수': return 'bg-green-50 text-green-600'
    case '보통': return 'bg-blue-50 text-blue-600'
    case '노력': return 'bg-orange-50 text-orange-600'
    default: return 'bg-gray-50 text-gray-500'
  }
}

function userTypeLabel(type: string): string {
  return type === 'LEARNER' ? '학습자' : type === 'PARENT' ? '학부모' : type
}

// --- 주간 학습 추이 라인 차트 ---
const weeklyMaxVal = computed(() => {
  const vals = data.value.weekly_learning.flatMap(w => [w.completed, w.started])
  return Math.max(...vals, 1)
})

function weeklyLinePath(key: 'completed' | 'started'): string {
  const items = data.value.weekly_learning
  if (items.length === 0) return ''
  const w = 440
  const h = 140
  const pad = 10
  const stepX = (w - pad * 2) / Math.max(items.length - 1, 1)
  return items
    .map((item, i) => {
      const x = pad + i * stepX
      const y = h - pad - ((item[key] / weeklyMaxVal.value) * (h - pad * 2))
      return `${i === 0 ? 'M' : 'L'}${x.toFixed(1)},${y.toFixed(1)}`
    })
    .join(' ')
}

function weeklyAreaPath(key: 'completed' | 'started'): string {
  const items = data.value.weekly_learning
  if (items.length === 0) return ''
  const w = 440
  const h = 140
  const pad = 10
  const stepX = (w - pad * 2) / Math.max(items.length - 1, 1)
  const points = items.map((item, i) => {
    const x = pad + i * stepX
    const y = h - pad - ((item[key] / weeklyMaxVal.value) * (h - pad * 2))
    return { x, y }
  })
  const first = points[0]
  const last = points[points.length - 1]
  if (!first || !last) return ''
  const linePart = points.map((p, i) => `${i === 0 ? 'M' : 'L'}${p.x.toFixed(1)},${p.y.toFixed(1)}`).join(' ')
  return `${linePart} L${last.x.toFixed(1)},${(h - pad).toFixed(1)} L${first.x.toFixed(1)},${(h - pad).toFixed(1)} Z`
}

function weeklyPointPos(key: 'completed' | 'started', index: number): { cx: number; cy: number } {
  const items = data.value.weekly_learning
  const w = 440
  const h = 140
  const pad = 10
  const stepX = (w - pad * 2) / Math.max(items.length - 1, 1)
  const item = items[index]
  if (!item) return { cx: 0, cy: 0 }
  return {
    cx: pad + index * stepX,
    cy: h - pad - ((item[key] / weeklyMaxVal.value) * (h - pad * 2)),
  }
}

// --- 월간 진단 점수 차트 ---
const monthlyMaxCount = computed(() => {
  return Math.max(...data.value.monthly_screening.map(m => m.count), 1)
})

function monthlyBarHeight(count: number): string {
  const pct = Math.max((count / monthlyMaxCount.value) * 100, 4)
  return `${pct}%`
}
</script>

<template>
  <div class="p-6">
    <h2 class="text-xl font-bold text-[#333] mb-6">대시보드</h2>

    <div v-if="loading" class="text-center py-20 text-[#888]">불러오는 중...</div>

    <template v-else>
      <!-- 1. 요약 카드 (4개) -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-[16px] border border-[#E8E8E8] p-5 flex items-start gap-4">
          <div class="w-11 h-11 rounded-[12px] bg-[#E3F2FD] flex items-center justify-center shrink-0">
            <svg class="w-5 h-5 text-[#2196F3]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
            </svg>
          </div>
          <div>
            <p class="text-[12px] text-[#888] mb-0.5">전체 사용자</p>
            <p class="text-[24px] font-bold text-[#333] leading-tight">{{ data.total_users }}</p>
          </div>
        </div>

        <div class="bg-white rounded-[16px] border border-[#E8E8E8] p-5 flex items-start gap-4">
          <div class="w-11 h-11 rounded-[12px] bg-[#E8F5E9] flex items-center justify-center shrink-0">
            <svg class="w-5 h-5 text-[#4CAF50]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.636 50.636 0 00-2.658-.813A59.906 59.906 0 0112 3.493a59.903 59.903 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5" />
            </svg>
          </div>
          <div>
            <p class="text-[12px] text-[#888] mb-0.5">학습자</p>
            <p class="text-[24px] font-bold text-[#4CAF50] leading-tight">{{ data.total_learners }}</p>
          </div>
        </div>

        <div class="bg-white rounded-[16px] border border-[#E8E8E8] p-5 flex items-start gap-4">
          <div class="w-11 h-11 rounded-[12px] bg-[#FFF3E0] flex items-center justify-center shrink-0">
            <svg class="w-5 h-5 text-[#FF9800]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
            </svg>
          </div>
          <div>
            <p class="text-[12px] text-[#888] mb-0.5">학습 콘텐츠</p>
            <p class="text-[24px] font-bold text-[#FF9800] leading-tight">{{ data.total_contents }}</p>
          </div>
        </div>

        <div class="bg-white rounded-[16px] border border-[#E8E8E8] p-5 flex items-start gap-4">
          <div class="w-11 h-11 rounded-[12px] bg-[#F3E5F5] flex items-center justify-center shrink-0">
            <svg class="w-5 h-5 text-[#9C27B0]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15a2.25 2.25 0 012.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />
            </svg>
          </div>
          <div>
            <p class="text-[12px] text-[#888] mb-0.5">진단 완료</p>
            <p class="text-[24px] font-bold text-[#9C27B0] leading-tight">{{ data.total_screenings }}</p>
          </div>
        </div>
      </div>

      <!-- 2. 학습 완료율 + 7일 활동 추이 -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
        <!-- 학습 완료율 -->
        <div class="bg-white rounded-[16px] border border-[#E8E8E8] p-5">
          <h3 class="text-[14px] font-semibold text-[#333] mb-4">학습 완료율</h3>
          <div class="flex items-center justify-center mb-4">
            <div class="relative w-[120px] h-[120px]">
              <svg class="w-[120px] h-[120px] -rotate-90" viewBox="0 0 100 100">
                <circle cx="50" cy="50" r="40" fill="none" stroke="#F0F0F0" stroke-width="10" />
                <circle
                  cx="50" cy="50" r="40"
                  fill="none"
                  stroke="#4CAF50"
                  stroke-width="10"
                  stroke-linecap="round"
                  :stroke-dasharray="251.33"
                  :stroke-dashoffset="251.33 - (251.33 * data.completion_rate) / 100"
                  class="transition-all duration-700"
                />
              </svg>
              <div class="absolute inset-0 flex flex-col items-center justify-center">
                <span class="text-[28px] font-bold text-[#333]">{{ data.completion_rate }}%</span>
              </div>
            </div>
          </div>
          <div class="text-center space-y-1">
            <p class="text-[13px] text-[#888]">
              전체 <span class="font-semibold text-[#333]">{{ data.total_assignments }}</span>건 중
              <span class="font-semibold text-[#4CAF50]">{{ data.completed_assignments }}</span>건 완료
            </p>
          </div>
        </div>

        <!-- 7일 활동 추이 -->
        <div class="lg:col-span-2 bg-white rounded-[16px] border border-[#E8E8E8] p-5">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-[14px] font-semibold text-[#333]">최근 7일 활동</h3>
            <div class="flex items-center gap-4 text-[11px]">
              <span class="flex items-center gap-1">
                <span class="w-2.5 h-2.5 rounded-full bg-[#9C27B0]"></span> 진단
              </span>
              <span class="flex items-center gap-1">
                <span class="w-2.5 h-2.5 rounded-full bg-[#4CAF50]"></span> 학습완료
              </span>
              <span class="flex items-center gap-1">
                <span class="w-2.5 h-2.5 rounded-full bg-[#FF9800]"></span> 챌린지
              </span>
            </div>
          </div>

          <div v-if="data.daily_activity.length > 0" class="flex items-end justify-between gap-2 h-[160px]">
            <div
              v-for="day in data.daily_activity"
              :key="day.date"
              class="flex-1 flex flex-col items-center gap-1"
            >
              <!-- 바 -->
              <div class="w-full flex items-end justify-center gap-[2px] h-[120px]">
                <div
                  class="w-[8px] rounded-t-[3px] bg-[#9C27B0] transition-all duration-500"
                  :style="{ height: barHeight(day.screenings) }"
                  :title="`진단: ${day.screenings}건`"
                ></div>
                <div
                  class="w-[8px] rounded-t-[3px] bg-[#4CAF50] transition-all duration-500"
                  :style="{ height: barHeight(day.learning_completed) }"
                  :title="`학습완료: ${day.learning_completed}건`"
                ></div>
                <div
                  class="w-[8px] rounded-t-[3px] bg-[#FF9800] transition-all duration-500"
                  :style="{ height: barHeight(day.challenges) }"
                  :title="`챌린지: ${day.challenges}건`"
                ></div>
              </div>
              <!-- 라벨 -->
              <div class="text-center">
                <p class="text-[11px] text-[#888]">{{ day.day }}</p>
                <p class="text-[10px] text-[#BBB]">{{ day.date }}</p>
              </div>
            </div>
          </div>
          <div v-else class="h-[160px] flex items-center justify-center text-[13px] text-[#888]">
            활동 데이터가 없습니다.
          </div>
        </div>
      </div>

      <!-- 3. 주간 학습 추이 + 월간 진단 점수 -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
        <!-- 주간 학습 완료 추이 (라인 차트) -->
        <div class="bg-white rounded-[16px] border border-[#E8E8E8] p-5">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-[14px] font-semibold text-[#333]">주간 학습 추이 (8주)</h3>
            <div class="flex items-center gap-4 text-[11px]">
              <span class="flex items-center gap-1">
                <span class="w-2.5 h-2.5 rounded-full bg-[#4CAF50]"></span> 완료
              </span>
              <span class="flex items-center gap-1">
                <span class="w-2.5 h-2.5 rounded-full bg-[#2196F3]"></span> 시작
              </span>
            </div>
          </div>

          <div v-if="data.weekly_learning.length > 0" class="relative">
            <svg viewBox="0 0 440 140" class="w-full h-[160px]">
              <!-- 가로 눈금선 -->
              <line x1="10" y1="10" x2="430" y2="10" stroke="#F0F0F0" stroke-width="0.5" />
              <line x1="10" y1="43" x2="430" y2="43" stroke="#F0F0F0" stroke-width="0.5" />
              <line x1="10" y1="76" x2="430" y2="76" stroke="#F0F0F0" stroke-width="0.5" />
              <line x1="10" y1="109" x2="430" y2="109" stroke="#F0F0F0" stroke-width="0.5" />
              <line x1="10" y1="130" x2="430" y2="130" stroke="#E8E8E8" stroke-width="0.5" />

              <!-- 시작 영역 -->
              <path :d="weeklyAreaPath('started')" fill="#2196F3" fill-opacity="0.08" />
              <path :d="weeklyLinePath('started')" fill="none" stroke="#2196F3" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />

              <!-- 완료 영역 -->
              <path :d="weeklyAreaPath('completed')" fill="#4CAF50" fill-opacity="0.12" />
              <path :d="weeklyLinePath('completed')" fill="none" stroke="#4CAF50" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />

              <!-- 포인트 -->
              <circle
                v-for="(_, i) in data.weekly_learning"
                :key="'c' + i"
                :cx="weeklyPointPos('completed', i).cx"
                :cy="weeklyPointPos('completed', i).cy"
                r="3" fill="#4CAF50"
              />
              <circle
                v-for="(_, i) in data.weekly_learning"
                :key="'s' + i"
                :cx="weeklyPointPos('started', i).cx"
                :cy="weeklyPointPos('started', i).cy"
                r="2.5" fill="#2196F3"
              />
            </svg>
            <!-- X축 라벨 -->
            <div class="flex justify-between px-[10px] mt-1">
              <span v-for="w in data.weekly_learning" :key="w.label" class="text-[10px] text-[#999]">{{ w.label }}</span>
            </div>
          </div>
          <div v-else class="h-[160px] flex items-center justify-center text-[13px] text-[#888]">
            학습 데이터가 없습니다.
          </div>
        </div>

        <!-- 월간 진단 현황 (바 + 평균 점수) -->
        <div class="bg-white rounded-[16px] border border-[#E8E8E8] p-5">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-[14px] font-semibold text-[#333]">월간 진단 현황 (6개월)</h3>
            <div class="flex items-center gap-4 text-[11px]">
              <span class="flex items-center gap-1">
                <span class="w-2.5 h-2.5 rounded-[2px] bg-[#9C27B0]"></span> 검사 수
              </span>
              <span class="flex items-center gap-1">
                <span class="w-2.5 h-0.5 bg-[#FF9800] inline-block"></span> 평균 점수
              </span>
            </div>
          </div>

          <div v-if="data.monthly_screening.length > 0">
            <div class="flex items-end justify-between gap-3 h-[140px] px-2">
              <div
                v-for="m in data.monthly_screening"
                :key="m.label"
                class="flex-1 flex flex-col items-center gap-1"
              >
                <!-- 평균 점수 라벨 -->
                <span v-if="m.avg_score !== null" class="text-[11px] font-bold text-[#FF9800]">
                  {{ m.avg_score }}
                </span>
                <span v-else class="text-[11px] text-[#CCC]">-</span>

                <!-- 바 -->
                <div class="w-full flex items-end justify-center h-[90px]">
                  <div
                    class="w-[28px] rounded-t-[6px] bg-gradient-to-t from-[#9C27B0] to-[#CE93D8] transition-all duration-500"
                    :style="{ height: monthlyBarHeight(m.count) }"
                  ></div>
                </div>

                <!-- 검사 수 + 라벨 -->
                <span class="text-[11px] font-medium text-[#555]">{{ m.count }}건</span>
                <span class="text-[10px] text-[#999]">{{ m.label }}</span>
              </div>
            </div>
          </div>
          <div v-else class="h-[160px] flex items-center justify-center text-[13px] text-[#888]">
            진단 데이터가 없습니다.
          </div>
        </div>
      </div>

      <!-- 4. 최근 진단 결과 + 최근 가입자 -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <!-- 최근 진단 결과 -->
        <div class="bg-white rounded-[16px] border border-[#E8E8E8] p-5">
          <h3 class="text-[14px] font-semibold text-[#333] mb-4">최근 진단 결과</h3>
          <div v-if="data.recent_screenings.length === 0" class="py-6 text-center text-[13px] text-[#888]">
            진단 결과가 없습니다.
          </div>
          <div v-else class="space-y-0">
            <div
              v-for="(item, index) in data.recent_screenings"
              :key="item.result_id"
              class="flex items-center gap-3 py-3"
              :class="index < data.recent_screenings.length - 1 ? 'border-b border-[#F5F5F5]' : ''"
            >
              <div class="w-9 h-9 rounded-full bg-[#F5F5F5] flex items-center justify-center shrink-0">
                <span class="text-[12px] font-bold text-[#888]">{{ item.profile_name.charAt(0) }}</span>
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                  <span class="text-[13px] font-medium text-[#333] truncate">{{ item.profile_name }}</span>
                  <span
                    class="px-1.5 py-0.5 rounded-full text-[10px] shrink-0"
                    :class="levelColor(item.level)"
                  >
                    {{ item.level ?? '-' }}
                  </span>
                </div>
                <p class="text-[12px] text-[#888] truncate">{{ item.test_title }}</p>
              </div>
              <div class="text-right shrink-0">
                <p class="text-[15px] font-bold text-[#333]">{{ item.score }}점</p>
                <p class="text-[11px] text-[#BBB]">{{ item.date }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- 최근 가입자 -->
        <div class="bg-white rounded-[16px] border border-[#E8E8E8] p-5">
          <h3 class="text-[14px] font-semibold text-[#333] mb-4">최근 가입자</h3>
          <div v-if="data.recent_users.length === 0" class="py-6 text-center text-[13px] text-[#888]">
            가입자가 없습니다.
          </div>
          <div v-else class="space-y-0">
            <div
              v-for="(user, index) in data.recent_users"
              :key="user.account_id"
              class="flex items-center gap-3 py-3"
              :class="index < data.recent_users.length - 1 ? 'border-b border-[#F5F5F5]' : ''"
            >
              <div class="w-9 h-9 rounded-full bg-[#E8F5E9] flex items-center justify-center shrink-0">
                <span class="text-[12px] font-bold text-[#4CAF50]">{{ user.name.charAt(0) }}</span>
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                  <span class="text-[13px] font-medium text-[#333] truncate">{{ user.name }}</span>
                  <span class="px-1.5 py-0.5 bg-[#F0F0F0] text-[#888] rounded-full text-[10px] shrink-0">
                    {{ userTypeLabel(user.user_type) }}
                  </span>
                </div>
                <p class="text-[12px] text-[#888]">{{ user.username }}</p>
              </div>
              <div class="text-right shrink-0">
                <p class="text-[11px] text-[#BBB]">{{ user.date }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
