<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useScreeningStore } from '../../stores/screeningStore'
import { useToastStore } from '@shared/stores/toastStore'
import { screeningApi } from '@shared/api/screeningApi'
import type { ScreeningTest, ScreeningResult, ScreeningQuestion } from '@shared/types'

const router = useRouter()
const route = useRoute()
const screeningStore = useScreeningStore()
const toast = useToastStore()

const LIKERT_OPTIONS = ['매우그렇다', '그렇다', '보통', '아니다', '매우아니다'] as const
const LIKERT_SCORES = [5, 4, 3, 2, 1] as const

const testId = computed(() => Number(route.params.id))
const currentTest = ref<ScreeningTest | null>(null)
const currentQuestion = ref(0)
const answers = ref<Record<number, number>>({})
const showSubmitConfirm = ref(false)
const isLikert = computed(() => currentTest.value?.test_type === 'LIKERT')
const showResultModal = ref(false)
const submitResult = ref<ScreeningResult | null>(null)
const submitting = ref(false)
const questions = ref<ScreeningQuestion[]>([])
const questionsLoading = ref(true)

// 타이머
const remainingSeconds = ref(0)
const timerInterval = ref<ReturnType<typeof setInterval> | null>(null)
const hasTimeLimit = computed(() => currentTest.value?.time_limit != null && currentTest.value.time_limit > 0)

