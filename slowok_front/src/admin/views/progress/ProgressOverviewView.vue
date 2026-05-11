<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import api from '@shared/api'
import type { ApiResponse } from '@shared/types'

interface ChallengeInfo { challenge_id: number; title: string; challenge_type: string; sort_order: number }
interface ScreeningInfo { test_id: number; title: string }
interface UserRow {
  account_id: number
  name: string
  username: string
  challenges: Record<number, string | null>
  screenings: Record<number, string | null>
}

const loading = ref(true)
const challenges = ref<ChallengeInfo[]>([])
const screenings = ref<ScreeningInfo[]>([])
const matrix = ref<UserRow[]>([])
const searchQuery = ref('')
const viewMode = ref<'challenge' | 'screening'>('challenge')

async function fetchData() {
  loading.value = true
  try {
    const res = await api.get<ApiResponse<{ challenges: ChallengeInfo[]; screenings: ScreeningInfo[]; matrix: UserRow[] }>>('/admin/progress-overview')
    if (res.data.success) {
      challenges.value = res.data.data.challenges
      screenings.value = res.data.data.screenings
      matrix.value = res.data.data.matrix
    }
  } catch { /* ignore */ }
  finally { loading.value = false }
}

onMounted(fetchData)

// ========== 주차 탭 ==========
const activeWeek = ref('')

function weekOrder(type: string): number {
  const m = type.match(/(\d+)주차/)
  return m?.[1] ? parseInt(m[1]) : 999
}

const weekTabs = computed(() => {
  const types = new Set(challenges.value.map(c => c.challenge_type).filter(Boolean))
  return Array.from(types).sort((a, b) => weekOrder(a) - weekOrder(b))
})

// 첫 로드 시 1주차 자동 선택
watch(weekTabs, (tabs) => {
  if (tabs.length > 0 && !activeWeek.value) {
    activeWeek.value = tabs[0]!
  }
})

// 현재 주차의 챌린지만
const weekChallenges = computed(() => {
  return challenges.value
    .filter(c => c.challenge_type === activeWeek.value)
    .sort((a, b) => (a.sort_order || 999) - (b.sort_order || 999))
})

const filteredMatrix = computed(() => {
  if (!searchQuery.value.trim()) return matrix.value
  const q = searchQuery.value.toLowerCase()
  return matrix.value.filter(u => u.name.toLowerCase().includes(q) || u.username.toLowerCase().includes(q))
})

// 사용자별 현재 주차 완료율
function getUserWeekProgress(u: UserRow): { completed: number; total: number } {
  const items = viewMode.value === 'challenge' ? weekChallenges.value : screenings.value
  let completed = 0
  let total = items.length
  for (const item of items) {
    const id = viewMode.value === 'challenge' ? (item as ChallengeInfo).challenge_id : (item as ScreeningInfo).test_id
    const data = viewMode.value === 'challenge' ? u.challenges : u.screenings
    if (data[id] === 'COMPLETED') completed++
  }
  return { completed, total }
}

function statusIcon(status: string | null | undefined): string {
  if (status === 'COMPLETED') return '✅'
  if (status === 'IN_PROGRESS') return '🔄'
  if (status === 'ASSIGNED') return '📋'
  return '—'
}

function statusClass(status: string | null | undefined): string {
  if (status === 'COMPLETED') return 'bg-green-50'
  if (status === 'IN_PROGRESS') return 'bg-blue-50'
  if (status === 'ASSIGNED') return 'bg-yellow-50'
  return ''
}

function statusLabel(status: string | null | undefined): string {
  if (status === 'COMPLETED') return '완료'
  if (status === 'IN_PROGRESS') return '진행중'
  if (status === 'ASSIGNED') return '할당됨'
  return '미할당'
}

