<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useReportStore } from '../../stores/reportStore'
import { useLearningStore } from '../../stores/learningStore'
import { useAssessmentStore } from '../../stores/assessmentStore'
import type { LearningReport } from '@shared/types'
import { useToastStore } from '@shared/stores/toastStore'

const router = useRouter()
const reportStore = useReportStore()
const learningStore = useLearningStore()
const assessmentStore = useAssessmentStore()
const toast = useToastStore()

const pageLoading = ref(true)

onMounted(async () => {
  try {
    await Promise.all([
      reportStore.fetchReports(),
      learningStore.fetchContents(),
      assessmentStore.fetchAssessments(),
    ])
  } catch (e: any) {
    toast.error(e.response?.data?.message || '데이터를 불러오지 못했습니다.')
  } finally {
    pageLoading.value = false
  }
})

const selectedPeriod = ref<'weekly' | 'monthly'>('weekly')

// Extract summary data from latest report matching selected period
const latestReport = computed<LearningReport | null>(() => {
  if (reportStore.reports.length === 0) return null
  const filtered = reportStore.reports.filter(r => r.period === selectedPeriod.value)
  if (filtered.length === 0) {
    // 선택 기간에 해당하는 리포트가 없으면 전체에서 최신 리포트 사용
    const sorted = [...reportStore.reports].sort((a, b) =>
      (b.created_at || '').localeCompare(a.created_at || '')
    )
    return sorted[0] ?? null
  }
  const sorted = [...filtered].sort((a, b) =>
    (b.created_at || '').localeCompare(a.created_at || '')
  )
  return sorted[0] ?? null
})

const overallStats = computed(() => {
  const summary = latestReport.value?.summary
  const completedCount = learningStore.contents.filter(c => c.progress?.status === 'COMPLETED').length

  return {
    totalTime: summary?.total_time || summary?.totalTime || '\u2014',
    completedItems: summary?.completed_items ?? summary?.completedItems ?? completedCount,
    streakDays: summary?.streak_days ?? summary?.streakDays ?? '\u2014',
  }
})

// Build categories from assessments grouped by category
const categories = computed(() => {
  const categoryMap = new Map<number, { name: string; scores: number[] }>()

  for (const assessment of assessmentStore.assessments) {
    const catId = assessment.category_id
    const catName = assessment.category?.name || '기타'
    if (!categoryMap.has(catId)) {
      categoryMap.set(catId, { name: catName, scores: [] })
    }
    categoryMap.get(catId)!.scores.push(assessment.score)
  }

  const categoryStyles: Record<string, { barColor: string; iconBg: string; iconColor: string }> = {
    '언어': { barColor: 'bg-[#2196F3]', iconBg: 'bg-[#E3F2FD]', iconColor: 'text-[#2196F3]' },
    '인지': { barColor: 'bg-[#9C27B0]', iconBg: 'bg-[#F3E5F5]', iconColor: 'text-[#9C27B0]' },
    '정서': { barColor: 'bg-[#E91E63]', iconBg: 'bg-[#FCE4EC]', iconColor: 'text-[#E91E63]' },
    '사회성': { barColor: 'bg-[#FF8F00]', iconBg: 'bg-[#FFF8E1]', iconColor: 'text-[#FF8F00]' },
  }

  const defaultStyle = { barColor: 'bg-[#607D8B]', iconBg: 'bg-[#ECEFF1]', iconColor: 'text-[#607D8B]' }

  let id = 1
  const result: Array<{
    id: number
    name: string
    score: number
    trend: 'up' | 'down' | 'same'
    trendValue: number
    barColor: string
    iconBg: string
    iconColor: string
  }> = []

  for (const [, data] of categoryMap) {
    const avgScore = Math.round(data.scores.reduce((s, v) => s + v, 0) / data.scores.length)
    const style = categoryStyles[data.name] || defaultStyle

    // Trend: compare latest score with earlier average
    let trend: 'up' | 'down' | 'same' = 'same'
    let trendValue = 0
    if (data.scores.length >= 2) {
      const latest = data.scores[data.scores.length - 1] ?? 0
      const earlier = data.scores.slice(0, -1)
      const earlierAvg = Math.round(earlier.reduce((s, v) => s + v, 0) / earlier.length)
      trendValue = latest - earlierAvg
      if (trendValue > 0) trend = 'up'
      else if (trendValue < 0) { trend = 'down'; trendValue = Math.abs(trendValue) }
    }

    result.push({
      id: id++,
      name: data.name,
      score: avgScore,
      trend,
      trendValue,
      ...style,
    })
  }

  return result
})

