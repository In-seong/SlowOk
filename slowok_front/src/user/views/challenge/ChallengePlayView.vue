<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import type { Challenge, ChallengeQuestion } from '@shared/types'
import MatchingGameQuestion from '@user/components/challenge/MatchingGameQuestion.vue'
import ImageChoiceQuestion from '@user/components/challenge/ImageChoiceQuestion.vue'
import ImageTextQuestion from '@user/components/challenge/ImageTextQuestion.vue'
// import ImageVoiceQuestion from '@user/components/challenge/ImageVoiceQuestion.vue' // [미사용] 음성 기능 비활성화
import turtleSuccessImg from '@shared/assets/turtle-success.png'
import { useChallengeStore } from '@user/stores/challengeStore'

const route = useRoute()
const router = useRouter()
const challengeStore = useChallengeStore()
const challengeId = Number(route.params.id)

const currentQuestion = ref(0)
const answers = ref<Record<number, number>>({})
const questionResults = ref<Record<number, boolean>>({})
const isCompleted = ref(false)
const score = ref(0)
const timerInterval = ref<ReturnType<typeof setInterval> | null>(null)
const submitting = ref(false)
const dataReady = ref(false)
const fetchError = ref(false)

// Duolingo-style feedback state
const showFeedback = ref(false)
const feedbackCorrect = ref(false)
const feedbackAnswer = ref('')

const challenge = computed<Challenge | null>(() => challengeStore.currentChallenge)
const questions = computed<ChallengeQuestion[]>(() => challenge.value?.questions ?? [])
const totalQuestions = computed(() => questions.value.length)
const progress = computed(() => totalQuestions.value > 0 ? ((currentQuestion.value) / totalQuestions.value) * 100 : 0)
const currentQ = computed<ChallengeQuestion | null>(() => questions.value[currentQuestion.value] ?? null)
const passThreshold = computed(() => Math.ceil(totalQuestions.value * 0.7))
const isPassed = computed(() => score.value >= passThreshold.value)

const currentType = computed(() => currentQ.value?.question_type ?? 'multiple_choice')

// Check if current question has been answered
const hasAnswer = computed(() => {
  const idx = currentQuestion.value
  if (currentType.value === 'multiple_choice' || currentType.value === 'image_choice') {
    return answers.value[idx] !== undefined || questionResults.value[idx] !== undefined
  }
  return questionResults.value[idx] !== undefined
})

function stopTimer(): void {
  if (timerInterval.value) {
    clearInterval(timerInterval.value)
    timerInterval.value = null
  }
}

function selectOption(index: number): void {
  if (showFeedback.value) return
  answers.value[currentQuestion.value] = index
}

function getCorrectIndex(q: ChallengeQuestion): number {
  if (!q.correct_answer) return -1
  return q.options.findIndex(opt => opt === q.correct_answer)
}

// "확인" 버튼 클릭 → 피드백 표시
function checkAnswer(): void {
  if (!currentQ.value) return

  const idx = currentQuestion.value
  const q = currentQ.value
  const type = q.question_type ?? 'multiple_choice'

  if (type === 'multiple_choice') {
    const selectedIdx = answers.value[idx]
    const correctIdx = getCorrectIndex(q)
    const correct = selectedIdx === correctIdx
    questionResults.value[idx] = correct
    feedbackCorrect.value = correct
    feedbackAnswer.value = q.correct_answer ?? ''
  } else if (questionResults.value[idx] !== undefined) {
    // image_choice, matching, image_text, image_voice — 컴포넌트가 이미 판정
    feedbackCorrect.value = questionResults.value[idx]
    feedbackAnswer.value = q.correct_answer ?? q.accept_answers?.[0] ?? ''
  } else {
    // 컴포넌트가 아직 결과를 emit하지 않은 경우 (방어)
    feedbackCorrect.value = false
    feedbackAnswer.value = q.correct_answer ?? q.accept_answers?.[0] ?? ''
  }

  showFeedback.value = true
}

// "계속하기" 버튼 → 다음 문항 or 완료
function continueNext(): void {
  showFeedback.value = false

  if (currentQuestion.value >= totalQuestions.value - 1) {
    finishChallenge()
  } else {
    currentQuestion.value++
  }
}

// Handler for sub-component answers
function onMatchingAnswered(result: { correct: number; total: number }) {
  const idx = currentQuestion.value
  questionResults.value[idx] = result.correct === result.total
}

