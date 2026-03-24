<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import type { Challenge, ChallengeQuestion } from '@shared/types'
import CardSection from '@shared/components/ui/CardSection.vue'
import MatchingGameQuestion from '@user/components/challenge/MatchingGameQuestion.vue'
import ImageChoiceQuestion from '@user/components/challenge/ImageChoiceQuestion.vue'
import ImageTextQuestion from '@user/components/challenge/ImageTextQuestion.vue'
import ImageVoiceQuestion from '@user/components/challenge/ImageVoiceQuestion.vue'
import { useChallengeStore } from '@user/stores/challengeStore'

const route = useRoute()
const router = useRouter()
const challengeStore = useChallengeStore()
const challengeId = Number(route.params.id)

const currentQuestion = ref(0)
const answers = ref<Record<number, number>>({})
const questionResults = ref<Record<number, boolean>>({})
const isCompleted = ref(false)
const showRewardModal = ref(false)
const score = ref(0)
const timerSeconds = ref(300)
const timerInterval = ref<ReturnType<typeof setInterval> | null>(null)
const submitting = ref(false)
const dataReady = ref(false)
const fetchError = ref(false)

const challenge = computed<Challenge | null>(() => challengeStore.currentChallenge)
const questions = computed<ChallengeQuestion[]>(() => challenge.value?.questions ?? [])
const totalQuestions = computed(() => questions.value.length)
const progress = computed(() => totalQuestions.value > 0 ? ((currentQuestion.value + 1) / totalQuestions.value) * 100 : 0)
const currentQ = computed<ChallengeQuestion | null>(() => questions.value[currentQuestion.value] ?? null)
const isLastQuestion = computed(() => currentQuestion.value === totalQuestions.value - 1)
const isFirstQuestion = computed(() => currentQuestion.value === 0)
const passThreshold = computed(() => Math.ceil(totalQuestions.value * 0.7))
const isPassed = computed(() => score.value >= passThreshold.value)
const formattedTime = computed(() => {
  const min = Math.floor(timerSeconds.value / 60)
  const sec = timerSeconds.value % 60
  return `${min}:${sec.toString().padStart(2, '0')}`
})

const optionLabels = ['\u2460', '\u2461', '\u2462', '\u2463']

// 현재 문항의 유형
const currentType = computed(() => currentQ.value?.question_type ?? 'multiple_choice')

// 현재 문항이 이미 답변되었는지
const hasAnswer = computed(() => {
  const idx = currentQuestion.value
  // 기존 객관식
  if (currentType.value === 'multiple_choice' || currentType.value === 'image_choice') {
    return answers.value[idx] !== undefined || questionResults.value[idx] !== undefined
  }
  // 매칭/텍스트/음성은 questionResults로 판정
  return questionResults.value[idx] !== undefined
})

function startTimer(): void {
  timerInterval.value = setInterval(() => {
    if (timerSeconds.value > 0) {
      timerSeconds.value--
    } else {
      finishChallenge()
    }
  }, 1000)
}

function stopTimer(): void {
  if (timerInterval.value) {
    clearInterval(timerInterval.value)
    timerInterval.value = null
  }
}

function selectOption(index: number): void {
  answers.value[currentQuestion.value] = index
}

function goNext(): void {
  if (isLastQuestion.value) {
    finishChallenge()
  } else {
    currentQuestion.value++
  }
}

function goPrev(): void {
  if (!isFirstQuestion.value) {
    currentQuestion.value--
  }
}

function getCorrectIndex(q: ChallengeQuestion): number {
  if (!q.correct_answer) return -1
  return q.options.findIndex(opt => opt === q.correct_answer)
}

// 새 유형 컴포넌트에서 받는 결과 핸들러
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

function onImageVoiceAnswered(result: { correct: boolean }) {
  const idx = currentQuestion.value
  questionResults.value[idx] = result.correct
}

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