// Weekly activity from report summary or empty fallback
interface DayActivity { day: string; value: number }
const weeklyActivity = computed<DayActivity[]>(() => {
  const summary = latestReport.value?.summary
  if (summary?.weekly_activity) return summary.weekly_activity as DayActivity[]
  if (summary?.weeklyActivity) return summary.weeklyActivity as DayActivity[]
  return [
    { day: '월', value: 0 },
    { day: '화', value: 0 },
    { day: '수', value: 0 },
    { day: '목', value: 0 },
    { day: '금', value: 0 },
    { day: '토', value: 0 },
    { day: '일', value: 0 },
  ]
})

const todayIndex = new Date().getDay() === 0 ? 6 : new Date().getDay() - 1
const maxActivity = computed(() => {
  const max = Math.max(...weeklyActivity.value.map((d: any) => d.value))
  return max > 0 ? max : 1
})

function getBarHeight(value: number): string {
  const percentage = Math.max((value / maxActivity.value) * 100, 8)
  return `${percentage}%`
}

function isMaxDay(index: number): boolean {
  const day = weeklyActivity.value[index]
  return day !== undefined && day.value === maxActivity.value && day.value > 0
}

// Recommendations from report summary or from learning contents
const recommendations = computed(() => {
  const summary = latestReport.value?.summary
  if (summary?.recommendations && Array.isArray(summary.recommendations)) {
    return summary.recommendations.slice(0, 3)
  }

  // Fallback: suggest incomplete learning contents
  const badgeStyles: Record<string, { badgeBg: string; badgeText: string }> = {
    '언어': { badgeBg: 'bg-[#E3F2FD]', badgeText: 'text-[#2196F3]' },
    '인지': { badgeBg: 'bg-[#F3E5F5]', badgeText: 'text-[#9C27B0]' },
    '정서': { badgeBg: 'bg-[#FCE4EC]', badgeText: 'text-[#E91E63]' },
    '사회성': { badgeBg: 'bg-[#FFF8E1]', badgeText: 'text-[#FF8F00]' },
  }
  const defaultBadge = { badgeBg: 'bg-[#ECEFF1]', badgeText: 'text-[#607D8B]' }

  return learningStore.contents
    .filter(c => c.progress?.status !== 'COMPLETED')
    .slice(0, 3)
    .map(c => {
      const catName = c.category?.name || '기타'
      const style = badgeStyles[catName] || defaultBadge
      return {
        id: c.content_id,
        title: c.title,
        category: catName,
        duration: '\u2014',
        ...style,
      }
    })
})

const hasData = computed(() =>
  reportStore.reports.length > 0 || assessmentStore.assessments.length > 0 || learningStore.contents.length > 0
)
</script>

