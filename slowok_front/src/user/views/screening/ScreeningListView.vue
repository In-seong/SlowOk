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
      <!-- 면책 안내 -->
      <div class="bg-[#FFF8E1] rounded-[12px] p-3">
        <p class="text-[11px] text-[#F57F17] leading-relaxed">
          본 검사는 의료 진단이 아닌 <strong>교육 목적의 발달 선별 도구</strong>입니다.
          정확한 진단은 전문 의료기관을 방문해주세요.
        </p>
        <div class="mt-1.5 space-y-0.5">
          <p class="text-[10px] text-[#888]">참고문헌:</p>
          <a href="https://psycnet.apa.org/record/1990-98005-000" target="_blank" rel="noopener" class="block text-[10px] text-[#2196F3] underline">• Gresham & Elliott (1990). Social Skills Rating System (SSRS)</a>
          <a href="https://doi.org/10.1007/978-1-4419-1698-3" target="_blank" rel="noopener" class="block text-[10px] text-[#2196F3] underline">• Elliott & Gresham (2008). Social Skills Improvement System (SSIS)</a>
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