function onImageChoiceAnswered(result: { correct: boolean }) {
  const idx = currentQuestion.value
  questionResults.value[idx] = result.correct
}

function onImageTextAnswered(result: { correct: boolean }) {
  const idx = currentQuestion.value
  questionResults.value[idx] = result.correct
}

// [미사용] 음성 기능 비활성화
// function onImageVoiceAnswered(result: { correct: boolean }) {
//   const idx = currentQuestion.value
//   questionResults.value[idx] = result.correct
// }

async function finishChallenge(): Promise<void> {
  stopTimer()
  let correctCount = 0

  for (let i = 0; i < totalQuestions.value; i++) {
    const q = questions.value[i]
    if (!q) continue

    const type = q.question_type ?? 'multiple_choice'
    if (type === 'multiple_choice') {
      if (answers.value[i] === getCorrectIndex(q)) correctCount++
    } else {
      if (questionResults.value[i]) correctCount++
    }
  }

  score.value = correctCount
  isCompleted.value = true

  const passed = correctCount >= passThreshold.value
  submitting.value = true
  await challengeStore.submitAttempt(challengeId, correctCount, passed)
  submitting.value = false
}

function goHome(): void {
  router.push({ name: 'home' })
}

function goClose(): void {
  router.push({ name: 'home' })
}

// Stars calculation for completion
function getStars(): number {
  if (!isPassed.value) return 0
  const ratio = totalQuestions.value > 0 ? score.value / totalQuestions.value : 0
  if (ratio >= 1) return 3
  if (ratio >= 0.85) return 2
  return 1
}

onMounted(async () => {
  await challengeStore.fetchChallenge(challengeId)
  if (!challenge.value) {
    fetchError.value = true
  }
  dataReady.value = true
})

onUnmounted(() => {
  stopTimer()
})
</script>