<template>
  <div class="min-h-screen bg-[#F5F5F5] max-w-[402px] mx-auto">
    <!-- Back Header -->
    <header class="bg-white sticky top-0 z-10">
      <div class="flex items-center gap-[12px] px-[20px] py-[16px]">
        <button
          @click="router.back()"
          class="w-[32px] h-[32px] flex items-center justify-center rounded-full hover:bg-[#F5F5F5] active:scale-[0.98] transition-all"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="w-[20px] h-[20px] text-[#333]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
          </svg>
        </button>
        <h1 class="text-[18px] font-bold text-[#333]">학습 리포트</h1>
      </div>
    </header>

    <!-- Content -->
    <main class="px-[20px] pb-[80px] pt-[16px] space-y-[16px]">
      <!-- Loading State -->
      <div v-if="pageLoading" class="flex flex-col items-center justify-center py-20">
        <div class="w-8 h-8 border-3 border-[#4CAF50] border-t-transparent rounded-full animate-spin"></div>
        <p class="text-[13px] text-[#888] mt-3">불러오는 중...</p>
      </div>

      <!-- Error State -->
      <div v-else-if="reportStore.error && !hasData" class="flex flex-col items-center justify-center py-20">
        <p class="text-[14px] text-[#F44336] font-medium">{{ reportStore.error }}</p>
        <button @click="reportStore.fetchReports()" class="mt-3 text-[13px] text-[#4CAF50] font-semibold">다시 시도</button>
      </div>

      <!-- Empty State -->
      <div v-else-if="!hasData" class="flex flex-col items-center justify-center py-20">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-[48px] h-[48px] text-[#E0E0E0] mb-[16px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
        </svg>
        <p class="text-[14px] text-[#888] font-medium">리포트 데이터가 없습니다</p>
        <p class="text-[12px] text-[#B0B0B0] mt-[4px]">학습을 시작하면 리포트가 생성됩니다</p>
      </div>

      <template v-else>
        <!-- 1. 기간 선택 Toggle Tabs -->
        <div class="bg-[#F8F8F8] rounded-[12px] p-[4px] flex">
          <button
            @click="selectedPeriod = 'weekly'"
            class="flex-1 py-[10px] rounded-[10px] text-[14px] font-semibold transition-all active:scale-[0.98]"
            :class="selectedPeriod === 'weekly' ? 'bg-white shadow-sm text-[#4CAF50]' : 'text-[#888]'"
          >
            주간
          </button>
          <button
            @click="selectedPeriod = 'monthly'"
            class="flex-1 py-[10px] rounded-[10px] text-[14px] font-semibold transition-all active:scale-[0.98]"
            :class="selectedPeriod === 'monthly' ? 'bg-white shadow-sm text-[#4CAF50]' : 'text-[#888]'"
          >
            월간
          </button>
        </div>

        <!-- 2. 종합 통계 -->
        <div class="bg-white rounded-[16px] p-[20px] shadow-[0_0_10px_rgba(0,0,0,0.1)]">
          <h2 class="text-[14px] font-semibold text-[#555] mb-[16px]">종합 통계</h2>
          <div class="grid grid-cols-3 gap-[12px]">
            <!-- 총 학습시간 -->
            <div class="text-center">
              <div class="w-[44px] h-[44px] rounded-full bg-[#E8F5E9] flex items-center justify-center mx-auto mb-[8px]">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-[22px] h-[22px] text-[#4CAF50]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <p class="text-[18px] font-bold text-[#333]">{{ overallStats.totalTime }}</p>
              <p class="text-[11px] text-[#888] mt-[2px]">총 학습시간</p>
            </div>
            <!-- 완료 콘텐츠 -->
            <div class="text-center">
              <div class="w-[44px] h-[44px] rounded-full bg-[#E3F2FD] flex items-center justify-center mx-auto mb-[8px]">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-[22px] h-[22px] text-[#2196F3]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <p class="text-[18px] font-bold text-[#333]">{{ overallStats.completedItems }}개</p>
              <p class="text-[11px] text-[#888] mt-[2px]">완료 콘텐츠</p>
            </div>
            <!-- 연속 출석 -->
            <div class="text-center">
              <div class="w-[44px] h-[44px] rounded-full bg-[#FFF3E0] flex items-center justify-center mx-auto mb-[8px]">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-[22px] h-[22px] text-[#FF9800]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15.362 5.214A8.252 8.252 0 0112 21 8.25 8.25 0 016.038 7.048 8.287 8.287 0 009 9.6a8.983 8.983 0 013.361-6.867 8.21 8.21 0 003 2.48z" />
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 18a3.75 3.75 0 00.495-7.467 5.99 5.99 0 00-1.925 3.546 5.974 5.974 0 01-2.133-1.001A3.75 3.75 0 0012 18z" />
                </svg>
              </div>
              <p class="text-[18px] font-bold text-[#333]">{{ overallStats.streakDays }}{{ typeof overallStats.streakDays === 'number' ? '일' : '' }}</p>
              <p class="text-[11px] text-[#888] mt-[2px]">연속 출석</p>
            </div>
          </div>
        </div>

        <!-- 3. 영역별 진행률 -->
        <div v-if="categories.length > 0" class="bg-white rounded-[16px] p-[20px] shadow-[0_0_10px_rgba(0,0,0,0.1)]">
          <h2 class="text-[14px] font-semibold text-[#555] mb-[16px]">영역별 진행률</h2>
          <div class="space-y-[20px]">
            <div v-for="cat in categories" :key="cat.id">
              <!-- Row: Icon + Name + Score% + Trend -->
              <div class="flex items-center justify-between mb-[8px]">
                <div class="flex items-center gap-[10px]">
                  <div class="w-[32px] h-[32px] rounded-[8px] flex items-center justify-center" :class="cat.iconBg">
                    <!-- Language -->
                    <svg v-if="cat.name === '언어'" xmlns="http://www.w3.org/2000/svg" class="w-[16px] h-[16px]" :class="cat.iconColor" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 21l5.25-11.25L21 21m-9-3h7.5M3 5.621a48.474 48.474 0 016-.371m0 0c1.12 0 2.233.038 3.334.114M9 5.25V3m3.334 2.364C11.176 10.658 7.69 15.08 3 17.502m9.334-12.138c.896.061 1.785.147 2.666.257m-4.589 8.495a18.023 18.023 0 01-3.827-5.802" />
                    </svg>
                    <!-- Brain -->
                    <svg v-else-if="cat.name === '인지'" xmlns="http://www.w3.org/2000/svg" class="w-[16px] h-[16px]" :class="cat.iconColor" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 00-2.455 2.456z" />
                    </svg>
                    <!-- Heart -->
                    <svg v-else-if="cat.name === '정서'" xmlns="http://www.w3.org/2000/svg" class="w-[16px] h-[16px]" :class="cat.iconColor" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                    </svg>
                    <!-- People -->
                    <svg v-else xmlns="http://www.w3.org/2000/svg" class="w-[16px] h-[16px]" :class="cat.iconColor" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                    </svg>
                  </div>
                  <span class="text-[14px] font-semibold text-[#333]">{{ cat.name }}</span>
                </div>
                <div class="flex items-center gap-[8px]">
                  <span class="text-[14px] font-bold" :class="cat.iconColor">{{ cat.score }}%</span>
                  <!-- Trend Arrow -->
                  <div v-if="cat.trend === 'up'" class="flex items-center gap-[2px]">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-[14px] h-[14px] text-[#4CAF50]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5l15-15m0 0H8.25m11.25 0v11.25" />
                    </svg>
                    <span class="text-[11px] font-semibold text-[#4CAF50]">+{{ cat.trendValue }}</span>
                  </div>
                  <div v-else-if="cat.trend === 'down'" class="flex items-center gap-[2px]">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-[14px] h-[14px] text-[#F44336]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 4.5l15 15m0 0V8.25m0 11.25H8.25" />
                    </svg>
                    <span class="text-[11px] font-semibold text-[#F44336]">-{{ cat.trendValue }}</span>
                  </div>
                  <div v-else class="flex items-center gap-[2px]">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-[14px] h-[14px] text-[#999]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M18 12H6" />
                    </svg>
                    <span class="text-[11px] font-semibold text-[#999]">0</span>
                  </div>
                </div>
              </div>
              <!-- Progress Bar -->
              <div class="w-full h-[8px] bg-[#F0F0F0] rounded-full overflow-hidden">
                <div
                  class="h-full rounded-full transition-all duration-500"
                  :class="cat.barColor"
                  :style="{ width: cat.score + '%' }"
                ></div>
              </div>
            </div>
          </div>
        </div>

        <!-- 4. 주간 활동 Bar Chart -->
        <div class="bg-white rounded-[16px] p-[20px] shadow-[0_0_10px_rgba(0,0,0,0.1)]">
          <h2 class="text-[14px] font-semibold text-[#555] mb-[16px]">주간 활동</h2>
          <div class="flex items-end justify-between gap-[8px] h-[140px] px-[4px]">
            <div
              v-for="(day, index) in weeklyActivity"
              :key="day.day"
              class="flex-1 flex flex-col items-center gap-[8px]"
            >
              <!-- Value label -->
              <span
                class="text-[10px] font-semibold"
                :class="isMaxDay(index) || index === todayIndex ? 'text-[#4CAF50]' : 'text-[#B0B0B0]'"
              >
                {{ day.value }}분
              </span>
              <!-- Bar container -->
              <div class="w-full flex justify-center" style="height: 100px;">
                <div class="relative w-full max-w-[28px] flex items-end h-full">
                  <div
                    class="w-full rounded-t-[4px] transition-all duration-500"
                    :class="isMaxDay(index) || index === todayIndex ? 'bg-[#4CAF50]' : 'bg-[#4CAF50]/40'"
                    :style="{ height: getBarHeight(day.value) }"
                  ></div>
                </div>
              </div>
              <!-- Day label -->
              <span
                class="text-[11px] font-medium"
                :class="isMaxDay(index) || index === todayIndex ? 'text-[#4CAF50] font-bold' : 'text-[#888]'"
              >
                {{ day.day }}
              </span>
            </div>
          </div>
        </div>

        <!-- 5. 추천 학습 -->
        <div v-if="recommendations.length > 0">
          <div class="flex items-center justify-between px-[4px] mb-[12px]">
            <div class="flex items-center gap-[8px]">
              <div class="w-[28px] h-[28px] rounded-[8px] bg-[#FFF3E0] flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-[16px] h-[16px] text-[#FF9800]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 001.5-.189m-1.5.189a6.01 6.01 0 01-1.5-.189m3.75 7.478a12.06 12.06 0 01-4.5 0m3.75 2.383a14.406 14.406 0 01-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 10-7.517 0c.85.493 1.509 1.333 1.509 2.316V18" />
                </svg>
              </div>
              <h2 class="text-[16px] font-bold text-[#333]">다음 주 추천 학습</h2>
            </div>
          </div>

          <div class="space-y-[8px]">
            <div
              v-for="rec in recommendations"
              :key="rec.id"
              class="bg-white rounded-[16px] p-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] flex items-center gap-[12px] active:scale-[0.98] transition-all cursor-pointer"
            >
              <!-- Category icon -->
              <div class="w-[40px] h-[40px] rounded-[10px] flex items-center justify-center shrink-0" :class="rec.badgeBg">
                <!-- Language -->
                <svg v-if="rec.category === '언어'" xmlns="http://www.w3.org/2000/svg" class="w-[20px] h-[20px]" :class="rec.badgeText" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 21l5.25-11.25L21 21m-9-3h7.5M3 5.621a48.474 48.474 0 016-.371m0 0c1.12 0 2.233.038 3.334.114M9 5.25V3m3.334 2.364C11.176 10.658 7.69 15.08 3 17.502m9.334-12.138c.896.061 1.785.147 2.666.257m-4.589 8.495a18.023 18.023 0 01-3.827-5.802" />
                </svg>
                <!-- Emotional -->
                <svg v-else-if="rec.category === '정서'" xmlns="http://www.w3.org/2000/svg" class="w-[20px] h-[20px]" :class="rec.badgeText" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                </svg>
                <!-- Social -->
                <svg v-else-if="rec.category === '사회성'" xmlns="http://www.w3.org/2000/svg" class="w-[20px] h-[20px]" :class="rec.badgeText" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                </svg>
                <!-- Default / Cognitive -->
                <svg v-else xmlns="http://www.w3.org/2000/svg" class="w-[20px] h-[20px]" :class="rec.badgeText" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
                </svg>
              </div>
              <!-- Content -->
              <div class="flex-1 min-w-0">
                <h3 class="text-[14px] font-semibold text-[#333] truncate">{{ rec.title }}</h3>
                <div class="flex items-center gap-[8px] mt-[4px]">
                  <span
                    class="text-[11px] font-semibold rounded-full px-[8px] py-[2px]"
                    :class="[rec.badgeBg, rec.badgeText]"
                  >
                    {{ rec.category }}
                  </span>
                  <span class="text-[11px] text-[#999] bg-[#F8F8F8] rounded-full px-[8px] py-[2px]">{{ rec.duration }}</span>
                </div>
              </div>
              <!-- Chevron -->
              <svg xmlns="http://www.w3.org/2000/svg" class="w-[16px] h-[16px] text-[#B0B0B0] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
              </svg>
            </div>
          </div>
        </div>
      </template>
    </main>
  </div>
</template>
