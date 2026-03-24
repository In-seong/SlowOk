<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useLearningStore } from '@user/stores/learningStore'
import BackHeader from '@shared/components/layout/BackHeader.vue'
import type { LearningContent } from '@shared/types'

const route = useRoute()
const router = useRouter()
const learningStore = useLearningStore()

const contentId = Number(route.params.id)
const initialLoading = ref(true)

// --- Content ---
const content = computed<LearningContent | null>(() =>
  learningStore.contents.find(c => c.content_id === contentId) ?? null
)

const contentType = computed(() => content.value?.content_type ?? null)

const parsedData = computed<Record<string, any> | null>(() => {
  if (!content.value?.content_data) return null
  if (typeof content.value.content_data === 'string') {
    try { return JSON.parse(content.value.content_data) } catch { return null }
  }
  return content.value.content_data
})

// =====================
// VIDEO
// =====================
function extractYoutubeId(url: string): string | null {
  const patterns = [
    /youtu\.be\/([^?&]+)/,
    /youtube\.com\/watch\?v=([^&]+)/,
    /youtube\.com\/embed\/([^?&]+)/,
    /youtube\.com\/shorts\/([^?&]+)/,
  ]
  for (const p of patterns) {
    const m = url.match(p)
    if (m?.[1]) return m[1]
  }
  return null
}

const videoData = computed(() => {
  if (contentType.value !== 'VIDEO' || !parsedData.value) return null
  const d = parsedData.value
  return {
    videoType: (d.video_type as string) ?? 'upload',
    videoUrl: (d.video_url as string) ?? '',
    thumbnailUrl: (d.thumbnail_url as string | undefined) ?? undefined,
    duration: (d.duration as string | undefined) ?? undefined,
    description: (d.description as string | undefined) ?? undefined,
  }
})

const youtubeId = computed(() => {
  if (!videoData.value || videoData.value.videoType !== 'youtube') return null
  return extractYoutubeId(videoData.value.videoUrl)
})

// =====================
// QUIZ
// =====================
interface QuizQuestion {
  question: string
  image_url?: string
  options: string[]
  correct_index: number
}

const quizData = computed(() => {
  if (contentType.value !== 'QUIZ' || !parsedData.value) return null
  const d = parsedData.value
  return {
    description: (d.description as string | undefined) ?? undefined,
    timeLimit: (d.time_limit as number | undefined) ?? undefined,
    passScore: (d.pass_score as number | undefined) ?? 70,
    questions: (d.questions as QuizQuestion[] | undefined) ?? [],
  }
})

const currentQuestionIndex = ref(0)
const selectedAnswers = ref<Record<number, number>>({})
const answeredQuestions = ref<Record<number, boolean>>({})
const showResult = ref(false)
const quizScore = ref(0)
const quizTimerSeconds = ref(0)
const quizTimerInterval = ref<ReturnType<typeof setInterval> | null>(null)

const currentQuizQuestion = computed<QuizQuestion | null>(() => {
  if (!quizData.value) return null
  return quizData.value.questions[currentQuestionIndex.value] ?? null
})

const quizTotalQuestions = computed(() => quizData.value?.questions.length ?? 0)

const quizFormattedTime = computed(() => {
  const min = Math.floor(quizTimerSeconds.value / 60)
  const sec = quizTimerSeconds.value % 60
  return `${min}:${sec.toString().padStart(2, '0')}`
})

function startQuizTimer(): void {
  if (!quizData.value?.timeLimit) return
  quizTimerSeconds.value = quizData.value.timeLimit
  quizTimerInterval.value = setInterval(() => {
    if (quizTimerSeconds.value > 0) {
      quizTimerSeconds.value--
    } else {
      finishQuiz()
    }
  }, 1000)
}

function stopQuizTimer(): void {
  if (quizTimerInterval.value) {
    clearInterval(quizTimerInterval.value)
    quizTimerInterval.value = null
  }
}

function selectQuizAnswer(optionIndex: number): void {
  if (answeredQuestions.value[currentQuestionIndex.value]) return
  selectedAnswers.value[currentQuestionIndex.value] = optionIndex
  answeredQuestions.value[currentQuestionIndex.value] = true
}

function nextQuizQuestion(): void {
  if (currentQuestionIndex.value < quizTotalQuestions.value - 1) {
    currentQuestionIndex.value++
  } else {
    finishQuiz()
  }
}

