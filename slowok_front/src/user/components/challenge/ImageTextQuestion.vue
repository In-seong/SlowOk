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
    <p class="text-[20px] font-bold text-[#333] mb-4 leading-relaxed">{{ question.content }}</p>

    <!-- 이미지 -->
    <div v-if="question.image_url" class="mb-5">
      <img
        :src="question.image_url"
        :alt="question.content"
        class="w-full rounded-2xl object-cover max-h-[240px] border border-[#E8E8E8]"
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
        class="w-full bg-[#F8F8F8] border-2 rounded-2xl px-4 py-4 text-[18px] text-center font-medium focus:outline-none transition-colors disabled:opacity-60"
        :class="showResult
          ? (isCorrect ? 'border-[#4CAF50] bg-[#E8F5E9]' : 'border-[#F44336] bg-[#FFEBEE]')
          : 'border-[#E0E0E0] focus:border-[#4CAF50]'"
      />
    </div>

    <!-- 제출 버튼 -->
    <button
      v-if="!showResult"
      @click="checkAnswer"
      :disabled="!userInput.trim()"
      class="w-full py-4 rounded-2xl text-[16px] font-bold transition-all"
      :class="userInput.trim()
        ? 'bg-[#4CAF50] text-white shadow-[0_4px_0_#388E3C] active:shadow-[0_2px_0_#388E3C] active:translate-y-[2px]'
        : 'bg-[#E0E0E0] text-[#9E9E9E] cursor-not-allowed'"
    >
      확인
    </button>
  </div>
</template>