function isQuestionCorrect(index: number): boolean {
  const q = questions.value[index]
  if (!q) return false
  const type = q.question_type ?? 'multiple_choice'
  if (type === 'multiple_choice') {
    return answers.value[index] === getCorrectIndex(q)
  }
  return !!questionResults.value[index]
}

function openRewardModal(): void {
  showRewardModal.value = true
}

function goToChallengeList(): void {
  router.push({ name: 'challenge-list' })
}

function goHome(): void {
  router.push({ name: 'home' })
}

onMounted(async () => {
  await challengeStore.fetchChallenge(challengeId)
  if (!challenge.value) {
    fetchError.value = true
  }
  dataReady.value = true
  if (questions.value.length > 0) {
    startTimer()
  }
})

onUnmounted(() => {
  stopTimer()
})
</script>

<template>
  <div class="min-h-screen bg-[#F8F8F8] max-w-[402px] mx-auto flex flex-col font-['Pretendard']">
    <!-- Loading -->
    <div v-if="!dataReady || challengeStore.loading" class="flex-1 flex flex-col items-center justify-center">
      <div class="w-[40px] h-[40px] border-4 border-[#FF9800] border-t-transparent rounded-full animate-spin mb-4" />
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
      <button @click="goToChallengeList" class="text-[13px] text-[#FF9800] font-semibold">챌린지 목록으로 돌아가기</button>
    </div>

    <!-- No questions -->
    <div v-else-if="questions.length === 0" class="flex-1 flex flex-col items-center justify-center px-6">
      <div class="w-[64px] h-[64px] bg-[#FFF3E0] rounded-full flex items-center justify-center mx-auto mb-4">
        <svg class="w-[32px] h-[32px] text-[#FF9800]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </div>
      <p class="text-[15px] font-semibold text-[#333] mb-1">아직 문제가 준비되지 않았어요</p>
      <p class="text-[13px] text-[#999] mb-6">곧 새로운 문제가 추가될 예정이에요.</p>
      <button @click="goToChallengeList" class="px-6 py-3 rounded-[12px] text-[14px] font-semibold text-white bg-[#FF9800] transition-all active:scale-[0.98]">챌린지 목록으로</button>
    </div>

    <template v-else>
      <!-- Header -->
      <header class="bg-white sticky top-0 z-10 border-b border-[#F5F5F5]">
        <div class="flex items-center justify-between h-[56px] px-5">
          <button @click="goToChallengeList" class="w-[32px] h-[32px] flex items-center justify-center rounded-full active:bg-[#F0F0F0] transition-colors">
            <svg class="w-[20px] h-[20px] text-[#333]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
          <h2 class="text-[14px] font-bold text-[#333] truncate max-w-[160px]">{{ challenge.title }}</h2>
          <div class="flex items-center gap-1" :class="timerSeconds < 60 ? 'text-[#F44336]' : 'text-[#999]'">
            <svg class="w-[14px] h-[14px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span class="text-[13px] font-bold">{{ formattedTime }}</span>
          </div>
        </div>
      </header>

      <!-- Progress bar -->
      <div v-if="!isCompleted" class="px-5 py-2 bg-white">
        <div class="flex items-center justify-between mb-1.5">
          <span class="text-[11px] text-[#999]">{{ currentQuestion + 1 }} / {{ totalQuestions }}</span>
          <span class="text-[11px] font-bold text-[#FF9800]">{{ Math.round(progress) }}%</span>
        </div>
        <div class="w-full h-[6px] rounded-full bg-[#F0F0F0] overflow-hidden">
          <div class="h-full rounded-full bg-[#FF9800] transition-all duration-300" :style="{ width: progress + '%' }" />
        </div>
      </div>

      <!-- QUIZ CONTENT -->
      <template v-if="!isCompleted && currentQ">
        <div class="flex-1 px-5 pt-6">
          <!-- 문제 번호 배지 -->
          <span class="inline-block bg-[#FFF3E0] text-[#FF9800] text-[11px] font-bold rounded-full px-3 py-1 mb-4">
            문제 {{ currentQuestion + 1 }}
          </span>

          <!-- 기존 객관식 -->
          <template v-if="currentType === 'multiple_choice'">
            <h2 class="text-[18px] font-bold text-[#333] leading-relaxed mb-6">{{ currentQ.content }}</h2>
            <div v-if="currentQ.image_url" class="mb-5">
              <img :src="currentQ.image_url" :alt="`문제 ${currentQuestion + 1} 이미지`" class="w-full rounded-[12px] object-cover max-h-[200px]" />
            </div>
            <div class="space-y-3">
              <button
                v-for="(option, index) in currentQ.options"
                :key="index"
                @click="selectOption(index)"
                :class="answers[currentQuestion] === index ? 'border-[#FF9800] bg-[#FFF3E0]' : 'border-[#E8E8E8] bg-white'"
                class="w-full border-2 rounded-[16px] p-4 text-left transition-all duration-200 flex items-center gap-3 active:scale-[0.98]"
              >
                <span
                  :class="answers[currentQuestion] === index ? 'bg-[#FF9800] text-white' : 'bg-[#F0F0F0] text-[#888]'"
                  class="w-[32px] h-[32px] rounded-full flex items-center justify-center text-[13px] font-bold shrink-0 transition-colors"
                >
                  {{ optionLabels[index] ?? index + 1 }}
                </span>
                <span :class="answers[currentQuestion] === index ? 'text-[#FF9800] font-semibold' : 'text-[#333]'" class="text-[14px] transition-colors">{{ option }}</span>
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

          <!-- 그림카드 음성 -->
          <ImageVoiceQuestion
            v-else-if="currentType === 'image_voice'"
            :question="currentQ"
            :challenge-id="challengeId"
            @answered="onImageVoiceAnswered"
          />
        </div>

        <!-- Bottom nav -->
        <div class="sticky bottom-0 bg-white border-t border-[#F5F5F5] px-5 py-4">
          <div class="flex items-center gap-3">
            <button
              v-if="!isFirstQuestion"
              @click="goPrev"
              class="flex-1 py-3 rounded-[12px] text-[14px] font-semibold text-[#555] bg-[#F8F8F8] transition-all active:scale-[0.98]"
            >
              이전
            </button>
            <div v-else class="flex-1" />
            <button
              @click="goNext"
              :disabled="!hasAnswer"
              :class="hasAnswer ? 'bg-[#FF9800] text-white active:scale-[0.98]' : 'bg-[#E8E8E8] text-[#B0B0B0] cursor-not-allowed'"
              class="flex-1 py-3 rounded-[12px] text-[14px] font-semibold transition-all"
            >
              {{ isLastQuestion ? '제출하기' : '다음' }}
            </button>
          </div>
        </div>
      </template>

      <!-- COMPLETION STATE -->
      <template v-else-if="isCompleted && !showRewardModal">
        <div class="flex-1 flex flex-col items-center justify-center px-6">
          <div v-if="submitting" class="mb-4">
            <div class="w-[24px] h-[24px] border-3 border-[#FF9800] border-t-transparent rounded-full animate-spin mx-auto" />
            <p class="text-[11px] text-[#999] mt-2">결과를 저장하는 중...</p>
          </div>

          <!-- Score circle -->
          <div class="relative mb-6">
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

          <h2 class="text-[20px] font-bold text-[#333] mb-2">
            {{ isPassed ? '챌린지 성공!' : score >= totalQuestions * 0.4 ? '아깝네요!' : '다시 도전해요!' }}
          </h2>
          <p class="text-[13px] text-[#888] text-center mb-1">{{ totalQuestions }}문제 중 {{ score }}개 맞혔어요</p>
          <p v-if="isPassed" class="text-[13px] text-[#4CAF50] font-semibold mb-6">보상 카드를 획득할 수 있어요!</p>
          <p v-else class="text-[13px] text-[#999] mb-6">{{ passThreshold }}개 이상 맞히면 보상을 받을 수 있어요</p>

          <!-- Answer review -->
          <CardSection class="w-full mb-6">
            <h3 class="text-[13px] font-bold text-[#333] mb-3">정답 확인</h3>
            <div class="space-y-2">
              <div v-for="(q, index) in questions" :key="q.question_id" class="flex items-center gap-3 py-2">
                <div
                  class="w-[24px] h-[24px] rounded-full flex items-center justify-center shrink-0"
                  :class="isQuestionCorrect(index) ? 'bg-[#E8F5E9]' : 'bg-[#FFEBEE]'"
                >
                  <svg v-if="isQuestionCorrect(index)" class="w-[14px] h-[14px] text-[#4CAF50]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M5 13l4 4L19 7" />
                  </svg>
                  <svg v-else class="w-[14px] h-[14px] text-[#F44336]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </div>
                <span class="text-[13px] text-[#555] flex-1 truncate">{{ index + 1 }}. {{ q.content }}</span>
              </div>
            </div>
          </CardSection>

          <!-- Action buttons -->
          <div class="w-full space-y-3">
            <button
              v-if="isPassed"
              @click="openRewardModal"
              class="w-full py-3.5 rounded-[12px] text-[14px] font-semibold text-white bg-[#FF9800] transition-all active:scale-[0.98] flex items-center justify-center gap-2"
            >
              <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
              </svg>
              보상 카드 받기
            </button>
            <button
              @click="goToChallengeList"
              :class="isPassed ? 'bg-[#F8F8F8] text-[#555]' : 'bg-[#4CAF50] text-white'"
              class="w-full py-3.5 rounded-[12px] text-[14px] font-semibold transition-all active:scale-[0.98]"
            >
              챌린지 목록으로
            </button>
          </div>
        </div>
      </template>

      <!-- REWARD MODAL -->
      <template v-if="showRewardModal">
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-black/50">
          <div class="bg-white rounded-t-[20px] w-full max-w-[402px] overflow-hidden">
            <div class="bg-gradient-to-br from-amber-400 to-orange-500 p-8 text-center text-white relative overflow-hidden">
              <div class="absolute -left-6 -top-6 w-[60px] h-[60px] bg-white/10 rounded-full" />
              <div class="absolute -right-4 -bottom-4 w-[80px] h-[80px] bg-white/10 rounded-full" />
              <div class="relative z-10">
                <div class="w-[56px] h-[56px] bg-white/20 rounded-full flex items-center justify-center mx-auto mb-4">
                  <svg class="w-[28px] h-[28px] text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                  </svg>
                </div>
                <p class="text-white/80 text-[13px] mb-1">축하합니다!</p>
                <h3 class="text-[22px] font-bold">보상 카드 획득</h3>
              </div>
            </div>
            <div class="p-6 text-center">
              <p class="text-[13px] text-[#888] mb-1">"{{ challenge.title }}" 챌린지를 통과하여</p>
              <p class="text-[15px] font-bold text-[#333] mb-6">보상 카드를 획득했어요!</p>
              <div class="flex justify-center mb-6">
                <div class="text-center">
                  <p class="text-[20px] font-bold text-[#4CAF50]">{{ score }}/{{ totalQuestions }}</p>
                  <p class="text-[11px] text-[#999] mt-0.5">정답</p>
                </div>
              </div>
              <div class="space-y-3">
                <button @click="goToChallengeList" class="w-full py-3.5 rounded-[12px] text-[14px] font-semibold text-white bg-[#4CAF50] transition-all active:scale-[0.98]">확인</button>
                <button @click="goHome" class="w-full py-3.5 rounded-[12px] text-[14px] font-semibold text-[#555] bg-[#F8F8F8] transition-all active:scale-[0.98]">홈으로 가기</button>
              </div>
            </div>
          </div>
        </div>
      </template>
    </template>
  </div>
</template>
