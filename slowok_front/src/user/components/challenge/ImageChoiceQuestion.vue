<script setup lang="ts">
import { ref, watch } from 'vue'
import type { ChallengeQuestion } from '@shared/types'

const props = defineProps<{
  question: ChallengeQuestion
}>()

const emit = defineEmits<{
  (e: 'answered', result: { correct: boolean; selected: string }): void
}>()

const selected = ref<string | null>(null)
const showResult = ref(false)
const isCorrect = ref(false)

watch(() => props.question.question_id, () => {
  selected.value = null
  showResult.value = false
  isCorrect.value = false
}, { immediate: true })

function selectOption(option: string) {
  if (showResult.value) return
  selected.value = option
  showResult.value = true
  isCorrect.value = option === props.question.correct_answer
  emit('answered', { correct: isCorrect.value, selected: option })
}

function getOptionClass(option: string): string {
  if (!showResult.value) {
    return 'border-[#E8E8E8] bg-white text-[#333]'
  }
  if (option === props.question.correct_answer) {
    return 'border-[#4CAF50] bg-[#E8F5E9] text-[#4CAF50]'
  }
  if (option === selected.value) {
    return 'border-[#F44336] bg-[#FFEBEE] text-[#F44336]'
  }
  return 'border-[#E8E8E8] bg-white text-[#999] opacity-50'
}
</script>

<template>
  <div>
    <!-- 문항 내용 -->
    <p class="text-[16px] font-bold text-[#333] mb-4 leading-relaxed">{{ question.content }}</p>

    <!-- 이미지 (필수) -->
    <div v-if="question.image_url" class="mb-5">
      <img
        :src="question.image_url"
        :alt="question.content"
        class="w-full rounded-[16px] object-cover max-h-[240px] border border-[#E8E8E8]"
      />
    </div>

    <!-- 보기 선택 -->
    <div class="space-y-2.5">
      <button
        v-for="(option, idx) in question.options"
        :key="idx"
        @click="selectOption(option)"
        :disabled="showResult"
        :class="getOptionClass(option)"
        class="w-full border-2 rounded-[14px] py-3.5 px-4 text-left transition-all duration-200 flex items-center gap-3 active:scale-[0.98]"
      >
        <span
          class="w-7 h-7 rounded-full flex items-center justify-center text-[12px] font-bold shrink-0"
          :class="showResult && option === question.correct_answer ? 'bg-[#4CAF50] text-white' : showResult && option === selected ? 'bg-[#F44336] text-white' : 'bg-[#F0F0F0] text-[#888]'"
        >
          {{ idx + 1 }}
        </span>
        <span class="text-[14px] font-medium">{{ option }}</span>
        <!-- 정답/오답 아이콘 -->
        <svg v-if="showResult && option === question.correct_answer" class="w-5 h-5 ml-auto text-[#4CAF50]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M5 13l4 4L19 7" />
        </svg>
        <svg v-else-if="showResult && option === selected && !isCorrect" class="w-5 h-5 ml-auto text-[#F44336]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>

    <!-- 결과 피드백 -->
    <div v-if="showResult" class="mt-4 rounded-[12px] p-3.5 text-center" :class="isCorrect ? 'bg-[#E8F5E9]' : 'bg-[#FFEBEE]'">
      <p class="text-[14px] font-bold" :class="isCorrect ? 'text-[#4CAF50]' : 'text-[#F44336]'">
        {{ isCorrect ? '정답입니다!' : '틀렸습니다' }}
      </p>
      <p v-if="!isCorrect" class="text-[12px] text-[#888] mt-1">정답: {{ question.correct_answer }}</p>
    </div>
  </div>
</template>
