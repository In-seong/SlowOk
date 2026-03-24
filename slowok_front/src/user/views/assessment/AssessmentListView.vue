<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAssessmentStore } from '../../stores/assessmentStore'
import type { Assessment } from '@shared/types'
import BottomNav from '@shared/components/layout/BottomNav.vue'

const router = useRouter()
const assessmentStore = useAssessmentStore()

const pageLoading = ref(true)

onMounted(async () => {
  try {
    await assessmentStore.fetchAssessments()
  } finally {
    pageLoading.value = false
  }
})

const assessments = computed(() => assessmentStore.assessments)

const averageScore = computed(() => {
  if (assessments.value.length === 0) return 0
  return Math.round(assessments.value.reduce((sum, a) => sum + a.score, 0) / assessments.value.length)
})

// Calculate the overall trend by comparing with earlier assessments of same categories
const overallTrendLabel = computed(() => {
  if (assessments.value.length < 2) return ''
  const sorted = [...assessments.value].sort((a, b) =>
    (a.created_at || '').localeCompare(b.created_at || '')
  )
  const half = Math.floor(sorted.length / 2)
  const olderAvg = sorted.slice(0, half).reduce((s, a) => s + a.score, 0) / half
  const newerAvg = sorted.slice(half).reduce((s, a) => s + a.score, 0) / (sorted.length - half)
  const diff = Math.round(newerAvg - olderAvg)
  if (diff > 0) return `+${diff}점`
  if (diff < 0) return `${diff}점`
  return '변동없음'
})

function getCategoryName(assessment: Assessment): string {
  return assessment.category?.name || '기타'
}

function getAssessmentType(assessment: Assessment): string {
  return assessment.type || '평가'
}

function getAssessmentDate(assessment: Assessment): string {
  if (!assessment.created_at) return ''
  return assessment.created_at.substring(0, 10)
}

function getFeedbackText(assessment: Assessment): string {
  if (!assessment.feedback) return '피드백이 아직 없습니다.'
  if (typeof assessment.feedback === 'string') return assessment.feedback
  if (assessment.feedback.text) return assessment.feedback.text
  if (assessment.feedback.message) return assessment.feedback.message
  return JSON.stringify(assessment.feedback)
}

// Calculate trend by comparing with previous assessment of same category
function getTrendData(assessment: Assessment) {
  const sameCategoryAssessments = assessments.value
    .filter(a => a.category_id === assessment.category_id && a.assessment_id !== assessment.assessment_id)
    .sort((a, b) => (b.created_at || '').localeCompare(a.created_at || ''))

  const previous = sameCategoryAssessments[0]
  if (!previous) {
    return { icon: '\u2192', color: 'text-[#999]', bg: 'bg-[#F5F5F5]', label: '0' }
  }

  const diff = assessment.score - previous.score
  if (diff > 0) return { icon: '\u2191', color: 'text-[#4CAF50]', bg: 'bg-[#E8F5E9]', label: `+${diff}` }
  if (diff < 0) return { icon: '\u2193', color: 'text-[#F44336]', bg: 'bg-[#FFEBEE]', label: `${diff}` }
  return { icon: '\u2192', color: 'text-[#999]', bg: 'bg-[#F5F5F5]', label: '0' }
}

function getCategoryBadge(category: string) {
  switch (category) {
    case '언어': return { bg: 'bg-[#E3F2FD]', text: 'text-[#2196F3]' }
    case '인지': return { bg: 'bg-[#F3E5F5]', text: 'text-[#9C27B0]' }
    case '정서': return { bg: 'bg-[#FCE4EC]', text: 'text-[#E91E63]' }
    case '사회성': return { bg: 'bg-[#FFF8E1]', text: 'text-[#FF8F00]' }
    default: return { bg: 'bg-[#F5F5F5]', text: 'text-[#888]' }
  }
}