function finishQuiz(): void {
  stopQuizTimer()
  let correct = 0
  const questions = quizData.value?.questions ?? []
  for (let i = 0; i < questions.length; i++) {
    const q = questions[i]
    if (q && selectedAnswers.value[i] === q.correct_index) {
      correct++
    }
  }
  quizScore.value = correct
  showResult.value = true
}

function retryQuiz(): void {
  currentQuestionIndex.value = 0
  selectedAnswers.value = {}
  answeredQuestions.value = {}
  showResult.value = false
  quizScore.value = 0
  if (quizData.value?.timeLimit) {
    startQuizTimer()
  }
}

const quizPassScore = computed(() => {
  if (!quizData.value) return 0
  return Math.ceil(quizTotalQuestions.value * (quizData.value.passScore / 100))
})

const quizPassed = computed(() => quizScore.value >= quizPassScore.value)

// =====================
// READING
// =====================
const readingData = computed(() => {
  if (contentType.value !== 'READING' || !parsedData.value) return null
  const d = parsedData.value
  return {
    textContent: (d.text_content as string) ?? '',
    audioUrl: (d.audio_url as string | undefined) ?? undefined,
    images: (d.images as string[] | undefined) ?? undefined,
    estimatedTime: (d.estimated_time as string | undefined) ?? undefined,
  }
})

// =====================
// GAME
// =====================
interface GameItem {
  question: string
  answer: string
  image_url?: string
}

const gameData = computed(() => {
  if (contentType.value !== 'GAME' || !parsedData.value) return null
  const d = parsedData.value
  return {
    gameType: (d.game_type as string) ?? 'matching',
    description: (d.description as string | undefined) ?? undefined,
    timeLimit: (d.time_limit as number | undefined) ?? undefined,
    items: (d.items as GameItem[] | undefined) ?? [],
  }
})

const currentGameIndex = ref(0)
const gameAnswers = ref<Record<number, string>>({})
const gameAnswered = ref<Record<number, boolean>>({})
const showGameResult = ref(false)
const gameScore = ref(0)

const gameTotalItems = computed(() => gameData.value?.items.length ?? 0)

const currentGameItem = computed<GameItem | null>(() => {
  if (!gameData.value) return null
  return gameData.value.items[currentGameIndex.value] ?? null
})

function selectGameAnswer(answer: string): void {
  if (gameAnswered.value[currentGameIndex.value]) return
  gameAnswers.value[currentGameIndex.value] = answer
  gameAnswered.value[currentGameIndex.value] = true
}

function nextGameItem(): void {
  if (currentGameIndex.value < gameTotalItems.value - 1) {
    currentGameIndex.value++
  } else {
    finishGame()
  }
}

function finishGame(): void {
  let correct = 0
  const items = gameData.value?.items ?? []
  for (let i = 0; i < items.length; i++) {
    const item = items[i]
    if (item && gameAnswers.value[i] === item.answer) {
      correct++
    }
  }
  gameScore.value = correct
  showGameResult.value = true
}

// =====================
// Completion
// =====================
async function completeContent(): Promise<void> {
  if (!content.value) return
  await learningStore.updateProgress(content.value.content_id, 'COMPLETED', contentType.value === 'QUIZ' ? quizScore.value : undefined)
  router.back()
}

function goBack(): void {
  router.back()
}

// =====================
// Lifecycle
// =====================
onMounted(async () => {
  if (!content.value) {
    await learningStore.fetchContents()
  }
  initialLoading.value = false

  if (content.value && content.value.progress?.status !== 'COMPLETED') {
    await learningStore.updateProgress(contentId, 'IN_PROGRESS')
  }

  if (contentType.value === 'QUIZ' && quizData.value?.timeLimit) {
    startQuizTimer()
  }
})
</script>

