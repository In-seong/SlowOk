<script setup lang="ts">
import { ref, watch } from 'vue'
import type { ChallengeQuestion } from '@shared/types'
import { playWrongSound } from '@shared/utils/soundEffects'

const props = defineProps<{
  question: ChallengeQuestion
}>()

const emit = defineEmits<{
  (e: 'answered', result: { correct: boolean; selected: string }): void
}>()

const selected = ref<string | null>(null)
const isCorrect = ref(false)
const wrongShake = ref(false)
const attemptCount = ref(0)

watch(() => props.question.question_id, () => {
  selected.value = null
  isCorrect.value = false
  wrongShake.value = false
  attemptCount.value = 0
}, { immediate: true })

function selectOption(option: string) {
  if (isCorrect.value || wrongShake.value) return

  selected.value = option

  if (option === props.question.correct_answer) {
    isCorrect.value = true
    emit('answered', { correct: attemptCount.value === 0, selected: option })
  } else {
    attemptCount.value++
    wrongShake.value = true
    playWrongSound()

    setTimeout(() => {
      selected.value = null
      wrongShake.value = false
    }, 500)
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
        :disabled="isCorrect || wrongShake"
        :class="[
          isCorrect && option === question.correct_answer
            ? 'border-[#4CAF50] bg-[#E8F5E9] shadow-[0_3px_0_#388E3C]'
            : selected === option && wrongShake
              ? 'border-[#F44336] bg-[#FFEBEE] shadow-[0_3px_0_#C62828] animate-shake'
              : 'border-[#E0E0E0] bg-white shadow-[0_3px_0_#E0E0E0]'
        ]"
        class="w-full border-2 rounded-2xl py-4 px-4 text-left transition-all duration-150 flex items-center gap-3 active:translate-y-[2px] active:shadow-none"
      >
        <span
          class="w-8 h-8 rounded-full flex items-center justify-center text-[13px] font-bold shrink-0"
          :class="isCorrect && option === question.correct_answer ? 'bg-[#4CAF50] text-white' : selected === option && wrongShake ? 'bg-[#F44336] text-white' : 'bg-[#F0F0F0] text-[#888]'"
        >
          {{ idx + 1 }}
        </span>
        <span class="text-[16px] font-medium flex-1">{{ option }}</span>
      </button>
    </div>
  </div>
</template>

<style scoped>
@keyframes shake {
  0%, 100% { transform: translateX(0); }
  20% { transform: translateX(-6px); }
  40% { transform: translateX(6px); }
  60% { transform: translateX(-4px); }
  80% { transform: translateX(4px); }
}
.animate-shake {
  animation: shake 0.4s ease-in-out;
}
</style>