function getScoreVariant(score: number): string {
  if (score >= 80) return 'bg-[#4CAF50]'
  if (score >= 60) return 'bg-[#FF9800]'
  return 'bg-[#F44336]'
}
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
        <h1 class="text-[18px] font-bold text-[#333]">평가 결과</h1>
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
      <div v-else-if="assessmentStore.error" class="flex flex-col items-center justify-center py-20">
        <p class="text-[14px] text-[#F44336] font-medium">{{ assessmentStore.error }}</p>
        <button @click="assessmentStore.fetchAssessments()" class="mt-3 text-[13px] text-[#4CAF50] font-semibold">다시 시도</button>
      </div>

      <!-- Empty State -->
      <div v-else-if="assessments.length === 0" class="flex flex-col items-center justify-center py-20">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-[48px] h-[48px] text-[#E0E0E0] mb-[16px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
        </svg>
        <p class="text-[14px] text-[#888] font-medium">평가 결과가 없습니다</p>
        <p class="text-[12px] text-[#B0B0B0] mt-[4px]">평가가 완료되면 여기에 표시됩니다</p>
      </div>

      <template v-else>
        <!-- 종합 평가 카드 -->
        <div class="bg-gradient-to-br from-[#4CAF50] to-[#388E3C] rounded-[16px] p-[20px] text-white shadow-[0_0_10px_rgba(0,0,0,0.1)] relative overflow-hidden">
          <!-- Decorative circles -->
          <div class="absolute -right-[24px] -top-[24px] w-[112px] h-[112px] bg-white/10 rounded-full"></div>
          <div class="absolute right-[32px] -bottom-[16px] w-[80px] h-[80px] bg-white/5 rounded-full"></div>

          <div class="relative z-10">
            <div class="flex items-center gap-[8px] mb-[12px]">
              <div class="w-[32px] h-[32px] rounded-[10px] bg-white/20 flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-[16px] h-[16px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
              </div>
              <span class="text-[14px] font-semibold text-white/90">종합 평가</span>
            </div>
            <div class="flex items-end gap-[4px] mb-[8px]">
              <span class="text-[32px] font-bold leading-none">{{ averageScore }}</span>
              <span class="text-[16px] text-white/80 pb-[2px]">/100</span>
            </div>
            <p v-if="overallTrendLabel" class="text-[13px] opacity-80">이전 대비 <span class="font-semibold text-white">{{ overallTrendLabel }}</span> 향상</p>
          </div>
        </div>

        <!-- 평가 항목 목록 -->
        <div class="space-y-[12px]">
          <div
            v-for="assessment in assessments"
            :key="assessment.assessment_id"
            class="bg-white rounded-[16px] p-[20px] shadow-[0_0_10px_rgba(0,0,0,0.1)]"
          >
            <!-- Top row: Category badge + Type badge + Date -->
            <div class="flex items-center justify-between mb-[12px]">
              <div class="flex items-center gap-[8px]">
                <span
                  :class="[getCategoryBadge(getCategoryName(assessment)).bg, getCategoryBadge(getCategoryName(assessment)).text]"
                  class="inline-block rounded-full px-[10px] py-[4px] text-[12px] font-semibold"
                >
                  {{ getCategoryName(assessment) }}
                </span>
                <span class="text-[12px] text-[#888] bg-[#F8F8F8] rounded-full px-[8px] py-[4px]">
                  {{ getAssessmentType(assessment) }}
                </span>
              </div>
              <span class="text-[12px] text-[#999]">{{ getAssessmentDate(assessment) }}</span>
            </div>

            <!-- Score + Trend -->
            <div class="flex items-center justify-between mb-[12px]">
              <div class="flex items-end gap-[4px]">
                <span class="text-[24px] font-bold text-[#333]">{{ assessment.score }}</span>
                <span class="text-[14px] text-[#999] pb-[2px]">/100</span>
              </div>
              <!-- Trend badge -->
              <span
                :class="[getTrendData(assessment).color, getTrendData(assessment).bg]"
                class="inline-flex items-center gap-[4px] text-[12px] font-semibold rounded-full px-[10px] py-[4px]"
              >
                {{ getTrendData(assessment).icon }} {{ getTrendData(assessment).label }}
              </span>
            </div>

            <!-- Progress Bar -->
            <div class="w-full bg-[#F0F0F0] rounded-full h-[8px] mb-[16px] overflow-hidden">
              <div
                :class="getScoreVariant(assessment.score)"
                class="h-full rounded-full transition-all duration-500"
                :style="{ width: assessment.score + '%' }"
              ></div>
            </div>

            <!-- Feedback -->
            <div class="bg-[#F8F8F8] rounded-[10px] p-[12px] flex items-start gap-[10px]">
              <div class="w-[24px] h-[24px] rounded-[8px] bg-[#E8F5E9] flex items-center justify-center shrink-0 mt-[2px]">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-[14px] h-[14px] text-[#4CAF50]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                </svg>
              </div>
              <p class="text-[13px] text-[#555] italic leading-relaxed">{{ getFeedbackText(assessment) }}</p>
            </div>
          </div>
        </div>
      </template>
    </main>
    <BottomNav />
  </div>
</template>
