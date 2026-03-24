<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import type { LearningContent } from '@shared/types'
import { useLearningStore } from '../../stores/learningStore'
import BackHeader from '@shared/components/layout/BackHeader.vue'
import BottomNav from '@shared/components/layout/BottomNav.vue'
import CardSection from '@shared/components/ui/CardSection.vue'
import StatusBadge from '@shared/components/ui/StatusBadge.vue'
import ProgressBar from '@shared/components/ui/ProgressBar.vue'

const router = useRouter()
const learningStore = useLearningStore()

const activeCategory = ref<string>('전체')

// Extract unique categories from contents
const categories = computed<string[]>(() => {
  const names = learningStore.contents
    .map(c => c.category?.name)
    .filter((name): name is string => !!name)
  return ['전체', ...Array.from(new Set(names))]
})

// Filter contents by selected category
const filteredContents = computed<LearningContent[]>(() =>
  activeCategory.value === '전체'
    ? learningStore.contents
    : learningStore.contents.filter(c => c.category?.name === activeCategory.value)
)

// Today's progress: ratio of COMPLETED among all contents
const todayProgress = computed(() => {
  const total = learningStore.contents.length
  if (total === 0) return { total: 0, completed: 0, percent: 0 }
  const completed = learningStore.contents.filter(
    c => c.progress?.status === 'COMPLETED'
  ).length
  return { total, completed, percent: Math.round((completed / total) * 100) }
})

// Get display progress value (0-100) from LearningProgress
function getProgressValue(content: LearningContent): number {
  if (!content.progress) return 0
  if (content.progress.status === 'COMPLETED') return 100
  if (content.progress.status === 'IN_PROGRESS') return 50
  return 0
}

function getTypeBadge(type: string): { label: string; variant: 'info' | 'warning' | 'success' | 'primary' | 'default' } {
  switch (type) {
    case 'VIDEO': return { label: '영상', variant: 'info' }
    case 'QUIZ': return { label: '퀴즈', variant: 'warning' }
    case 'GAME': return { label: '게임', variant: 'success' }
    case 'READING': return { label: '읽기', variant: 'primary' }
    default: return { label: type, variant: 'default' }
  }
}

function getCategoryVariant(categoryName: string): 'info' | 'primary' | 'danger' | 'warning' | 'default' {
  switch (categoryName) {
    case '언어': return 'info'
    case '인지': return 'primary'
    case '정서': return 'danger'
    case '사회성': return 'warning'
    default: return 'default'
  }
}

function getButtonLabel(content: LearningContent): string {
  if (content.progress?.status === 'COMPLETED') return '복습하기'
  if (content.progress?.status === 'IN_PROGRESS') return '이어하기'
  return '시작하기'
}

// When category filter changes, re-fetch with categoryId
watch(activeCategory, (newVal) => {
  if (newVal === '전체') {
    learningStore.fetchContents()
  } else {
    // Find category_id by matching name from existing contents
    const matchedContent = learningStore.contents.find(c => c.category?.name === newVal)
    if (matchedContent) {
      learningStore.fetchContents(matchedContent.category_id)
    }
  }
})

onMounted(() => {
  learningStore.fetchContents()
})
</script>

