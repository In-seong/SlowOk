<script setup lang="ts">
import { onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import BackHeader from '@shared/components/layout/BackHeader.vue'
import BottomNav from '@shared/components/layout/BottomNav.vue'
import CardSection from '@shared/components/ui/CardSection.vue'
import StatusBadge from '@shared/components/ui/StatusBadge.vue'
import ActionButton from '@shared/components/ui/ActionButton.vue'
import { useScreeningStore } from '../../stores/screeningStore'
import type { ScreeningTest, ScreeningResult } from '@shared/types'

const router = useRouter()
const screeningStore = useScreeningStore()

onMounted(async () => {
  await Promise.all([
    screeningStore.fetchTests(),
    screeningStore.fetchResults(),
  ])
})

function getResultForTest(testId: number): ScreeningResult | undefined {
  return screeningStore.results.find(r => r.test_id === testId)
}

function isCompleted(test: ScreeningTest): boolean {
  return !!getResultForTest(test.test_id)
}

function getScore(test: ScreeningTest): number | null {
  const result = getResultForTest(test.test_id)
  return result ? result.score : null
}

function getCategoryName(test: ScreeningTest): string {
  return test.category?.name || '기타'
}

function getCategoryVariant(category: string): 'info' | 'primary' | 'warning' | 'danger' {
  const map: Record<string, 'info' | 'primary' | 'warning' | 'danger'> = {
    '언어': 'info',
    '인지': 'primary',
    '정서': 'warning',
    '사회성': 'danger',
  }
  return map[category] || 'info'
}

function startTest(testId: number): void {
  router.push({ name: 'screening-test', params: { id: testId } })
}

function viewResult(testId: number): void {
  const result = getResultForTest(testId)
  if (result) {
    router.push({ name: 'screening-result-detail', params: { resultId: result.result_id } })
  }
}

const isEmpty = computed(() => !screeningStore.loading && screeningStore.tests.length === 0)
</script>

<template>
  <div class="min-h-screen bg-[#F5F5F5] max-w-[402px] mx-auto">
    <BackHeader title="진단 검사" />

    <main class="px-5 pb-[80px] pt-4 space-y-3">
      <!-- Info Banner -->
      <div class="bg-[#E3F2FD] rounded-[12px] p-4">
        <div class="flex items-start gap-3">
          <div class="w-9 h-9 rounded-[10px] bg-[#2196F3]/10 flex items-center justify-center shrink-0 mt-0.5">
            <svg class="w-5 h-5 text-[#2196F3]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
            </svg>
          </div>
          <div>
            <p class="text-[14px] font-semibold text-[#2196F3]">맞춤 학습의 시작!</p>
            <p class="text-[13px] text-[#555] mt-0.5 leading-relaxed">나의 학습 수준을 알아보고 맞춤 학습을 시작해요!</p>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="screeningStore.loading" class="flex items-center justify-center py-20">
        <p class="text-[14px] text-[#999]">불러오는 중...</p>
      </div>

      <!-- Error State -->
      <div v-else-if="screeningStore.error" class="flex items-center justify-center py-20">
        <p class="text-[14px] text-[#F44336]">{{ screeningStore.error }}</p>
      </div>

      <!-- Empty State -->
      <div v-else-if="isEmpty" class="flex items-center justify-center py-20">
        <p class="text-[14px] text-[#999]">진단 검사가 없습니다</p>
      </div>

      <!-- Test List -->
      <template v-else>
        <CardSection v-for="test in screeningStore.tests" :key="test.test_id">
          <!-- Top: Category + Completed Badge -->
          <div class="flex items-center gap-2 mb-3">
            <StatusBadge :label="getCategoryName(test)" :variant="getCategoryVariant(getCategoryName(test))" />
            <StatusBadge v-if="isCompleted(test)" label="완료" variant="success" />
          </div>

          <!-- Title -->
          <h3 class="text-[15px] font-semibold text-[#333]">{{ test.title }}</h3>

          <!-- Description -->
          <p class="text-[13px] text-[#888] mt-1 leading-relaxed">{{ test.description }}</p>

          <!-- Meta: Question Count + Time -->
          <div class="flex items-center gap-3 mt-3 mb-4">
            <span class="flex items-center gap-1 text-[12px] text-[#999]">
              <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
              {{ test.question_count }}문항
            </span>
            <span class="flex items-center gap-1 text-[12px] text-[#999]">
              <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              {{ test.time_limit }}분
            </span>
          </div>

          <!-- Bottom: Score + Action -->
          <div class="flex items-center justify-between">
            <div v-if="isCompleted(test)" class="flex items-center gap-2">
              <span class="text-[15px] font-bold text-[#4CAF50]">{{ getScore(test) }}점</span>
            </div>
            <div v-else />

            <div class="flex items-center gap-2">
              <ActionButton
                v-if="isCompleted(test)"
                variant="outline"
                @click="viewResult(test.test_id)"
              >
                결과 보기
              </ActionButton>
              <ActionButton
                :variant="isCompleted(test) ? 'outline' : 'primary'"
                @click="startTest(test.test_id)"
              >
                {{ isCompleted(test) ? '다시하기' : '시작하기' }}
              </ActionButton>
            </div>
          </div>
        </CardSection>
      </template>
    </main>

    <BottomNav />
  </div>
</template>
