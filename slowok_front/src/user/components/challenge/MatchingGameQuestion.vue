<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import type { ChallengeQuestion, MatchPair } from '@shared/types'

const props = defineProps<{
  question: ChallengeQuestion
}>()

const emit = defineEmits<{
  (e: 'answered', result: { correct: number; total: number }): void
}>()

const pairs = computed<MatchPair[]>(() => props.question.match_pairs ?? [])
const total = computed(() => pairs.value.length)

// 셔플된 우측 카드 (텍스트 + 이미지 정보)
interface RightCard {
  text: string
  image: string | null
}
const shuffledRight = ref<RightCard[]>([])
const selectedLeft = ref<number | null>(null)
const selectedRight = ref<number | null>(null)
const matched = ref<Set<number>>(new Set())
const wrongPair = ref<{ left: number; right: number } | null>(null)
const correctCount = ref(0)
const isFinished = ref(false)

function shuffle<T>(arr: T[]): T[] {
  const a = [...arr]
  for (let i = a.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1))
    const tmp = a[i]!
    a[i] = a[j]!
    a[j] = tmp
  }
  return a
}

function init() {
  shuffledRight.value = shuffle(pairs.value.map(p => ({ text: p.right, image: p.right_image ?? null })))
  selectedLeft.value = null
  selectedRight.value = null
  matched.value = new Set()
  wrongPair.value = null
  correctCount.value = 0
  isFinished.value = false
}

watch(() => props.question.question_id, init, { immediate: true })

function selectLeft(idx: number) {
  if (matched.value.has(idx)) return
  selectedLeft.value = idx
  wrongPair.value = null
  tryMatch()
}

function isSameRight(pair: MatchPair | undefined, card: RightCard | undefined): boolean {
  if (!pair || !card) return false
  if (card.image) return card.image === (pair.right_image ?? null)
  return card.text === pair.right
}

function selectRight(idx: number) {
  // 이미 매칭된 right인지 확인
  const rightCard = shuffledRight.value[idx]
  const alreadyMatched = Array.from(matched.value).some(leftIdx => {
    return isSameRight(pairs.value[leftIdx], rightCard)
  })
  if (alreadyMatched) return
  selectedRight.value = idx
  wrongPair.value = null
  tryMatch()
}

function tryMatch() {
  if (selectedLeft.value === null || selectedRight.value === null) return

  const leftIdx = selectedLeft.value
  const rightIdx = selectedRight.value
  const pair = pairs.value[leftIdx]
  const rightCard = shuffledRight.value[rightIdx]

  if (isSameRight(pair, rightCard)) {
    // 정답
    matched.value.add(leftIdx)
    correctCount.value++
    selectedLeft.value = null
    selectedRight.value = null

    if (matched.value.size === total.value) {
      isFinished.value = true
      emit('answered', { correct: correctCount.value, total: total.value })
    }
  } else {
    // 오답
    wrongPair.value = { left: leftIdx, right: rightIdx }
    setTimeout(() => {
      wrongPair.value = null
      selectedLeft.value = null
      selectedRight.value = null
    }, 600)
  }
}

function isRightMatched(idx: number): boolean {
  const rightCard = shuffledRight.value[idx]
  return Array.from(matched.value).some(leftIdx => {
    return isSameRight(pairs.value[leftIdx], rightCard)
  })
}
</script>

<template>
  <div>
    <!-- 문항 내용 -->
    <p class="text-[16px] font-bold text-[#333] mb-5 leading-relaxed">{{ question.content }}</p>

    <!-- 이미지 -->
    <div v-if="question.image_url" class="mb-5">
      <img :src="question.image_url" :alt="question.content" class="w-full rounded-[12px] object-cover max-h-[180px]" />
    </div>

    <!-- 매칭 영역 -->
    <div class="flex gap-4">
      <!-- 좌측 카드 -->
      <div class="flex-1 space-y-2.5">
        <p class="text-[11px] text-[#999] font-medium mb-1 text-center">멘트</p>
        <button
          v-for="(pair, idx) in pairs"
          :key="'left-' + idx"
          @click="selectLeft(idx)"
          :disabled="matched.has(idx)"
          :class="[
            'w-full py-3 px-3 rounded-[12px] text-[13px] font-medium border-2 transition-all text-center',
            matched.has(idx)
              ? 'bg-[#E8F5E9] border-[#4CAF50] text-[#4CAF50] opacity-70'
              : wrongPair?.left === idx
                ? 'border-[#F44336] bg-[#FFEBEE] text-[#F44336] animate-shake'
                : selectedLeft === idx
                  ? 'border-[#FF9800] bg-[#FFF3E0] text-[#FF9800]'
                  : 'border-[#E8E8E8] bg-white text-[#333] active:scale-[0.97]'
          ]"
        >
          {{ pair.left }}
        </button>
      </div>

      <!-- 우측 카드 -->
      <div class="flex-1 space-y-2.5">
        <p class="text-[11px] text-[#999] font-medium mb-1 text-center">답</p>
        <button
          v-for="(card, idx) in shuffledRight"
          :key="'right-' + idx"
          @click="selectRight(idx)"
          :disabled="isRightMatched(idx)"
          :class="[
            'w-full rounded-[12px] border-2 transition-all flex items-center justify-center',
            card.image ? 'p-2' : 'py-3 px-3 text-[13px] font-medium text-center',
            isRightMatched(idx)
              ? 'bg-[#E8F5E9] border-[#4CAF50] text-[#4CAF50] opacity-70'
              : wrongPair?.right === idx
                ? 'border-[#F44336] bg-[#FFEBEE] text-[#F44336] animate-shake'
                : selectedRight === idx
                  ? 'border-[#FF9800] bg-[#FFF3E0] text-[#FF9800]'
                  : 'border-[#E8E8E8] bg-white text-[#333] active:scale-[0.97]'
          ]"
        >
          <img v-if="card.image" :src="card.image" class="w-12 h-12 rounded-[8px] object-cover" />
          <span v-else>{{ card.text }}</span>
        </button>
      </div>
    </div>

    <!-- 진행 상태 -->
    <div class="mt-4 text-center">
      <span class="text-[12px] text-[#888]">{{ matched.size }} / {{ total }} 매칭 완료</span>
    </div>

    <!-- 완료 메시지 -->
    <div v-if="isFinished" class="mt-4 bg-[#E8F5E9] rounded-[12px] p-4 text-center">
      <p class="text-[14px] font-bold text-[#4CAF50]">모든 매칭 완료!</p>
    </div>
  </div>
</template>

<style scoped>
@keyframes shake {
  0%, 100% { transform: translateX(0); }
  25% { transform: translateX(-4px); }
  75% { transform: translateX(4px); }
}
.animate-shake {
  animation: shake 0.3s ease-in-out;
}
</style>