// 주차별 통계
const stats = computed(() => {
  const users = filteredMatrix.value
  if (users.length === 0) return null

  const items = viewMode.value === 'challenge' ? weekChallenges.value : screenings.value
  const itemCount = items.length

  let completedCells = 0
  let assignedCells = 0

  for (const u of users) {
    for (const item of items) {
      const id = viewMode.value === 'challenge' ? (item as ChallengeInfo).challenge_id : (item as ScreeningInfo).test_id
      const data = viewMode.value === 'challenge' ? u.challenges : u.screenings
      const s = data[id]
      if (s === 'COMPLETED') completedCells++
      if (s) assignedCells++
    }
  }

  return {
    users: users.length,
    items: itemCount,
    completionRate: assignedCells > 0 ? Math.round((completedCells / assignedCells) * 100) : 0,
    assignedCells,
    completedCells,
  }
})
</script>

<template>
  <div class="p-6">
    <div class="max-w-[1200px] mx-auto">
      <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-3">
          <h2 class="text-[16px] font-bold text-[#333]">진행 현황</h2>
          <!-- 모드 전환 -->
          <div class="flex gap-1">
            <button
              @click="viewMode = 'challenge'"
              :class="viewMode === 'challenge' ? 'bg-[#4CAF50] text-white' : 'bg-[#F0F0F0] text-[#666]'"
              class="px-3 py-1.5 rounded-[8px] text-[12px] font-medium transition-colors"
            >챌린지</button>
            <button
              @click="viewMode = 'screening'"
              :class="viewMode === 'screening' ? 'bg-[#4CAF50] text-white' : 'bg-[#F0F0F0] text-[#666]'"
              class="px-3 py-1.5 rounded-[8px] text-[12px] font-medium transition-colors"
            >진단</button>
          </div>
          <!-- 검색 -->
          <div class="relative">
            <input v-model="searchQuery" type="text" placeholder="사용자 검색..." class="bg-white border border-[#E8E8E8] rounded-[10px] pl-8 pr-3 py-1.5 text-[12px] w-[160px] focus:border-[#4CAF50] focus:outline-none" />
            <svg class="w-3.5 h-3.5 text-[#999] absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8" /><path d="m21 21-4.3-4.3" /></svg>
          </div>
        </div>
        <!-- 범례 -->
        <div class="flex items-center gap-3 text-[11px]">
          <span class="flex items-center gap-1">✅ 완료</span>
          <span class="flex items-center gap-1">🔄 진행중</span>
          <span class="flex items-center gap-1">📋 할당됨</span>
          <span class="flex items-center gap-1 text-[#CCC]">— 미할당</span>
        </div>
      </div>

      <!-- 주차 탭 (챌린지 모드에서만) -->
      <div v-if="viewMode === 'challenge' && weekTabs.length > 0" class="flex items-center gap-1.5 mb-4 overflow-x-auto pb-1">
        <button
          v-for="week in weekTabs"
          :key="week"
          @click="activeWeek = week"
          class="shrink-0 px-4 py-2 rounded-[10px] text-[13px] font-medium transition-all"
          :class="activeWeek === week ? 'bg-[#4CAF50] text-white' : 'bg-white border border-[#E8E8E8] text-[#555] hover:bg-[#F0F0F0]'"
        >
          {{ week }}
        </button>
      </div>

      <!-- 통계 카드 -->
      <div v-if="stats && !loading" class="flex gap-4 mb-4">
        <div class="bg-white rounded-[12px] border border-[#E8E8E8] px-4 py-3">
          <p class="text-[11px] text-[#999]">사용자</p>
          <p class="text-[18px] font-bold text-[#333]">{{ stats.users }}명</p>
        </div>
        <div class="bg-white rounded-[12px] border border-[#E8E8E8] px-4 py-3">
          <p class="text-[11px] text-[#999]">{{ viewMode === 'challenge' ? activeWeek + ' 챌린지' : '진단' }}</p>
          <p class="text-[18px] font-bold text-[#333]">{{ stats.items }}개</p>
        </div>
        <div class="bg-white rounded-[12px] border border-[#E8E8E8] px-4 py-3">
          <p class="text-[11px] text-[#999]">완료율</p>
          <p class="text-[18px] font-bold" :class="stats.completionRate >= 70 ? 'text-[#4CAF50]' : stats.completionRate >= 40 ? 'text-[#FF9800]' : 'text-[#F44336]'">{{ stats.completionRate }}%</p>
        </div>
        <div class="bg-white rounded-[12px] border border-[#E8E8E8] px-4 py-3">
          <p class="text-[11px] text-[#999]">완료/할당</p>
          <p class="text-[14px] font-bold text-[#555]">{{ stats.completedCells }} / {{ stats.assignedCells }}</p>
        </div>
      </div>

      <!-- 로딩 -->
      <div v-if="loading" class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] p-8 text-center">
        <p class="text-[#888]">불러오는 중...</p>
      </div>

      <!-- 매트릭스 테이블 -->
      <div v-else class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-[12px]">
            <thead>
              <tr class="bg-[#FAFAFA] border-b border-[#E8E8E8]">
                <th class="sticky left-0 bg-[#FAFAFA] z-10 px-3 py-2 text-left font-semibold text-[#555] min-w-[120px]">사용자</th>
                <template v-if="viewMode === 'challenge'">
                  <th
                    v-for="c in weekChallenges"
                    :key="c.challenge_id"
                    class="px-2 py-2 text-center font-medium text-[#555] min-w-[80px]"
                  >
                    <div class="text-[11px] truncate max-w-[100px]" :title="c.title">{{ c.title }}</div>
                  </th>
                </template>
                <template v-else>
                  <th
                    v-for="s in screenings"
                    :key="s.test_id"
                    class="px-2 py-2 text-center font-medium text-[#555] min-w-[80px]"
                  >
                    <div class="text-[11px] truncate max-w-[80px]" :title="s.title">{{ s.title }}</div>
                  </th>
                </template>
                <th class="px-3 py-2 text-center font-semibold text-[#555] min-w-[60px]">완료율</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="filteredMatrix.length === 0">
                <td :colspan="2 + (viewMode === 'challenge' ? weekChallenges.length : screenings.length)" class="px-4 py-8 text-center text-[#888]">
                  {{ searchQuery ? '검색 결과가 없습니다.' : '데이터가 없습니다.' }}
                </td>
              </tr>
              <tr
                v-for="u in filteredMatrix"
                :key="u.account_id"
                class="border-b border-[#F0F0F0] hover:bg-[#FAFAFA]"
              >
                <td class="sticky left-0 bg-white z-10 px-3 py-2 font-medium text-[#333]">
                  <div>{{ u.name }}</div>
                  <div class="text-[10px] text-[#999]">{{ u.username }}</div>
                </td>
                <template v-if="viewMode === 'challenge'">
                  <td
                    v-for="c in weekChallenges"
                    :key="c.challenge_id"
                    :class="statusClass(u.challenges[c.challenge_id])"
                    class="px-2 py-2 text-center"
                    :title="statusLabel(u.challenges[c.challenge_id])"
                  >
                    {{ statusIcon(u.challenges[c.challenge_id]) }}
                  </td>
                </template>
                <template v-else>
                  <td
                    v-for="s in screenings"
                    :key="s.test_id"
                    :class="statusClass(u.screenings[s.test_id])"
                    class="px-2 py-2 text-center"
                    :title="statusLabel(u.screenings[s.test_id])"
                  >
                    {{ statusIcon(u.screenings[s.test_id]) }}
                  </td>
                </template>
                <!-- 완료율 -->
                <td class="px-3 py-2 text-center">
                  <span
                    class="text-[12px] font-bold"
                    :class="getUserWeekProgress(u).completed === getUserWeekProgress(u).total && getUserWeekProgress(u).total > 0
                      ? 'text-[#4CAF50]'
                      : getUserWeekProgress(u).completed > 0
                        ? 'text-[#FF9800]'
                        : 'text-[#CCC]'"
                  >
                    {{ getUserWeekProgress(u).completed }}/{{ getUserWeekProgress(u).total }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>
