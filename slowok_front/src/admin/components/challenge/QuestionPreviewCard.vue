<script setup lang="ts">
import { computed } from 'vue'
import type { ChallengeQuestion } from '@shared/types'
import ImageChoiceQuestion from '@user/components/challenge/ImageChoiceQuestion.vue'
import ImageTextQuestion from '@user/components/challenge/ImageTextQuestion.vue'
import ImageVoiceQuestion from '@user/components/challenge/ImageVoiceQuestion.vue'
import MatchingGameQuestion from '@user/components/challenge/MatchingGameQuestion.vue'

const props = defineProps<{
  question: ChallengeQuestion
  index: number
  total: number
  selected: boolean
}>()

const emit = defineEmits<{
  (e: 'edit'): void
  (e: 'delete'): void
  (e: 'move-up'): void
  (e: 'move-down'): void
}>()

const typeLabel = computed(() => {
  const map: Record<string, string> = {
    multiple_choice: '객관식',
    matching: '매칭게임',
    image_choice: '그림카드 택1',
    image_text: '그림카드 텍스트',
    image_voice: '그림카드 음성',
  }
  return map[props.question.question_type ?? ''] ?? '객관식'
})

const typeColor = computed(() => {
  const map: Record<string, string> = {
    multiple_choice: 'bg-blue-50 text-blue-600',
    matching: 'bg-purple-50 text-purple-600',
    image_choice: 'bg-amber-50 text-amber-600',
    image_text: 'bg-teal-50 text-teal-600',
    image_voice: 'bg-rose-50 text-rose-600',
  }
  return map[props.question.question_type ?? ''] ?? 'bg-blue-50 text-blue-600'
})

</script>

<template>
  <div
    class="relative rounded-[16px] border-2 transition-all"
    :class="selected ? 'border-[#4CAF50] shadow-[0_0_0_2px_rgba(76,175,80,0.2)]' : 'border-[#E8E8E8]'"
  >
    <!-- 편집 도구 바 -->
    <div class="flex items-center gap-1.5 px-3 py-2 bg-[#FAFAFA] rounded-t-[14px] border-b border-[#E8E8E8]">
      <span class="text-[12px] font-bold text-[#555] mr-1">#{{ question.order }}</span>
      <span :class="['px-2 py-0.5 rounded-full text-[10px] font-bold', typeColor]">{{ typeLabel }}</span>
      <div class="flex-1" />
      <button
        @click="emit('move-up')"
        :disabled="index === 0"
        class="w-7 h-7 flex items-center justify-center rounded-md hover:bg-[#E8E8E8] disabled:opacity-30 transition-colors"
        title="위로"
      >
        <svg class="w-4 h-4 text-[#555]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 15l-6-6-6 6" /></svg>
      </button>
      <button
        @click="emit('move-down')"
        :disabled="index === total - 1"
        class="w-7 h-7 flex items-center justify-center rounded-md hover:bg-[#E8E8E8] disabled:opacity-30 transition-colors"
        title="아래로"
      >
        <svg class="w-4 h-4 text-[#555]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6" /></svg>
      </button>
      <button
        @click="emit('edit')"
        class="w-7 h-7 flex items-center justify-center rounded-md hover:bg-[#E8F5E9] transition-colors"
        title="수정"
      >
        <svg class="w-4 h-4 text-[#4CAF50]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.828 2.828 0 114 4L7.5 20.5 2 22l1.5-5.5L17 3z" /></svg>
      </button>
      <button
        @click="emit('delete')"
        class="w-7 h-7 flex items-center justify-center rounded-md hover:bg-[#FFEBEE] transition-colors"
        title="삭제"
      >
        <svg class="w-4 h-4 text-[#F44336]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2" /></svg>
      </button>
    </div>

    <!-- 미리보기 (pointer-events-none으로 인터랙션 차단) -->
    <div class="p-4 pointer-events-none select-none">
      <!-- 객관식 (ChallengePlayView 인라인 렌더링과 동일) -->
      <template v-if="question.question_type === 'multiple_choice' || !question.question_type">
        <h2 class="text-[20px] font-bold text-[#333] leading-relaxed mb-4">{{ question.content }}</h2>
        <div v-if="question.image_url" class="mb-5">
          <img :src="question.image_url" :alt="question.content" class="w-full rounded-[16px] object-cover max-h-[200px]" />
        </div>
        <div class="space-y-3">
          <div
            v-for="(option, idx) in question.options"
            :key="idx"
            class="w-full border-2 border-[#E0E0E0] bg-white shadow-[0_3px_0_#E0E0E0] rounded-2xl p-4 flex items-center gap-3"
          >
            <span class="w-[32px] h-[32px] rounded-full bg-[#F0F0F0] text-[#888] flex items-center justify-center text-[13px] font-bold shrink-0">
              {{ idx + 1 }}
            </span>
            <span class="text-[16px] text-[#333]">{{ option }}</span>
            <svg v-if="option === question.correct_answer" class="w-4 h-4 text-[#4CAF50] ml-auto shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M5 13l4 4L19 7" />
            </svg>
          </div>
        </div>
      </template>

      <!-- 매칭 게임 (사용자 컴포넌트 사용) -->
      <MatchingGameQuestion
        v-else-if="question.question_type === 'matching'"
        :question="question"
      />

      <!-- 그림카드 택1 -->
      <ImageChoiceQuestion
        v-else-if="question.question_type === 'image_choice'"
        :question="question"
      />

      <!-- 그림카드 텍스트 -->
      <ImageTextQuestion
        v-else-if="question.question_type === 'image_text'"
        :question="question"
      />

      <!-- 그림카드 음성 -->
      <ImageVoiceQuestion
        v-else-if="question.question_type === 'image_voice'"
        :question="question"
        :challenge-id="question.challenge_id"
      />
    </div>
  </div>
</template>
