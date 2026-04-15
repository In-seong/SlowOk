<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@shared/api'
import type { ApiResponse } from '@shared/types'
import SectionTitle from '@shared/components/ui/SectionTitle.vue'
import { useShortcuts } from '../composables/useShortcuts'
import BottomSheet from '../components/BottomSheet.vue'

const router = useRouter()
const { shortcuts, addShortcut, removeShortcut, resetToDefault, getAvailableItems } = useShortcuts()

const editMode = ref(false)
const showAddSheet = ref(false)

// 대시보드 데이터
interface DashboardResponse {
  total_learners: number
  total_screenings: number
  completion_rate: number
  recent_screenings: { profile_name: string; test_title: string; score: number; level: string; created_at: string }[]
}

const dashboardData = ref<DashboardResponse | null>(null)
const loading = ref(true)

async function fetchDashboard() {
  loading.value = true
  try {
    const res = await api.get<ApiResponse<DashboardResponse>>('/admin/dashboard')
    if (res.data.success) {
      dashboardData.value = res.data.data
    }
  } catch {
    // ignore
  } finally {
    loading.value = false
  }
}

onMounted(fetchDashboard)

function navigateTo(routeName: string) {
  if (editMode.value) return
  router.push({ name: routeName })
}

function toggleEditMode() {
  editMode.value = !editMode.value
  if (!editMode.value) {
    showAddSheet.value = false
  }
}

function handleAddShortcut(item: { key: string; label: string; icon: string; color: string }) {
  addShortcut(item)
  showAddSheet.value = false
}

function levelVariant(level: string): string {
  const map: Record<string, string> = {
    '우수': 'text-[#4CAF50]',
    '양호': 'text-[#2196F3]',
    '보통': 'text-[#FF9800]',
    '주의': 'text-[#FF4444]',
  }
  return map[level] || 'text-[#888]'
}
</script>

<template>
  <div class="px-4 py-5 space-y-6">
    <!-- 편집 버튼 -->
    <div class="flex justify-end">
      <button
        class="text-[13px] px-3 py-1.5 rounded-[8px] active:scale-95 transition-transform"
        :class="editMode ? 'bg-[#4CAF50] text-white' : 'bg-[#F0F0F0] text-[#555]'"
        @click="toggleEditMode"
      >
        {{ editMode ? '완료' : '편집' }}
      </button>
    </div>

    <!-- 바로가기 그리드 -->
    <div class="grid grid-cols-2 gap-3">
      <button
        v-for="item in shortcuts"
        :key="item.key"
        class="relative bg-white rounded-[16px] shadow-sm p-4 flex flex-col items-center gap-2 active:scale-95 transition-transform"
        @click="navigateTo(item.key)"
      >
        <button
          v-if="editMode && shortcuts.length > 2"
          class="absolute -top-1.5 -right-1.5 w-6 h-6 bg-[#FF4444] text-white rounded-full flex items-center justify-center text-[12px] font-bold"
          @click.stop="removeShortcut(item.key)"
        >
          &times;
        </button>
        <div class="w-[48px] h-[48px] rounded-[12px] flex items-center justify-center text-[24px]" :style="{ backgroundColor: item.color }">
          {{ item.icon }}
        </div>
        <span class="text-[13px] font-medium text-[#333]">{{ item.label }}</span>
      </button>

      <!-- 추가 버튼 (편집 모드) -->
      <button
        v-if="editMode && shortcuts.length < 8"
        class="bg-white rounded-[16px] shadow-sm p-4 flex flex-col items-center gap-2 border-2 border-dashed border-[#DDD] active:scale-95 transition-transform"
        @click="showAddSheet = true"
      >
        <div class="w-[48px] h-[48px] rounded-[12px] flex items-center justify-center text-[24px] bg-[#F0F0F0]">
          +
        </div>
        <span class="text-[13px] font-medium text-[#888]">추가</span>
      </button>
    </div>

    <!-- 편집 모드: 기본값 복원 -->
    <div v-if="editMode" class="text-center">
      <button class="text-[13px] text-[#888] underline" @click="resetToDefault">기본값 복원</button>
    </div>

    <!-- 요약 -->
    <div v-if="!editMode">
      <SectionTitle title="요약" />
      <div v-if="loading" class="bg-white rounded-[16px] shadow-sm p-5 text-center">
        <p class="text-[13px] text-[#888]">불러오는 중...</p>
      </div>
      <div v-else-if="dashboardData" class="bg-white rounded-[16px] shadow-sm p-5 flex justify-around">
        <div class="text-center">
          <p class="text-[20px] font-bold text-[#333]">{{ dashboardData.total_learners }}</p>
          <p class="text-[12px] text-[#888] mt-0.5">학습자</p>
        </div>
        <div class="w-px bg-[#E8E8E8]" />
        <div class="text-center">
          <p class="text-[20px] font-bold text-[#333]">{{ dashboardData.total_screenings }}</p>
          <p class="text-[12px] text-[#888] mt-0.5">진단 완료</p>
        </div>
        <div class="w-px bg-[#E8E8E8]" />
        <div class="text-center">
          <p class="text-[20px] font-bold text-[#333]">{{ dashboardData.completion_rate ?? 0 }}%</p>
          <p class="text-[12px] text-[#888] mt-0.5">학습 완료율</p>
        </div>
      </div>
    </div>

    <!-- 최근 진단 결과 -->
    <div v-if="!editMode && dashboardData?.recent_screenings?.length">
      <SectionTitle title="최근 진단 결과" action-label="전체보기" action-to="/screening-results" />
      <div class="bg-white rounded-[16px] shadow-sm divide-y divide-[#F0F0F0]">
        <div
          v-for="(item, idx) in dashboardData.recent_screenings.slice(0, 5)"
          :key="idx"
          class="px-5 py-3.5 flex items-center justify-between"
        >
          <div>
            <p class="text-[15px] font-semibold text-[#333]">{{ item.profile_name }}</p>
            <p class="text-[13px] text-[#888] mt-0.5">{{ item.test_title }}</p>
          </div>
          <div class="text-right">
            <p class="text-[15px] font-semibold text-[#333]">{{ item.score }}점</p>
            <p class="text-[13px] font-medium mt-0.5" :class="levelVariant(item.level)">{{ item.level }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- 바로가기 추가 바텀시트 -->
  <BottomSheet v-model="showAddSheet" title="바로가기 추가">
    <div class="space-y-1 pb-4">
      <button
        v-for="item in getAvailableItems()"
        :key="item.key"
        class="w-full flex items-center gap-3 px-4 py-3 rounded-[12px] active:bg-[#F0F0F0] transition-colors"
        @click="handleAddShortcut(item)"
      >
        <div class="w-[40px] h-[40px] rounded-[10px] flex items-center justify-center text-[20px]" :style="{ backgroundColor: item.color }">
          {{ item.icon }}
        </div>
        <span class="text-[15px] text-[#333]">{{ item.label }}</span>
      </button>
      <p v-if="getAvailableItems().length === 0" class="text-center text-[13px] text-[#888] py-4">
        추가 가능한 항목이 없습니다.
      </p>
    </div>
  </BottomSheet>
</template>
