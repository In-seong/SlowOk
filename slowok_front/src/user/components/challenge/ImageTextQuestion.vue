<script setup lang="ts">
import { ref, watch } from 'vue'
import type { ChallengeQuestion } from '@shared/types'

const props = defineProps<{
  question: ChallengeQuestion
}>()

const emit = defineEmits<{
  (e: 'answered', result: { correct: boolean; userInput: string }): void
}>()

const userInput = ref('')
const showResult = ref(false)
const isCorrect = ref(false)

watch(() => props.question.question_id, () => {
  userInput.value = ''
  showResult.value = false
  isCorrect.value = false
}, { immediate: true })

function checkAnswer() {
  const input = userInput.value.trim()
  if (!input) return

  const answers = props.question.accept_answers ?? []
  isCorrect.value = answers.some(
    ans => ans.trim().toLowerCase() === input.toLowerCase()
  )
  showResult.value = true
  emit('answered', { correct: isCorrect.value, userInput: input })
}
</script>

<template>
  <div>
    <!-- 문항 내용 -->
    <p class="text-[16px] font-bold text-[#333] mb-4 leading-relaxed">{{ question.content }}</p>

    <!-- 이미지 -->
    <div v-if="question.image_url" class="mb-5">
      <img
        :src="question.image_url"
        :alt="question.content"
        class="w-full rounded-[16px] object-cover max-h-[240px] border border-[#E8E8E8]"
      />
    </div>

    <!-- 텍스트 입력 -->
    <div class="mb-4">
      <input
        v-model="userInput"
        type="text"
        placeholder="답을 입력하세요"
        :disabled="showResult"
        @keydown.enter="checkAnswer"
        class="w-full bg-[#F8F8F8] border-2 border-[#E8E8E8] rounded-[14px] px-4 py-3.5 text-[15px] text-center font-medium focus:border-[#FF9800] focus:outline-none transition-colors disabled:opacity-60"
        :class="showResult ? (isCorrect ? 'border-[#4CAF50] bg-[#E8F5E9]' : 'border-[#F44336] bg-[#FFEBEE]') : ''"
      />
    </div>

    <!-- 제출 버튼 -->
    <button
      v-if="!showResult"
      @click="checkAnswer"
      :disabled="!userInput.trim()"
      class="w-full py-3.5 rounded-[14px] text-[15px] font-bold transition-all active:scale-[0.98]"
      :class="userInput.trim() ? 'bg-[#FF9800] text-white' : 'bg-[#E8E8E8] text-[#BBB] cursor-not-allowed'"
    >
      확인
    </button>

    <!-- 결과 피드백 -->
    <div v-if="showResult" class="mt-3 rounded-[12px] p-3.5 text-center" :class="isCorrect ? 'bg-[#E8F5E9]' : 'bg-[#FFEBEE]'">
      <p class="text-[14px] font-bold" :class="isCorrect ? 'text-[#4CAF50]' : 'text-[#F44336]'">
        {{ isCorrect ? '정답입니다!' : '틀렸습니다' }}
      </p>
      <p v-if="!isCorrect && question.accept_answers?.length" class="text-[12px] text-[#888] mt-1">
        정답: {{ question.accept_answers.join(', ') }}
      </p>
    </div>
  </div>
</template>
