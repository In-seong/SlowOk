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
const wrongSelections = ref<Set<string>>(new Set())
const isCorrect = ref(false)

watch(() => props.question.question_id, () => {
  selected.value = null
  wrongSelections.value = new Set()
  isCorrect.value = false
}, { immediate: true })

function selectOption(option: string) {
  if (isCorrect.value) return
  if (wrongSelections.value.has(option)) return

  selected.value = option

  if (option === props.question.correct_answer) {
    isCorrect.value = true
    emit('answered', { correct: true, selected: option })
  } else {
    wrongSelections.value.add(option)
    wrongSelections.value = new Set(wrongSelections.value)
    // 모든 보기를 틀리면 오답 처리
    const totalOptions = props.question.options?.length ?? 0
    if (wrongSelections.value.size >= totalOptions - 1) {
      isCorrect.value = true // 더 이상 시도 불가
      selected.value = props.question.correct_answer
      emit('answered', { correct: false, selected: option })
    }
  }
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

    <!-- 보기 선택 -->
    <div class="space-y-3">
      <button
        v-for="(option, idx) in question.options"
        :key="idx"
        @click="selectOption(option)"
        :disabled="isCorrect || wrongSelections.has(option)"
        :class="[
          isCorrect && option === question.correct_answer
            ? 'border-[#4CAF50] bg-[#E8F5E9] shadow-[0_3px_0_#388E3C]'
            : wrongSelections.has(option)
              ? 'border-[#F44336] bg-[#FFEBEE] shadow-[0_3px_0_#C62828] opacity-50'
              : 'border-[#E0E0E0] bg-white shadow-[0_3px_0_#E0E0E0]'
        ]"
        class="w-full border-2 rounded-2xl py-4 px-4 text-left transition-all duration-150 flex items-center gap-3 active:translate-y-[2px] active:shadow-none"
      >
        <span
          class="w-8 h-8 rounded-full flex items-center justify-center text-[13px] font-bold shrink-0"
          :class="isCorrect && option === question.correct_answer ? 'bg-[#4CAF50] text-white' : wrongSelections.has(option) ? 'bg-[#F44336] text-white' : 'bg-[#F0F0F0] text-[#888]'"
        >
          {{ idx + 1 }}
        </span>
        <span class="text-[16px] font-medium flex-1">{{ option }}</span>
        <!-- 정답 체크 -->
        <svg v-if="isCorrect && option === question.correct_answer" class="w-5 h-5 text-[#4CAF50]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M5 13l4 4L19 7" />
        </svg>
        <!-- 오답 X -->
        <svg v-else-if="wrongSelections.has(option)" class="w-5 h-5 text-[#F44336]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>
  </div>
</template>