<template>
  <div class="min-h-screen bg-[#F8F8F8] max-w-[402px] mx-auto flex flex-col font-['Pretendard']">
    <!-- Loading -->
    <div v-if="!dataReady || challengeStore.loading" class="flex-1 flex flex-col items-center justify-center">
      <div class="w-[40px] h-[40px] border-4 border-[#4CAF50] border-t-transparent rounded-full animate-spin mb-4" />
      <p class="text-[13px] text-[#999]">챌린지를 준비하는 중...</p>
    </div>

    <!-- Error -->
    <div v-else-if="fetchError || !challenge" class="flex-1 flex flex-col items-center justify-center px-6">
      <div class="w-[56px] h-[56px] bg-[#FFEBEE] rounded-full flex items-center justify-center mx-auto mb-3">
        <svg class="w-[28px] h-[28px] text-[#F44336]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </div>
      <p class="text-[14px] font-semibold text-[#333] mb-1">챌린지를 불러올 수 없습니다</p>
      <p class="text-[13px] text-[#999] mb-4">요청하신 챌린지를 찾지 못했어요.</p>
      <button @click="goHome" class="text-[13px] text-[#4CAF50] font-semibold">홈으로 돌아가기</button>
    </div>

    <!-- No questions -->
    <div v-else-if="questions.length === 0" class="flex-1 flex flex-col items-center justify-center px-6">
      <div class="w-[64px] h-[64px] bg-[#E8F5E9] rounded-full flex items-center justify-center mx-auto mb-4">
        <svg class="w-[32px] h-[32px] text-[#4CAF50]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </div>
      <p class="text-[15px] font-semibold text-[#333] mb-1">아직 문제가 준비되지 않았어요</p>
      <p class="text-[13px] text-[#999] mb-6">곧 새로운 문제가 추가될 예정이에요.</p>
      <button @click="goHome" class="px-6 py-3 rounded-[16px] text-[14px] font-semibold text-white bg-[#4CAF50] shadow-[0_4px_0_#388E3C] active:shadow-[0_2px_0_#388E3C] active:translate-y-[2px] transition-all">홈으로</button>
    </div>

    <template v-else>
      <!-- ===== HEADER: X close + thin progress bar ===== -->
      <header class="bg-white sticky top-0 z-10">
        <div class="flex items-center gap-3 h-[52px] px-4">
          <button @click="goClose" class="w-[32px] h-[32px] flex items-center justify-center rounded-full active:bg-[#F0F0F0] transition-colors shrink-0">
            <svg class="w-[22px] h-[22px] text-[#999]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
          <!-- Progress -->
          <div class="flex-1 flex flex-col gap-1">
            <div class="flex items-center justify-between">
              <span class="text-[12px] font-semibold text-[#333]">{{ currentQuestion + 1 }} / {{ totalQuestions }}</span>
            </div>
            <div class="h-[8px] rounded-full bg-[#E8E8E8] overflow-hidden">
              <div
                class="h-full rounded-full bg-[#4CAF50] transition-all duration-500"
                :style="{ width: (isCompleted ? 100 : progress) + '%' }"
              />
            </div>
          </div>
        </div>
      </header>

      <!-- ===== QUIZ CONTENT ===== -->
      <template v-if="!isCompleted && currentQ">
        <div class="flex-1 px-5 pt-6 pb-32 overflow-y-auto">
          <!-- 기존 객관식 -->
          <template v-if="currentType === 'multiple_choice'">
            <h2 class="text-[20px] font-bold text-[#333] leading-relaxed mb-6">{{ currentQ.content }}</h2>
            <div v-if="currentQ.image_url" class="mb-5">
              <img :src="currentQ.image_url" :alt="`문제 ${currentQuestion + 1} 이미지`" class="w-full rounded-[16px] object-cover max-h-[200px]" />
            </div>
            <div class="space-y-3">
              <button
                v-for="(option, index) in currentQ.options"
                :key="index"
                @click="selectOption(index)"
                :disabled="showFeedback"
                :class="[
                  answers[currentQuestion] === index
                    ? 'border-[#4CAF50] bg-[#E8F5E9] shadow-[0_3px_0_#388E3C]'
                    : 'border-[#E0E0E0] bg-white shadow-[0_3px_0_#E0E0E0]',
                  showFeedback ? 'pointer-events-none' : ''
                ]"
                class="w-full border-2 rounded-2xl p-4 text-left transition-all duration-150 flex items-center gap-3 active:translate-y-[2px] active:shadow-none"
              >
                <span
                  :class="answers[currentQuestion] === index ? 'bg-[#4CAF50] text-white' : 'bg-[#F0F0F0] text-[#888]'"
                  class="w-[32px] h-[32px] rounded-full flex items-center justify-center text-[13px] font-bold shrink-0 transition-colors"
                >
                  {{ index + 1 }}
                </span>
                <span
                  :class="answers[currentQuestion] === index ? 'text-[#333] font-semibold' : 'text-[#333]'"
                  class="text-[16px] transition-colors"
                >
                  {{ option }}
                </span>
              </button>
            </div>
          </template>

          <!-- 매칭 게임 -->
          <MatchingGameQuestion
            v-else-if="currentType === 'matching'"
            :question="currentQ"
            @answered="onMatchingAnswered"
          />

          <!-- 그림카드 택1 -->
          <ImageChoiceQuestion
            v-else-if="currentType === 'image_choice'"
            :question="currentQ"
            @answered="onImageChoiceAnswered"
          />

          <!-- 그림카드 텍스트 입력 -->
          <ImageTextQuestion
            v-else-if="currentType === 'image_text'"
            :question="currentQ"
            @answered="onImageTextAnswered"
          />

          <!-- [미사용] 음성 기능 비활성화 -->
        </div>

        <!-- ===== BOTTOM: Big "확인" button ===== -->
        <div
          v-if="!showFeedback"
          class="fixed bottom-0 inset-x-0 mx-auto w-full max-w-[402px] bg-white border-t border-[#F0F0F0] px-5 py-4 z-20"
        >
          <button
            @click="checkAnswer"
            :disabled="!hasAnswer"
            :class="hasAnswer
              ? 'bg-[#4CAF50] text-white shadow-[0_4px_0_#388E3C] active:shadow-[0_2px_0_#388E3C] active:translate-y-[2px]'
              : 'bg-[#E0E0E0] text-[#9E9E9E] cursor-not-allowed'"
            class="w-full py-4 rounded-2xl text-[16px] font-bold transition-all"
          >
            확인
          </button>
        </div>

        <!-- ===== FEEDBACK BANNER (slides up from bottom) ===== -->
        <div
          v-if="showFeedback"
          class="fixed bottom-0 inset-x-0 mx-auto w-full max-w-[402px] z-30 animate-slide-up"
          :class="feedbackCorrect ? 'bg-[#E8F5E9]' : 'bg-[#FFEBEE]'"
        >
          <div class="px-5 py-5">
            <div class="flex items-center gap-2 mb-2">
              <!-- Correct icon -->
              <div
                v-if="feedbackCorrect"
                class="w-8 h-8 rounded-full bg-[#4CAF50] flex items-center justify-center"
              >
                <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M5 13l4 4L19 7" />
                </svg>
              </div>
              <!-- Wrong icon -->
              <div
                v-else
                class="w-8 h-8 rounded-full bg-[#F44336] flex items-center justify-center"
              >
                <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M6 18L18 6M6 6l12 12" />
                </svg>
              </div>
              <span
                class="text-[18px] font-bold"
                :class="feedbackCorrect ? 'text-[#4CAF50]' : 'text-[#F44336]'"
              >
                {{ feedbackCorrect ? '정답이에요!' : '아쉬워요' }}
              </span>
            </div>
            <p v-if="!feedbackCorrect && feedbackAnswer" class="text-[14px] text-[#F44336] mb-3 ml-10">
              정답: {{ feedbackAnswer }}
            </p>
            <button
              @click="continueNext"
              :class="feedbackCorrect
                ? 'bg-[#4CAF50] shadow-[0_4px_0_#388E3C]'
                : 'bg-[#F44336] shadow-[0_4px_0_#C62828]'"
              class="w-full py-4 rounded-2xl text-[16px] font-bold text-white transition-all active:translate-y-[2px] active:shadow-none"
            >
              계속하기
            </button>
          </div>
        </div>
      </template>

      <!-- ===== COMPLETION STATE: Stars + Score ===== -->
      <template v-else-if="isCompleted">
        <div class="flex-1 flex flex-col items-center justify-center px-6">
          <div v-if="submitting" class="mb-4">
            <div class="w-[24px] h-[24px] border-3 border-[#4CAF50] border-t-transparent rounded-full animate-spin mx-auto" />
            <p class="text-[11px] text-[#999] mt-2">결과를 저장하는 중...</p>
          </div>

          <!-- 거북이 캐릭터 -->
          <img :src="turtleSuccessImg" alt="참 잘했어요!" class="w-[140px] h-[140px] object-contain mb-2" />

          <!-- Stars -->
          <div class="flex gap-2 mb-4">
            <svg
              v-for="i in 3"
              :key="i"
              class="w-12 h-12 transition-all duration-500"
              :class="i <= getStars() ? 'text-[#FFC107]' : 'text-[#E0E0E0]'"
              :style="{ transitionDelay: i * 200 + 'ms' }"
              viewBox="0 0 24 24"
              fill="currentColor"
            >
              <path d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" />
            </svg>
          </div>

          <!-- Score circle -->
          <div class="relative mb-4">
            <svg class="w-[120px] h-[120px] -rotate-90" viewBox="0 0 128 128">
              <circle cx="64" cy="64" r="54" fill="none" stroke="#F0F0F0" stroke-width="8" />
              <circle
                cx="64" cy="64" r="54" fill="none"
                :stroke="isPassed ? '#4CAF50' : '#F44336'"
                stroke-width="8" stroke-linecap="round"
                :stroke-dasharray="339.29"
                :stroke-dashoffset="totalQuestions > 0 ? 339.29 - (339.29 * score) / totalQuestions : 339.29"
                class="transition-all duration-1000"
              />
            </svg>
            <div class="absolute inset-0 flex flex-col items-center justify-center">
              <span class="text-[28px] font-bold" :class="isPassed ? 'text-[#4CAF50]' : 'text-[#F44336]'">{{ score }}</span>
              <span class="text-[12px] text-[#999]">/ {{ totalQuestions }}</span>
            </div>
          </div>

          <h2 class="text-[22px] font-bold text-[#333] mb-2">
            {{ isPassed ? '챌린지 성공!' : score >= totalQuestions * 0.4 ? '아깝네요!' : '다시 도전해요!' }}
          </h2>
          <p class="text-[13px] text-[#888] text-center mb-8">{{ totalQuestions }}문제 중 {{ score }}개 맞혔어요</p>

          <!-- Continue button -->
          <button
            @click="goHome"
            class="w-full py-4 rounded-2xl text-[16px] font-bold text-white bg-[#4CAF50] shadow-[0_4px_0_#388E3C] active:shadow-[0_2px_0_#388E3C] active:translate-y-[2px] transition-all"
          >
            계속하기
          </button>
        </div>
      </template>
    </template>
  </div>
</template>

<style scoped>
@keyframes slide-up {
  from {
    transform: translateY(100%);
  }
  to {
    transform: translateY(0);
  }
}
.animate-slide-up {
  animation: slide-up 0.3s ease-out;
}
</style>
