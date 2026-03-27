<script setup lang="ts">
import { computed } from 'vue'
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

// Group challenges by category
interface CategoryGroup {
  name: string
  challenges: Challenge[]
  startIndex: number
}

const categoryGroups = computed<CategoryGroup[]>(() => {
  const groups: CategoryGroup[] = []
  let globalIndex = 0

  for (const challenge of props.challenges) {
    const catName = challenge.category?.name ?? '기타'
    const lastGroup = groups[groups.length - 1]

    if (lastGroup && lastGroup.name === catName) {
      lastGroup.challenges.push(challenge)
    } else {
      groups.push({
        name: catName,
        challenges: [challenge],
        startIndex: globalIndex,
      })
    }
    globalIndex++
  }

  return groups
})

// SVG connector path between two nodes
function getConnectorPath(fromIndex: number, toIndex: number): string {
  const fromPos = positions[fromIndex % positions.length]
  const toPos = positions[toIndex % positions.length]

  const posToX: Record<string, number> = { left: 70, center: 180, right: 290 }
  const x1 = posToX[fromPos ?? 'center'] ?? 180
  const x2 = posToX[toPos ?? 'center'] ?? 180

  return `M ${x1} 0 C ${x1} 40, ${x2} 40, ${x2} 80`
}
</script>

<template>
  <div class="w-full">
    <div
      v-for="group in categoryGroups"
      :key="group.name + group.startIndex"
      class="mb-6"
    >
      <!-- Category Header -->
      <div class="flex items-center justify-center mb-5">
        <div class="bg-[#4CAF50] text-white text-[12px] font-bold px-4 py-1.5 rounded-full shadow-[0_2px_0_#388E3C]">
          {{ group.name }}
        </div>
      </div>

      <!-- Nodes -->
      <div class="relative">
        <div
          v-for="(challenge, idx) in group.challenges"
          :key="challenge.challenge_id"
          class="relative"
        >
          <!-- SVG Connector (dotted line between nodes) -->
          <svg
            v-if="idx > 0"
            class="w-full absolute -top-10 left-0"
            height="80"
            viewBox="0 0 360 80"
            preserveAspectRatio="none"
            style="z-index: 0; pointer-events: none;"
          >
            <path
              :d="getConnectorPath(group.startIndex + idx - 1, group.startIndex + idx)"
              fill="none"
              :stroke="getStatus(group.startIndex + idx - 1) === 'completed' ? '#4CAF50' : '#E0E0E0'"
              stroke-width="3"
              stroke-dasharray="8 6"
              stroke-linecap="round"
            />
          </svg>

          <!-- Node row -->
          <div
            class="flex w-full relative z-10"
            :class="getNodePosition(group.startIndex + idx)"
            :style="idx > 0 ? 'margin-top: 16px;' : ''"
          >
            <LevelNode
              :challenge="challenge"
              :status="getStatus(group.startIndex + idx)"
              :stars="getStars(challenge)"
              :retry-blocked="isRetryBlocked(challenge)"
              @play="emit('play', $event)"
            />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
