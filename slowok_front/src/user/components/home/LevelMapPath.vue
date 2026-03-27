<script setup lang="ts">
// no computed needed
import type { Challenge } from '@shared/types'
import LevelNode from './LevelNode.vue'

const props = defineProps<{
  challenges: Challenge[]
}>()

const emit = defineEmits<{
  (e: 'play', challengeId: number): void
}>()

// S-curve positions: left → center → right → center → left ...
const positions = ['left', 'center', 'right', 'center'] as const

function getNodePosition(index: number): string {
  const pos = positions[index % positions.length]
  switch (pos) {
    case 'left': return 'justify-start pl-6'
    case 'right': return 'justify-end pr-6'
    case 'center': return 'justify-center'
    default: return 'justify-center'
  }
}

function getStatus(index: number): 'completed' | 'current' | 'locked' {
  const challenge = props.challenges[index]
  if (!challenge) return 'locked'

  if (challenge.latest_attempt?.is_passed) {
    // 재도전 불가 챌린지는 completed(잠금 느낌)로 표시
    return 'completed'
  }

  // First incomplete challenge is "current"
  const allPreviousCompleted = props.challenges
    .slice(0, index)
    .every(c => c.latest_attempt?.is_passed)

  if (index === 0 && !challenge.latest_attempt?.is_passed) return 'current'
  if (allPreviousCompleted) return 'current'

  return 'locked'
}

// 재도전 불가 + 이미 통과 → 클릭 차단
function isRetryBlocked(challenge: Challenge): boolean {
  return challenge.allow_retry === false && !!challenge.latest_attempt?.is_passed
}

function getStars(challenge: Challenge): number {
  const attempt = challenge.latest_attempt
  if (!attempt?.is_passed) return 0
  const total = (challenge as any).questions_count ?? challenge.questions?.length ?? 0
  if (total === 0) return 3
  const ratio = attempt.score / total
  if (ratio >= 1) return 3
  if (ratio >= 0.85) return 2
  return 1
}

// 카테고리 구분 없이 단순 리스트

// SVG connector path between two nodes
function getConnectorPath(fromIndex: number, toIndex: number): string {
  const fromPos = positions[fromIndex % positions.length]
  const toPos = positions[toIndex % positions.length]

  const posToX: Record<string, number> = { left: 70, center: 180, right: 290 }
  const x1 = posToX[fromPos ?? 'center'] ?? 180
  const x2 = posToX[toPos ?? 'center'] ?? 180

  return `M ${x1} 0 C ${x1} 40, ${x2} 40, ${x2} 80`
}

// 장식 아이콘들 (노드 사이 빈 공간에 배치)
const decorIcons = [
  // 거북이
  `<img src="/images/turtle.png" style="width:100%;height:100%;" />`,
  // 별
  `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/></svg>`,
  // 하트
  `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>`,
  // 별 (작은 변형)
  `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4L12 16.8l-6.2 4.5 2.4-7.4L2 9.4h7.6z"/></svg>`,
  // 왕관
  `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M5 16L3 5l5.5 5L12 4l3.5 6L21 5l-2 11H5z"/><rect x="5" y="17" width="14" height="2" rx="1"/></svg>`,
]

const decorColors = ['text-[#A5D6A7]', 'text-[#FFC107]/40', 'text-[#EF9A9A]/50', 'text-[#81C784]/60', 'text-[#FFD54F]/40']

// 첫 번째 노드: 노드 반대편에 배치
function getFirstDecorPosition(index: number): string {
  const pos = positions[index % positions.length]
  if (pos === 'left') return 'right-6'
  if (pos === 'right') return 'left-6'
  return 'right-6'
}

// 이전 노드와 현재 노드 사이의 빈쪽에 배치
function getDecorOppositePosition(index: number): string {
  const prevPos = positions[(index - 1) % positions.length]
  const curPos = positions[index % positions.length]

  // 둘 다 같은 쪽이면 반대편
  if (prevPos === 'left' && curPos === 'center') return 'right-6'
  if (prevPos === 'center' && curPos === 'right') return 'left-6'
  if (prevPos === 'right' && curPos === 'center') return 'left-6'
  if (prevPos === 'center' && curPos === 'left') return 'right-6'

  // 둘 다 한쪽에 몰려있으면 반대편
  if (prevPos === 'left' || curPos === 'left') return 'right-6'
  if (prevPos === 'right' || curPos === 'right') return 'left-6'

  return index % 2 === 0 ? 'left-6' : 'right-6'
}

function getDecorIcon(index: number): string {
  return decorIcons[index % decorIcons.length] ?? decorIcons[0]!
}

function getDecorColor(index: number): string {
  return decorColors[index % decorColors.length] ?? decorColors[0]!
}
</script>

<template>
  <div class="w-full relative">
    <div
      v-for="(challenge, idx) in props.challenges"
      :key="challenge.challenge_id"
      class="relative"
    >
      <!-- SVG Connector -->
      <svg
        v-if="idx > 0"
        class="w-full absolute -top-10 left-0"
        height="80"
        viewBox="0 0 360 80"
        preserveAspectRatio="none"
        style="z-index: 0; pointer-events: none;"
      >
        <path
          :d="getConnectorPath(idx - 1, idx)"
          fill="none"
          :stroke="getStatus(idx - 1) === 'completed' ? '#4CAF50' : '#D0D0D0'"
          :stroke-width="getStatus(idx - 1) === 'completed' ? 5 : 4"
          stroke-linecap="round"
          :stroke-opacity="getStatus(idx - 1) === 'completed' ? 1 : 0.5"
        />
      </svg>

      <!-- 장식 아이콘 -->
      <div
        class="absolute opacity-50"
        :class="[idx === 0 ? getFirstDecorPosition(idx) : getDecorOppositePosition(idx), getDecorColor(idx), idx % decorIcons.length === 0 ? 'w-12 h-12' : 'w-5 h-5']"
        style="z-index: 1; pointer-events: none; top: 30%;"
        v-html="getDecorIcon(idx)"
      />

      <!-- Node row -->
      <div
        class="flex w-full relative z-10"
        :class="getNodePosition(idx)"
        :style="idx > 0 ? 'margin-top: 16px;' : ''"
      >
        <LevelNode
          :challenge="challenge"
          :status="getStatus(idx)"
          :stars="getStars(challenge)"
          :retry-blocked="isRetryBlocked(challenge)"
          @play="emit('play', $event)"
        />
      </div>
    </div>
  </div>
</template>