<template>
  <div class="min-h-screen bg-[#F8F8F8] max-w-[402px] mx-auto">
    <BackHeader title="학습하기" />

    <main class="px-5 pb-[80px] pt-4">
      <!-- Loading state -->
      <div v-if="learningStore.loading && learningStore.contents.length === 0" class="text-center py-16">
        <div class="w-[40px] h-[40px] border-4 border-[#E0E0E0] border-t-[#4CAF50] rounded-full animate-spin mx-auto mb-3" />
        <p class="text-[13px] text-[#999]">학습 콘텐츠를 불러오는 중...</p>
      </div>

      <!-- Error state -->
      <div v-else-if="learningStore.error && learningStore.contents.length === 0" class="text-center py-16">
        <div class="w-[56px] h-[56px] bg-[#FFF3E0] rounded-full flex items-center justify-center mx-auto mb-3">
          <svg class="w-[28px] h-[28px] text-[#FF9800]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
        </div>
        <p class="text-[13px] text-[#999] mb-3">{{ learningStore.error }}</p>
        <button
          @click="learningStore.fetchContents()"
          class="text-[13px] text-[#4CAF50] font-semibold"
        >
          다시 시도
        </button>
      </div>

      <!-- Main content -->
      <template v-else>
        <!-- Category filter tabs -->
        <div class="overflow-x-auto flex gap-2 pb-3 scrollbar-hide">
          <button
            v-for="cat in categories"
            :key="cat"
            @click="activeCategory = cat"
            :class="activeCategory === cat
              ? 'bg-[#4CAF50] text-white'
              : 'bg-[#F8F8F8] text-[#888]'"
            class="rounded-full px-4 py-2 text-[13px] font-medium whitespace-nowrap shrink-0 transition-colors active:scale-[0.98]"
          >
            {{ cat }}
          </button>
        </div>

        <!-- Today progress summary -->
        <div class="bg-[#4CAF50] rounded-[16px] p-4 text-white mt-1">
          <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
              <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
              </svg>
              <span class="text-[14px] font-bold">오늘의 학습</span>
            </div>
            <span class="text-[13px] font-semibold text-white/80">{{ todayProgress.completed }}/{{ todayProgress.total }} 완료</span>
          </div>
          <div class="w-full h-[8px] rounded-full bg-white/20 overflow-hidden">
            <div
              class="h-full rounded-full bg-white transition-all duration-500"
              :style="{ width: todayProgress.percent + '%' }"
            />
          </div>
        </div>

        <!-- Content count -->
        <p class="text-[12px] text-[#999] mt-4 mb-2">{{ filteredContents.length }}개의 학습 콘텐츠</p>

        <!-- Content list -->
        <div class="space-y-3 mt-1">
          <CardSection
            v-for="content in filteredContents"
            :key="content.content_id"
          >
            <div
              class="cursor-pointer active:scale-[0.98] transition-transform"
              @click="router.push({ name: 'learning-content', params: { id: content.content_id } })"
            >
              <!-- Top badges -->
              <div class="flex items-center gap-2 mb-2">
                <StatusBadge :label="content.category?.name ?? '기타'" :variant="getCategoryVariant(content.category?.name ?? '')" />
                <StatusBadge :label="getTypeBadge(content.content_type).label" :variant="getTypeBadge(content.content_type).variant" />
              </div>

              <!-- Title -->
              <h3 class="text-[15px] font-semibold text-[#333] leading-snug">{{ content.title }}</h3>

              <!-- Description from content_data or fallback -->
              <p class="text-[13px] text-[#888] mt-1 truncate">
                {{ (content.content_data as any)?.description ?? content.title }}
              </p>

              <!-- Bottom row -->
              <div class="flex items-center justify-between mt-3 pt-3 border-t border-[#F0F0F0]">
                <!-- Difficulty dots -->
                <div class="flex items-center gap-1.5">
                  <span class="text-[11px] text-[#999] mr-1">난이도</span>
                  <template v-for="i in 3" :key="i">
                    <span
                      class="w-[6px] h-[6px] rounded-full inline-block"
                      :class="i <= content.difficulty_level ? 'bg-[#FF9800]' : 'bg-[#E0E0E0]'"
                    />
                  </template>
                </div>

                <!-- Progress or button -->
                <div class="flex items-center gap-3">
                  <div v-if="content.progress?.status === 'IN_PROGRESS'" class="w-[60px]">
                    <ProgressBar :value="getProgressValue(content)" variant="primary" />
                  </div>
                  <span
                    v-if="content.progress?.status === 'COMPLETED'"
                    class="text-[12px] font-semibold text-[#4CAF50] flex items-center gap-1"
                  >
                    <svg class="w-[14px] h-[14px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M5 13l4 4L19 7" />
                    </svg>
                    완료
                  </span>
                  <button
                    class="rounded-[12px] px-3.5 py-2 text-[12px] font-semibold transition-all active:scale-[0.98]"
                    :class="content.progress?.status === 'COMPLETED'
                      ? 'bg-[#F8F8F8] text-[#555]'
                      : 'bg-[#4CAF50] text-white'"
                    @click.stop="router.push({ name: 'learning-content', params: { id: content.content_id } })"
                  >
                    {{ getButtonLabel(content) }}
                  </button>
                </div>
              </div>
            </div>
          </CardSection>
        </div>

        <!-- Empty state -->
        <div v-if="filteredContents.length === 0 && !learningStore.loading" class="text-center py-16">
          <div class="w-[56px] h-[56px] bg-[#F0F0F0] rounded-full flex items-center justify-center mx-auto mb-3">
            <svg class="w-[28px] h-[28px] text-[#B0B0B0]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
            </svg>
          </div>
          <p class="text-[13px] text-[#999]">해당 카테고리에 콘텐츠가 없습니다</p>
        </div>
      </template>
    </main>

    <BottomNav />
  </div>
</template>
