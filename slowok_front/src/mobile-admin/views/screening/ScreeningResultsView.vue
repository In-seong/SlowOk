<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@shared/api'
import type { ApiResponse, ScreeningResult } from '@shared/types'
import { useToastStore } from '@shared/stores/toastStore'
import BottomSheet from '../../components/BottomSheet.vue'

const router = useRouter()
const toast = useToastStore()

interface ScreeningResultWithProfile extends ScreeningResult {
  profile?: { profile_id: number; name: string; phone: string | null; email: string | null; user_type: string | null } | null
}

const results = ref<ScreeningResultWithProfile[]>([])
const loading = ref(true)
const filterLevel = ref('')
const filterUser = ref('')

const filteredResults = computed(() => {
  let filtered = results.value
  if (filterLevel.value) filtered = filtered.filter(r => r.level === filterLevel.value)
  if (filterUser.value) {
    const q = filterUser.value.toLowerCase()
    filtered = filtered.filter(r => r.profile?.name?.toLowerCase().includes(q))
  }
  return filtered
})

const levels = computed(() => {
  const set = new Set(results.value.map(r => r.level).filter(Boolean))
  return Array.from(set) as string[]
})

watch([filterLevel, filterUser], () => { /* reset handled by computed */ })

async function fetchResults() {
  loading.value = true
  try {
    const res = await api.get<ApiResponse<ScreeningResultWithProfile[]>>('/admin/screening-results')
    if (res.data.success) results.value = res.data.data
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    toast.error(err.response?.data?.message || '데이터를 불러오지 못했습니다.')
  } finally {
    loading.value = false
  }
}

function levelColor(level: string | null): string {
  switch (level) {
    case '우수': return 'text-[#4CAF50] bg-green-50'
    case '양호': return 'text-[#2196F3] bg-blue-50'
    case '보통': return 'text-[#FF9800] bg-orange-50'
    case '주의': return 'text-[#FF4444] bg-red-50'
    default: return 'text-[#888] bg-gray-100'
  }
}

function formatDate(d: string | null | undefined): string {
  if (!d) return '-'
  return d.split('T')[0] || d.split(' ')[0] || d
}

// 상세 바텀시트
const showDetail = ref(false)
const detailResult = ref<ScreeningResultWithProfile | null>(null)

function openDetail(r: ScreeningResultWithProfile) {
  detailResult.value = r
  showDetail.value = true
}

onMounted(fetchResults)
</script>

<template>
  <div class="min-h-screen bg-[#FAFAFA]">
    <header class="sticky top-0 z-40 bg-white border-b border-[#E8E8E8] h-[56px] flex items-center px-4">
      <button @click="router.back()" class="w-10 h-10 flex items-center justify-center">
        <svg class="w-5 h-5 text-[#333]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
      </button>
      <h1 class="flex-1 text-center text-[16px] font-bold text-[#333]">진단 결과</h1>
      <div class="w-10" />
    </header>

    <!-- 검색/필터 -->
    <div class="sticky top-[56px] z-30 bg-[#FAFAFA] px-4 pt-3 pb-2 space-y-2">
      <input v-model="filterUser" type="text" placeholder="학습자 검색" class="w-full bg-white border border-[#E8E8E8] rounded-[12px] px-4 py-3 text-[14px] focus:outline-none focus:border-[#4CAF50]" />
      <div class="flex gap-2 overflow-x-auto">
        <button @click="filterLevel = ''" :class="!filterLevel ? 'bg-[#4CAF50] text-white' : 'bg-white text-[#666] border border-[#E8E8E8]'" class="shrink-0 px-3 py-1.5 rounded-[10px] text-[13px] font-medium">전체</button>
        <button v-for="l in levels" :key="l" @click="filterLevel = l" :class="filterLevel === l ? 'bg-[#4CAF50] text-white' : 'bg-white text-[#666] border border-[#E8E8E8]'" class="shrink-0 px-3 py-1.5 rounded-[10px] text-[13px] font-medium">{{ l }}</button>
      </div>
    </div>

    <div v-if="loading" class="flex justify-center py-20">
      <div class="w-8 h-8 border-3 border-[#4CAF50] border-t-transparent rounded-full animate-spin" />
    </div>

    <div v-else class="px-4 pb-4 space-y-3">
      <div v-if="filteredResults.length === 0" class="bg-white rounded-[16px] shadow-sm border border-[#E8E8E8] p-8 text-center">
        <p class="text-[14px] text-[#888]">진단 결과가 없습니다.</p>
      </div>

      <div
        v-for="r in filteredResults"
        :key="r.result_id"
        @click="openDetail(r)"
        class="bg-white rounded-[16px] shadow-sm border border-[#E8E8E8] p-4 active:bg-[#FAFAFA]"
      >
        <div class="flex items-start justify-between">
          <div>
            <p class="text-[15px] font-semibold text-[#333]">{{ r.profile?.name || '-' }}</p>
            <p class="text-[13px] text-[#888] mt-0.5">{{ r.test?.title || '-' }}</p>
          </div>
          <div class="text-right">
            <p class="text-[15px] font-bold text-[#333]">{{ r.score }}점</p>
            <span v-if="r.level" class="inline-block text-[11px] font-medium px-2 py-0.5 rounded-[6px] mt-0.5" :class="levelColor(r.level)">{{ r.level }}</span>
          </div>
        </div>
        <p class="text-[11px] text-[#BBB] mt-2">{{ formatDate(r.created_at) }}</p>
      </div>

      <p class="text-center text-[12px] text-[#888] pt-2">총 {{ filteredResults.length }}건</p>
    </div>

    <!-- 상세 바텀시트 -->
    <BottomSheet v-model="showDetail" :title="detailResult?.profile?.name ? `${detailResult.profile.name} 진단 결과` : '진단 결과'" max-height="85vh">
      <div v-if="detailResult" class="space-y-4 pb-4">
        <div class="bg-[#F8F8F8] rounded-[12px] p-4 text-center">
          <p class="text-[13px] text-[#888]">{{ detailResult.test?.title }}</p>
          <p class="text-[28px] font-bold text-[#333] mt-1">{{ detailResult.score }}점</p>
          <span v-if="detailResult.level" class="inline-block text-[13px] font-medium px-3 py-1 rounded-[8px] mt-1" :class="levelColor(detailResult.level)">{{ detailResult.level }}</span>
        </div>

        <!-- 하위영역 -->
        <div v-if="detailResult.sub_scores && Object.keys(detailResult.sub_scores).length > 0">
          <p class="text-[13px] font-semibold text-[#333] mb-2">하위영역 점수</p>
          <div class="space-y-2">
            <div v-for="(score, domain) in detailResult.sub_scores" :key="String(domain)" class="flex items-center justify-between bg-[#F8F8F8] rounded-[10px] px-4 py-2.5">
              <span class="text-[14px] text-[#333]">{{ domain }}</span>
              <span class="text-[14px] font-semibold text-[#4CAF50]">{{ score }}점</span>
            </div>
          </div>
        </div>

        <p class="text-[12px] text-[#888]">완료일: {{ formatDate(detailResult.created_at) }}</p>
      </div>
    </BottomSheet>
  </div>
</template>