const timerDisplay = computed(() => {
  const mins = Math.floor(remainingSeconds.value / 60)
  const secs = remainingSeconds.value % 60
  return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`
})

const totalQuestions = computed(() => questions.value.length)
const progress = computed(() => totalQuestions.value > 0 ? ((currentQuestion.value + 1) / totalQuestions.value) * 100 : 0)
const isLastQuestion = computed(() => currentQuestion.value === totalQuestions.value - 1)
const isFirstQuestion = computed(() => currentQuestion.value === 0)
const currentQ = computed(() => questions.value[currentQuestion.value])
const hasAnswer = computed(() => answers.value[currentQuestion.value] !== undefined)

function startTimer() {
  if (!hasTimeLimit.value || !currentTest.value?.time_limit) return
  remainingSeconds.value = currentTest.value.time_limit * 60
  timerInterval.value = setInterval(() => {
    remainingSeconds.value--
    if (remainingSeconds.value <= 0) {
      if (timerInterval.value) clearInterval(timerInterval.value)
      submitTest()
    }
  }, 1000)
}

function stopTimer() {
  if (timerInterval.value) {
    clearInterval(timerInterval.value)
    timerInterval.value = null
  }
}

onMounted(async () => {
  // 테스트 정보 로딩
  if (screeningStore.tests.length === 0) {
    await screeningStore.fetchTests()
  }
  currentTest.value = screeningStore.tests.find(t => t.test_id === testId.value) || null

  // 문항 로딩
  try {
    const res = await screeningApi.getQuestions(testId.value)
    if (res.data.success) {
      questions.value = res.data.data
    }
  } catch (e: any) {
    toast.error(e.response?.data?.message || '데이터를 불러오지 못했습니다.')
    questions.value = []
  } finally {
    questionsLoading.value = false
  }

  // 타이머 시작
  startTimer()
})

onUnmounted(() => {
  stopTimer()
})

function selectOption(index: number): void {
  answers.value[currentQuestion.value] = index
}

function goNext(): void {
  if (isLastQuestion.value) {
    showSubmitConfirm.value = true
  } else {
    currentQuestion.value++
  }
}

function goPrev(): void {
  if (!isFirstQuestion.value) {
    currentQuestion.value--
  }
}

async function submitTest(): Promise<void> {
  showSubmitConfirm.value = false
  submitting.value = true
  stopTimer()

  // answers를 { question_id: value } 형식으로 변환
  const formattedAnswers: Record<number, string> = {}
  for (const [qIndex, optIdx] of Object.entries(answers.value)) {
    const question = questions.value[Number(qIndex)]
    const idx = Number(optIdx)
    if (question) {
      if (isLikert.value) {
        // 리커트: 점수값(5,4,3,2,1) 전송
        const likertScore = LIKERT_SCORES[idx]
        formattedAnswers[question.question_id] = String(likertScore ?? 0)
      } else {
        // 객관식: 선택 텍스트 전송
        const opt = question.options[idx]
        formattedAnswers[question.question_id] = opt ?? ''
      }
    }
  }

  const result = await screeningStore.submitTest(testId.value, formattedAnswers)
  submitting.value = false

  if (result) {
    submitResult.value = result
    showResultModal.value = true
  } else {
    toast.error(screeningStore.error || '제출에 실패했습니다.')
  }
}

function goToResults(): void {
  showResultModal.value = false
  if (submitResult.value) {
    router.push({ name: 'screening-result-detail', params: { resultId: submitResult.value.result_id } })
  } else {
    router.push({ name: 'screening-results' })
  }
}

function closeTest(): void {
  router.back()
}
</script>

<template>
  <div class="min-h-screen bg-white max-w-[402px] mx-auto flex flex-col">
    <!-- 로딩 상태 -->
    <div v-if="questionsLoading" class="flex-1 flex items-center justify-center">
      <p class="text-[14px] text-[#888]">문항을 불러오는 중...</p>
    </div>

    <!-- 문항이 없는 경우 -->
    <div v-else-if="questions.length === 0" class="flex-1 flex flex-col items-center justify-center px-5">
      <p class="text-[16px] text-[#555] font-medium mb-4">등록된 문항이 없습니다.</p>
      <button
        @click="closeTest"
        class="bg-[#4CAF50] text-white rounded-[12px] px-6 py-3 text-[14px] font-semibold"
      >
        돌아가기
      </button>
    </div>

    <!-- 테스트 진행 -->
    <template v-else-if="currentQ">
      <!-- Top Bar -->
      <header class="bg-white sticky top-0 z-10 px-5 py-4">
        <div class="flex items-center justify-between">
          <!-- Close Button -->
          <button
            @click="closeTest"
            class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-[#F5F5F5]"
          >
            <svg class="w-5 h-5 text-[#333]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>

          <!-- Counter -->
          <span class="text-[14px] font-bold text-[#333]">{{ currentQuestion + 1 }}/{{ totalQuestions }}</span>

          <!-- Timer -->
          <div v-if="hasTimeLimit" class="flex items-center gap-1 text-[#888]">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span class="text-[13px] font-medium" :class="remainingSeconds <= 60 ? 'text-red-500' : ''">{{ timerDisplay }}</span>
          </div>
          <div v-else class="w-8" />
        </div>
      </header>

      <!-- Progress Bar -->
      <div class="mx-5 mb-2">
        <div class="bg-[#F0F0F0] rounded-full h-2 overflow-hidden">
          <div
            class="bg-[#4CAF50] rounded-full h-full transition-all duration-300 ease-out"
            :style="{ width: progress + '%' }"
          />
        </div>
      </div>

      <!-- Question Area -->
      <div class="px-5 pt-6 flex-1">
        <!-- Question Badge -->
        <div class="flex items-center gap-2 mb-4">
          <span class="inline-block bg-[#E8F5E9] text-[#4CAF50] rounded-full px-3 py-1 text-[12px] font-bold">
            문제 {{ currentQuestion + 1 }}
          </span>
          <span v-if="isLikert && currentQ.sub_domain" class="inline-block bg-[#E3F2FD] text-[#2196F3] rounded-full px-3 py-1 text-[12px] font-bold">
            {{ currentQ.sub_domain }}
          </span>
        </div>

        <!-- Question Text -->
        <h2 class="text-[18px] font-bold text-[#333] leading-relaxed mb-8">
          {{ currentQ.content }}
        </h2>

        <!-- Likert Scale Options -->
        <div v-if="isLikert" class="space-y-3">
          <button
            v-for="(label, index) in LIKERT_OPTIONS"
            :key="index"
            @click="selectOption(index)"
            :class="answers[currentQuestion] === index
              ? 'border-[#4CAF50] bg-[#E8F5E9]'
              : 'border-[#E8E8E8] bg-white hover:border-[#E0E0E0]'"
            class="w-full border-[1.5px] rounded-[12px] p-4 text-left transition-all duration-200 flex items-center gap-3"
          >
            <span
              :class="answers[currentQuestion] === index
                ? 'bg-[#4CAF50] text-white'
                : 'bg-[#F0F0F0] text-[#999]'"
              class="w-8 h-8 rounded-full flex items-center justify-center text-[14px] font-bold shrink-0 transition-colors"
            >
              {{ LIKERT_SCORES[index] }}
            </span>
            <span
              :class="answers[currentQuestion] === index ? 'text-[#4CAF50] font-semibold' : 'text-[#333]'"
              class="text-[14px] transition-colors"
            >
              {{ label }}
            </span>
          </button>
        </div>

        <!-- Multiple Choice Options -->
        <div v-else class="space-y-3">
          <button
            v-for="(option, index) in currentQ.options"
            :key="index"
            @click="selectOption(index)"
            :class="answers[currentQuestion] === index
              ? 'border-[#4CAF50] bg-[#E8F5E9]'
              : 'border-[#E8E8E8] bg-white hover:border-[#E0E0E0]'"
            class="w-full border-[1.5px] rounded-[12px] p-4 text-left transition-all duration-200 flex items-center gap-3"
          >
            <span
              :class="answers[currentQuestion] === index
                ? 'bg-[#4CAF50] text-white'
                : 'bg-[#F0F0F0] text-[#999]'"
              class="w-8 h-8 rounded-full flex items-center justify-center text-[14px] font-bold shrink-0 transition-colors"
            >
              {{ index + 1 }}
            </span>
            <span
              :class="answers[currentQuestion] === index ? 'text-[#4CAF50] font-semibold' : 'text-[#333]'"
              class="text-[14px] transition-colors"
            >
              {{ option }}
            </span>
          </button>
        </div>
      </div>

      <!-- Bottom Navigation -->
      <div class="sticky bottom-0 bg-white border-t border-[#F0F0F0] px-5 py-4">
        <div class="flex gap-3">
          <button
            v-if="!isFirstQuestion"
            @click="goPrev"
            class="flex-1 py-3 rounded-[12px] text-[14px] font-semibold text-[#555] bg-white border border-[#E0E0E0] transition-colors active:scale-[0.98]"
          >
            이전
          </button>
          <div v-else class="flex-1" />

          <button
            @click="goNext"
            :disabled="!hasAnswer || submitting"
            :class="hasAnswer && !submitting
              ? 'bg-[#4CAF50] text-white active:scale-[0.98]'
              : 'bg-[#E0E0E0] text-[#999] cursor-not-allowed'"
            class="flex-1 py-3 rounded-[12px] text-[14px] font-semibold transition-all"
          >
            {{ submitting ? '제출 중...' : (isLastQuestion ? '제출하기' : '다음') }}
          </button>
        </div>
      </div>
    </template>

    <!-- Submit Confirm Modal -->
    <div v-if="showSubmitConfirm" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center px-6">
      <div class="bg-white rounded-[16px] p-6 w-full max-w-sm shadow-xl">
        <!-- Icon -->
        <div class="text-center mb-5">
          <div class="w-14 h-14 bg-[#E8F5E9] rounded-full flex items-center justify-center mx-auto mb-3">
            <svg class="w-7 h-7 text-[#4CAF50]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <h3 class="text-[17px] font-bold text-[#333] mb-1">검사를 제출할까요?</h3>
          <p class="text-[13px] text-[#888]">제출 후에는 수정할 수 없어요</p>
        </div>

        <!-- Buttons -->
        <div class="flex gap-3">
          <button
            @click="showSubmitConfirm = false"
            class="flex-1 py-3 rounded-[12px] text-[14px] font-semibold text-[#555] bg-white border border-[#E0E0E0] transition-colors active:scale-[0.98]"
          >
            취소
          </button>
          <button
            @click="submitTest"
            class="flex-1 py-3 rounded-[12px] text-[14px] font-semibold text-white bg-[#4CAF50] transition-colors active:scale-[0.98]"
          >
            제출하기
          </button>
        </div>
      </div>
    </div>

    <!-- Result Modal (shown after successful submit) -->
    <div v-if="showResultModal && submitResult" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center px-6">
      <div class="bg-white rounded-[16px] p-6 w-full max-w-sm shadow-xl">
        <div class="text-center mb-5">
          <div class="w-14 h-14 bg-[#E8F5E9] rounded-full flex items-center justify-center mx-auto mb-3">
            <svg class="w-7 h-7 text-[#4CAF50]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
          </div>
          <h3 class="text-[17px] font-bold text-[#333] mb-1">검사 완료!</h3>
          <p class="text-[28px] font-bold text-[#4CAF50] mt-2">{{ submitResult.score }}점</p>
          <p v-if="submitResult.level" class="text-[13px] text-[#888] mt-1">{{ submitResult.level }}</p>
        </div>

        <button
          @click="goToResults"
          class="w-full py-3 rounded-[12px] text-[14px] font-semibold text-white bg-[#4CAF50] transition-colors active:scale-[0.98]"
        >
          결과 보기
        </button>
      </div>
    </div>
  </div>
</template>
