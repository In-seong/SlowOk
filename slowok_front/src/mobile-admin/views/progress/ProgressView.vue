<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import api from '@shared/api'
import type { ApiResponse } from '@shared/types'

interface ChallengeInfo {
  challenge_id: number
  title: string
  challenge_type: string
  sort_order: number
}

interface UserRow {
  account_id: number
  name: string
  username: string
  challenges: Record<number, string | null>
  screenings: Record<number, string | null>
}

const loading = ref(true)
const challenges = ref<ChallengeInfo[]>([])
const matrix = ref<UserRow[]>([])
const searchQuery = ref('')
const expandedUser = ref<number | null>(null)

async function fetchData() {
  loading.value = true
  try {
    const res = await api.get<ApiResponse<{ challenges: ChallengeInfo[]; screenings: { test_id: number; title: string }[]; matrix: UserRow[] }>>('/admin/progress-overview')
    if (res.data.success) {
      challenges.value = res.data.data.challenges
      matrix.value = res.data.data.matrix
    }
  } catch {
    // ignore
  } finally {
    loading.value = false
  }
}

onMounted(fetchData)

const filteredMatrix = computed(() => {
  if (!searchQuery.value.trim()) return matrix.value
  const q = searchQuery.value.toLowerCase()
  return matrix.value.filter(u =>
    u.name.toLowerCase().includes(q) || u.username.toLowerCase().includes(q)
  )
})

// 통계
const stats = computed(() => {
  const total = filteredMatrix.value.length
  let inProgress = 0
  let completed = 0

  for (const u of filteredMatrix.value) {
    const statuses = Object.values(u.challenges)
    const hasCompleted = statuses.every(s => s === 'COMPLETED')
    const hasProgress = statuses.some(s => s === 'IN_PROGRESS' || s === 'COMPLETED')

    if (hasCompleted && statuses.length > 0) {
      completed++
    } else if (hasProgress) {
      inProgress++
    }
  }

  return { total, inProgress, completed }
})

function getUserProgress(user: UserRow): { completed: number; total: number } {
  const challengeStatuses = Object.values(user.challenges)
  const assignedStatuses = challengeStatuses.filter(s => s !== null)
  const completedCount = assignedStatuses.filter(s => s === 'COMPLETED').length
  return { completed: completedCount, total: assignedStatuses.length }
}

function toggleUser(accountId: number) {
  expandedUser.value = expandedUser.value === accountId ? null : accountId
}

function statusIcon(status: string | null | undefined): string {
  if (status === 'COMPLETED') return '✅'
  if (status === 'IN_PROGRESS') return '🔄'
  if (status === 'ASSIGNED') return '⬜'
  return ''
}

function statusColor(status: string | null | undefined): string {
  if (status === 'COMPLETED') return 'text-[#4CAF50]'
  if (status === 'IN_PROGRESS') return 'text-[#2196F3]'
  return 'text-[#888]'
}
</script>

<template>
  <div class="px-4 py-4 space-y-4">
    <!-- 검색바 -->
    <div class="relative">
      <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-[#B0B0B0]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="11" cy="11" r="8" />
        <line x1="21" y1="21" x2="16.65" y2="16.65" />
      </svg>
      <input
        v-model="searchQuery"
        type="text"
        placeholder="학습자 검색"
        class="w-full bg-white border border-[#E8E8E8] rounded-[12px] pl-10 pr-4 py-3 text-[15px] text-[#333] placeholder-[#B0B0B0] focus:border-[#4CAF50] focus:outline-none transition-colors"
      />
    </div>

    <!-- 로딩 -->
    <div v-if="loading" class="text-center py-10">
      <div class="w-8 h-8 border-3 border-[#4CAF50] border-t-transparent rounded-full animate-spin mx-auto"></div>
      <p class="text-[13px] text-[#888] mt-3">불러오는 중...</p>
    </div>

    <template v-else>
      <!-- 통계 -->
      <div class="grid grid-cols-3 gap-2.5">
        <div class="bg-white rounded-[16px] shadow-sm p-4 text-center">
          <p class="text-[20px] font-bold text-[#333]">{{ stats.total }}</p>
          <p class="text-[12px] text-[#888] mt-0.5">전체</p>
        </div>
        <div class="bg-white rounded-[16px] shadow-sm p-4 text-center">
          <p class="text-[20px] font-bold text-[#2196F3]">{{ stats.inProgress }}</p>
          <p class="text-[12px] text-[#888] mt-0.5">진행 중</p>
        </div>
        <div class="bg-white rounded-[16px] shadow-sm p-4 text-center">
          <p class="text-[20px] font-bold text-[#4CAF50]">{{ stats.completed }}</p>
          <p class="text-[12px] text-[#888] mt-0.5">완료</p>
        </div>
      </div>

      <!-- 학습자별 현황 -->
      <div class="space-y-2.5">
        <div
          v-for="user in filteredMatrix"
          :key="user.account_id"
          class="bg-white rounded-[16px] shadow-sm overflow-hidden"
        >
          <!-- 헤더 (아코디언 토글) -->
          <button
            class="w-full flex items-center justify-between px-5 py-4 active:bg-[#FAFAFA] transition-colors"
            @click="toggleUser(user.account_id)"
          >
            <div class="flex items-center gap-2">
              <svg
                class="w-4 h-4 text-[#888] transition-transform"
                :class="expandedUser === user.account_id ? 'rotate-90' : ''"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              >
                <path d="M9 18l6-6-6-6" />
              </svg>
              <span class="text-[15px] font-semibold text-[#333]">{{ user.name }}</span>
            </div>
            <span class="text-[13px] text-[#888]">
              {{ getUserProgress(user).completed }}/{{ getUserProgress(user).total }}
            </span>
          </button>

          <!-- 콘텐츠 (펼침) -->
          <div v-if="expandedUser === user.account_id" class="border-t border-[#F0F0F0] px-5 py-3 space-y-2">
            <!-- 할당된 챌린지 -->
            <template v-for="ch in challenges" :key="ch.challenge_id">
              <div
                v-if="user.challenges[ch.challenge_id] !== undefined"
                class="flex items-center gap-2 py-1.5"
              >
                <span class="text-[16px]">{{ statusIcon(user.challenges[ch.challenge_id]) }}</span>
                <span class="text-[14px] flex-1" :class="statusColor(user.challenges[ch.challenge_id])">{{ ch.title }}</span>
              </div>
            </template>
            <!-- 미할당 챌린지 -->
            <template v-for="ch in challenges" :key="'unassigned-' + ch.challenge_id">
              <div
                v-if="user.challenges[ch.challenge_id] === undefined"
                class="flex items-center gap-2 py-1.5 opacity-40"
              >
                <span class="text-[14px] text-[#BBB]">{{ ch.title }}</span>
              </div>
            </template>
            <p v-if="challenges.length === 0" class="text-[13px] text-[#888] text-center py-2">
              할당된 콘텐츠가 없습니다.
            </p>
          </div>
        </div>

        <p v-if="filteredMatrix.length === 0" class="text-center text-[13px] text-[#888] py-10">
          학습자가 없습니다.
        </p>
      </div>
    </template>
  </div>
</template>