<template>
  <div class="min-h-screen bg-[#F8F8F8] max-w-[402px] mx-auto flex flex-col font-['Pretendard']">
    <!-- Loading -->
    <div v-if="initialLoading || learningStore.loading" class="flex-1 flex items-center justify-center">
      <div class="text-center">
        <div class="w-[40px] h-[40px] border-4 border-[#E0E0E0] border-t-[#4CAF50] rounded-full animate-spin mx-auto mb-3" />
        <p class="text-[13px] text-[#999]">콘텐츠를 불러오는 중...</p>
      </div>
    </div>

    <!-- Content not found -->
    <div v-else-if="!content" class="flex-1 flex items-center justify-center">
      <div class="text-center px-6">
        <div class="w-[56px] h-[56px] bg-[#F0F0F0] rounded-full flex items-center justify-center mx-auto mb-3">
          <svg class="w-[28px] h-[28px] text-[#B0B0B0]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <p class="text-[14px] font-semibold text-[#333] mb-1">콘텐츠를 불러올 수 없습니다</p>
        <p class="text-[13px] text-[#999] mb-4">요청하신 학습 콘텐츠를 찾지 못했어요.</p>
        <button
          @click="goBack"
          class="text-[13px] text-[#4CAF50] font-semibold"
        >
          뒤로 돌아가기
        </button>
      </div>
    </div>

    <!-- Empty content_data -->
    <template v-else-if="!parsedData">
      <BackHeader :title="content.title" />
      <div class="flex-1 flex items-center justify-center">
        <div class="text-center px-6">
          <div class="w-[64px] h-[64px] bg-[#E8F5E9] rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-[32px] h-[32px] text-[#4CAF50]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <p class="text-[15px] font-semibold text-[#333] mb-1">아직 콘텐츠가 준비되지 않았어요</p>
          <p class="text-[13px] text-[#999]">곧 새로운 학습 콘텐츠가 추가될 예정이에요.</p>
        </div>
      </div>
    </template>

    <!-- ==================== VIDEO ==================== -->
    <template v-else-if="contentType === 'VIDEO' && videoData">
      <BackHeader :title="content.title" />
      <div class="flex-1 flex flex-col">
        <!-- Video player -->
        <div class="px-4 pt-4">
          <!-- YouTube embed -->
          <div v-if="videoData.videoType === 'youtube' && youtubeId" class="relative w-full rounded-[16px] overflow-hidden bg-black" style="aspect-ratio: 16/9;">
            <iframe
              :src="`https://www.youtube.com/embed/${youtubeId}`"
              class="absolute inset-0 w-full h-full"
              frameborder="0"
              allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
              allowfullscreen
            />
          </div>
          <!-- Upload video -->
          <div v-else-if="videoData.videoType === 'upload' && videoData.videoUrl" class="w-full rounded-[16px] overflow-hidden bg-black">
            <video
              controls
              class="w-full"
              :poster="videoData.thumbnailUrl"
              :src="videoData.videoUrl"
              preload="metadata"
            />
          </div>
          <!-- Invalid video -->
          <div v-else class="w-full rounded-[16px] bg-[#F0F0F0] flex items-center justify-center" style="aspect-ratio: 16/9;">
            <p class="text-[13px] text-[#999]">영상을 재생할 수 없습니다</p>
          </div>
        </div>

        <!-- Video info -->
        <div class="px-5 pt-5">
          <div class="flex items-center gap-2 mb-3">
            <span class="bg-[#E3F2FD] text-[#2196F3] text-[11px] font-bold rounded-full px-2.5 py-1">영상</span>
            <span v-if="videoData.duration" class="flex items-center gap-1 text-[11px] text-[#999]">
              <svg class="w-[12px] h-[12px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              {{ videoData.duration }}
            </span>
          </div>
          <h2 class="text-[18px] font-bold text-[#333] mb-2">{{ content.title }}</h2>
          <p v-if="videoData.description" class="text-[14px] text-[#666] leading-relaxed">{{ videoData.description }}</p>
        </div>

        <div class="flex-1" />

        <!-- Complete button -->
        <div class="sticky bottom-0 bg-white border-t border-[#F5F5F5] px-5 py-4">
          <button
            @click="completeContent"
            class="w-full py-3.5 rounded-[12px] text-[14px] font-semibold text-white bg-[#4CAF50] transition-all active:scale-[0.98]"
          >
            학습 완료
          </button>
        </div>
      </div>
    </template>

    <!-- ==================== QUIZ ==================== -->
    <template v-else-if="contentType === 'QUIZ' && quizData">
      <!-- Quiz in progress -->
      <template v-if="!showResult">
        <!-- Header with timer -->
        <header class="bg-white sticky top-0 z-10 border-b border-[#F5F5F5]">
          <div class="flex items-center justify-between h-[56px] px-5">
            <button
              @click="goBack"
              class="w-[32px] h-[32px] flex items-center justify-center rounded-full active:bg-[#F0F0F0] transition-colors"
            >
              <svg class="w-[20px] h-[20px] text-[#333]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M15 18l-6-6 6-6" />
              </svg>
            </button>
            <h2 class="text-[14px] font-bold text-[#333] truncate max-w-[160px]">{{ content.title }}</h2>
            <div class="flex items-center gap-2">
              <span v-if="quizData.timeLimit" class="text-[13px] font-bold" :class="quizTimerSeconds < 30 ? 'text-[#F44336]' : 'text-[#999]'">
                {{ quizFormattedTime }}
              </span>
              <span v-else class="text-[13px] font-bold text-[#4CAF50]">{{ currentQuestionIndex + 1 }}/{{ quizTotalQuestions }}</span>
            </div>
          </div>
        </header>

        <!-- Progress bar -->
        <div class="px-5 py-2 bg-white">
          <div class="flex items-center justify-between mb-1.5">
            <span class="text-[11px] text-[#999]">{{ currentQuestionIndex + 1 }} / {{ quizTotalQuestions }}</span>
            <span class="text-[11px] font-bold text-[#FF9800]">{{ Math.round(((currentQuestionIndex + 1) / quizTotalQuestions) * 100) }}%</span>
          </div>
          <div class="w-full h-[6px] rounded-full bg-[#F0F0F0] overflow-hidden">
            <div
              class="h-full rounded-full bg-[#FF9800] transition-all duration-300"
              :style="{ width: ((currentQuestionIndex + 1) / quizTotalQuestions) * 100 + '%' }"
            />
          </div>
        </div>

        <!-- Question content -->
        <div v-if="currentQuizQuestion" class="flex-1 px-5 pt-6">
          <span class="inline-block bg-[#FFF3E0] text-[#FF9800] text-[11px] font-bold rounded-full px-3 py-1 mb-4">
            문제 {{ currentQuestionIndex + 1 }}
          </span>

          <h2 class="text-[18px] font-bold text-[#333] leading-relaxed mb-4">
            {{ currentQuizQuestion.question }}
          </h2>

          <!-- Question image -->
          <div v-if="currentQuizQuestion.image_url" class="mb-5">
            <img
              :src="currentQuizQuestion.image_url"
              :alt="`문제 ${currentQuestionIndex + 1} 이미지`"
              class="w-full rounded-[12px] object-cover max-h-[200px]"
            />
          </div>

          <!-- Options -->
          <div class="space-y-3">
            <button
              v-for="(option, idx) in currentQuizQuestion.options"
              :key="idx"
              @click="selectQuizAnswer(idx)"
              :disabled="answeredQuestions[currentQuestionIndex] === true"
              :class="[
                answeredQuestions[currentQuestionIndex]
                  ? idx === currentQuizQuestion.correct_index
                    ? 'border-[#4CAF50] bg-[#E8F5E9]'
                    : selectedAnswers[currentQuestionIndex] === idx
                      ? 'border-[#F44336] bg-[#FFEBEE]'
                      : 'border-[#E8E8E8] bg-white opacity-60'
                  : selectedAnswers[currentQuestionIndex] === idx
                    ? 'border-[#FF9800] bg-[#FFF3E0]'
                    : 'border-[#E8E8E8] bg-white'
              ]"
              class="w-full border-2 rounded-[16px] p-4 text-left transition-all duration-200 flex items-center gap-3 active:scale-[0.98]"
            >
              <span
                :class="[
                  answeredQuestions[currentQuestionIndex]
                    ? idx === currentQuizQuestion.correct_index
                      ? 'bg-[#4CAF50] text-white'
                      : selectedAnswers[currentQuestionIndex] === idx
                        ? 'bg-[#F44336] text-white'
                        : 'bg-[#F0F0F0] text-[#B0B0B0]'
                    : selectedAnswers[currentQuestionIndex] === idx
                      ? 'bg-[#FF9800] text-white'
                      : 'bg-[#F0F0F0] text-[#888]'
                ]"
                class="w-[32px] h-[32px] rounded-full flex items-center justify-center text-[13px] font-bold shrink-0 transition-colors"
              >
                {{ idx + 1 }}
              </span>
              <span
                class="text-[14px] flex-1"
                :class="[
                  answeredQuestions[currentQuestionIndex]
                    ? idx === currentQuizQuestion.correct_index
                      ? 'text-[#4CAF50] font-semibold'
                      : selectedAnswers[currentQuestionIndex] === idx
                        ? 'text-[#F44336]'
                        : 'text-[#B0B0B0]'
                    : 'text-[#333]'
                ]"
              >
                {{ option }}
              </span>
              <!-- Feedback icons -->
              <template v-if="answeredQuestions[currentQuestionIndex]">
                <svg v-if="idx === currentQuizQuestion.correct_index" class="w-[20px] h-[20px] text-[#4CAF50] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M5 13l4 4L19 7" />
                </svg>
                <svg v-else-if="selectedAnswers[currentQuestionIndex] === idx" class="w-[20px] h-[20px] text-[#F44336] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M6 18L18 6M6 6l12 12" />
                </svg>
              </template>
            </button>
          </div>
        </div>

        <!-- Next button -->
        <div v-if="answeredQuestions[currentQuestionIndex]" class="sticky bottom-0 bg-white border-t border-[#F5F5F5] px-5 py-4">
          <button
            @click="nextQuizQuestion"
            class="w-full py-3.5 rounded-[12px] text-[14px] font-semibold text-white bg-[#4CAF50] transition-all active:scale-[0.98]"
          >
            {{ currentQuestionIndex < quizTotalQuestions - 1 ? '다음 문제' : '결과 보기' }}
          </button>
        </div>
      </template>

      <!-- Quiz result -->
      <template v-else>
        <BackHeader :title="content.title" />
        <div class="flex-1 flex flex-col items-center justify-center px-6">
          <!-- Score circle -->
          <div class="relative mb-6">
            <svg class="w-[120px] h-[120px] -rotate-90" viewBox="0 0 128 128">
              <circle cx="64" cy="64" r="54" fill="none" stroke="#F0F0F0" stroke-width="8" />
              <circle
                cx="64" cy="64" r="54" fill="none"
                :stroke="quizPassed ? '#4CAF50' : '#F44336'"
                stroke-width="8"
                stroke-linecap="round"
                :stroke-dasharray="339.29"
                :stroke-dashoffset="quizTotalQuestions > 0 ? 339.29 - (339.29 * quizScore) / quizTotalQuestions : 339.29"
                class="transition-all duration-1000"
              />
            </svg>
            <div class="absolute inset-0 flex flex-col items-center justify-center">
              <span class="text-[28px] font-bold" :class="quizPassed ? 'text-[#4CAF50]' : 'text-[#F44336]'">
                {{ quizScore }}
              </span>
              <span class="text-[12px] text-[#999]">/ {{ quizTotalQuestions }}</span>
            </div>
          </div>

          <h2 class="text-[20px] font-bold text-[#333] mb-2">
            {{ quizPassed ? '잘했어요!' : '다시 도전해 볼까요?' }}
          </h2>
          <p class="text-[13px] text-[#888] text-center mb-6">
            {{ quizTotalQuestions }}문제 중 {{ quizScore }}개를 맞혔어요
          </p>

          <!-- Answer review -->
          <div class="w-full rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] bg-white p-4 mb-6">
            <h3 class="text-[13px] font-bold text-[#333] mb-3">정답 확인</h3>
            <div class="space-y-2">
              <div
                v-for="(q, idx) in quizData.questions"
                :key="idx"
                class="flex items-center gap-3 py-2"
              >
                <div
                  class="w-[24px] h-[24px] rounded-full flex items-center justify-center shrink-0"
                  :class="selectedAnswers[idx] === q.correct_index ? 'bg-[#E8F5E9]' : 'bg-[#FFEBEE]'"
                >
                  <svg v-if="selectedAnswers[idx] === q.correct_index" class="w-[14px] h-[14px] text-[#4CAF50]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M5 13l4 4L19 7" />
                  </svg>
                  <svg v-else class="w-[14px] h-[14px] text-[#F44336]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </div>
                <span class="text-[13px] text-[#555] flex-1 truncate">{{ idx + 1 }}. {{ q.question }}</span>
              </div>
            </div>
          </div>

          <!-- Action buttons -->
          <div class="w-full space-y-3 pb-6">
            <button
              @click="retryQuiz"
              class="w-full py-3.5 rounded-[12px] text-[14px] font-semibold text-[#555] bg-[#F8F8F8] transition-all active:scale-[0.98]"
            >
              다시 풀기
            </button>
            <button
              @click="completeContent"
              class="w-full py-3.5 rounded-[12px] text-[14px] font-semibold text-white bg-[#4CAF50] transition-all active:scale-[0.98]"
            >
              학습 완료
            </button>
          </div>
        </div>
      </template>
    </template>

    <!-- ==================== READING ==================== -->
    <template v-else-if="contentType === 'READING' && readingData">
      <BackHeader :title="content.title" />
      <div class="flex-1 flex flex-col">
        <!-- Reading meta -->
        <div class="px-5 pt-4">
          <div class="flex items-center gap-2 mb-4">
            <span class="bg-[#F3E5F5] text-[#9C27B0] text-[11px] font-bold rounded-full px-2.5 py-1">읽기</span>
            <span v-if="readingData.estimatedTime" class="flex items-center gap-1 text-[11px] text-[#999]">
              <svg class="w-[12px] h-[12px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              약 {{ readingData.estimatedTime }}
            </span>
          </div>
        </div>

        <!-- Audio player -->
        <div v-if="readingData.audioUrl" class="px-5 mb-4">
          <div class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] p-4">
            <p class="text-[12px] text-[#999] mb-2">음성으로 듣기</p>
            <audio controls class="w-full" :src="readingData.audioUrl" preload="metadata" />
          </div>
        </div>

        <!-- Image gallery -->
        <div v-if="readingData.images && readingData.images.length > 0" class="px-5 mb-4">
          <div class="flex gap-3 overflow-x-auto pb-2 -mx-5 px-5">
            <img
              v-for="(img, idx) in readingData.images"
              :key="idx"
              :src="img"
              :alt="`이미지 ${idx + 1}`"
              class="w-[200px] h-[140px] rounded-[12px] object-cover shrink-0"
            />
          </div>
        </div>

        <!-- Text content -->
        <div class="px-5 pb-4">
          <div class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] p-5">
            <p class="text-[15px] text-[#333] leading-relaxed whitespace-pre-wrap">{{ readingData.textContent }}</p>
          </div>
        </div>

        <div class="flex-1" />

        <!-- Complete button -->
        <div class="sticky bottom-0 bg-white border-t border-[#F5F5F5] px-5 py-4">
          <button
            @click="completeContent"
            class="w-full py-3.5 rounded-[12px] text-[14px] font-semibold text-white bg-[#4CAF50] transition-all active:scale-[0.98]"
          >
            학습 완료
          </button>
        </div>
      </div>
    </template>

    <!-- ==================== GAME ==================== -->
    <template v-else-if="contentType === 'GAME' && gameData">
      <!-- Game in progress -->
      <template v-if="!showGameResult">
        <header class="bg-white sticky top-0 z-10 border-b border-[#F5F5F5]">
          <div class="flex items-center justify-between h-[56px] px-5">
            <button
              @click="goBack"
              class="w-[32px] h-[32px] flex items-center justify-center rounded-full active:bg-[#F0F0F0] transition-colors"
            >
              <svg class="w-[20px] h-[20px] text-[#333]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M15 18l-6-6 6-6" />
              </svg>
            </button>
            <h2 class="text-[14px] font-bold text-[#333] truncate max-w-[180px]">{{ content.title }}</h2>
            <span class="text-[13px] font-bold text-[#4CAF50]">{{ currentGameIndex + 1 }}/{{ gameTotalItems }}</span>
          </div>
        </header>

        <!-- Progress -->
        <div class="px-5 py-2 bg-white">
          <div class="w-full h-[6px] rounded-full bg-[#F0F0F0] overflow-hidden">
            <div
              class="h-full rounded-full bg-[#4CAF50] transition-all duration-300"
              :style="{ width: gameTotalItems > 0 ? ((currentGameIndex + 1) / gameTotalItems) * 100 + '%' : '0%' }"
            />
          </div>
        </div>

        <!-- Description -->
        <div v-if="gameData.description && currentGameIndex === 0" class="px-5 pt-4">
          <div class="bg-[#E8F5E9] rounded-[12px] p-4 mb-4">
            <p class="text-[13px] text-[#333]">{{ gameData.description }}</p>
          </div>
        </div>

        <!-- Current game item -->
        <div v-if="currentGameItem" class="flex-1 px-5 pt-4">
          <span class="inline-block bg-[#E8F5E9] text-[#4CAF50] text-[11px] font-bold rounded-full px-3 py-1 mb-4">
            {{ currentGameIndex + 1 }}번째
          </span>

          <!-- Question image -->
          <div v-if="currentGameItem.image_url" class="mb-4">
            <img
              :src="currentGameItem.image_url"
              :alt="`문제 ${currentGameIndex + 1} 이미지`"
              class="w-full rounded-[12px] object-cover max-h-[180px]"
            />
          </div>

          <h2 class="text-[18px] font-bold text-[#333] leading-relaxed mb-6">
            {{ currentGameItem.question }}
          </h2>

          <!-- Answer input as buttons (simplified for MVP) -->
          <div class="space-y-3">
            <!-- Generate shuffled options from items (for matching type) -->
            <button
              v-for="(item, idx) in gameData.items"
              :key="idx"
              @click="selectGameAnswer(item.answer)"
              :disabled="gameAnswered[currentGameIndex] === true"
              :class="[
                gameAnswered[currentGameIndex]
                  ? gameAnswers[currentGameIndex] === item.answer && item.answer === currentGameItem.answer
                    ? 'border-[#4CAF50] bg-[#E8F5E9]'
                    : gameAnswers[currentGameIndex] === item.answer
                      ? 'border-[#F44336] bg-[#FFEBEE]'
                      : item.answer === currentGameItem.answer
                        ? 'border-[#4CAF50] bg-[#E8F5E9]'
                        : 'border-[#E8E8E8] bg-white opacity-60'
                  : 'border-[#E8E8E8] bg-white'
              ]"
              class="w-full border-2 rounded-[16px] p-4 text-left transition-all duration-200 flex items-center gap-3 active:scale-[0.98]"
            >
              <span
                :class="[
                  gameAnswered[currentGameIndex] && (item.answer === currentGameItem.answer)
                    ? 'bg-[#4CAF50] text-white'
                    : gameAnswered[currentGameIndex] && gameAnswers[currentGameIndex] === item.answer
                      ? 'bg-[#F44336] text-white'
                      : 'bg-[#F0F0F0] text-[#888]'
                ]"
                class="w-[32px] h-[32px] rounded-full flex items-center justify-center text-[13px] font-bold shrink-0"
              >
                {{ idx + 1 }}
              </span>
              <span class="text-[14px] text-[#333]">{{ item.answer }}</span>
            </button>
          </div>
        </div>

        <!-- Next button -->
        <div v-if="gameAnswered[currentGameIndex]" class="sticky bottom-0 bg-white border-t border-[#F5F5F5] px-5 py-4">
          <button
            @click="nextGameItem"
            class="w-full py-3.5 rounded-[12px] text-[14px] font-semibold text-white bg-[#4CAF50] transition-all active:scale-[0.98]"
          >
            {{ currentGameIndex < gameTotalItems - 1 ? '다음' : '결과 보기' }}
          </button>
        </div>
      </template>

      <!-- Game result -->
      <template v-else>
        <BackHeader :title="content.title" />
        <div class="flex-1 flex flex-col items-center justify-center px-6">
          <div class="relative mb-6">
            <div class="w-[80px] h-[80px] bg-[#E8F5E9] rounded-full flex items-center justify-center">
              <svg class="w-[40px] h-[40px] text-[#4CAF50]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
          </div>

          <h2 class="text-[20px] font-bold text-[#333] mb-2">게임 완료!</h2>
          <p class="text-[13px] text-[#888] text-center mb-2">
            {{ gameTotalItems }}개 문제 중 {{ gameScore }}개를 맞혔어요
          </p>

          <div class="w-full space-y-3 mt-6 pb-6">
            <button
              @click="completeContent"
              class="w-full py-3.5 rounded-[12px] text-[14px] font-semibold text-white bg-[#4CAF50] transition-all active:scale-[0.98]"
            >
              학습 완료
            </button>
            <button
              @click="goBack"
              class="w-full py-3.5 rounded-[12px] text-[14px] font-semibold text-[#555] bg-[#F8F8F8] transition-all active:scale-[0.98]"
            >
              목록으로
            </button>
          </div>
        </div>
      </template>
    </template>
  </div>
</template>
