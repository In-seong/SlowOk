<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import api from '@shared/api'
import { exportApi } from '@shared/api/exportApi'
import type { ApiResponse, ScreeningResult } from '@shared/types'
import { useToastStore } from '@shared/stores/toastStore'

const toast = useToastStore()

interface ScreeningResultWithProfile extends ScreeningResult {
  profile?: {
    profile_id: number
    name: string
    phone: string | null
    email: string | null
    user_type: string | null
  } | null
}

const results = ref<ScreeningResultWithProfile[]>([])
const loading = ref(true)
const error = ref('')
const filterLevel = ref<string>('')
const filterUser = ref<string>('')

const currentPage = ref(1)
const perPage = 15

const filteredResults = computed(() => {
  let filtered = results.value
  if (filterLevel.value) {
    filtered = filtered.filter(r => r.level === filterLevel.value)
  }
  if (filterUser.value) {
    const q = filterUser.value.toLowerCase()
    filtered = filtered.filter(r =>
      r.profile?.name?.toLowerCase().includes(q) ||
      r.profile?.email?.toLowerCase().includes(q)
    )
  }
  return filtered
})

const totalPages = computed(() => Math.ceil(filteredResults.value.length / perPage))

const pagedResults = computed(() => {
  const start = (currentPage.value - 1) * perPage
  return filteredResults.value.slice(start, start + perPage)
})

// 필터 변경 시 1페이지로 리셋
watch([filterLevel, filterUser], () => { currentPage.value = 1 })

const pageNumbers = computed(() => {
  const total = totalPages.value
  const cur = currentPage.value
  const pages: (number | '...')[] = []

  if (total <= 7) {
    for (let i = 1; i <= total; i++) pages.push(i)
  } else {
    pages.push(1)
    if (cur > 3) pages.push('...')
    for (let i = Math.max(2, cur - 1); i <= Math.min(total - 1, cur + 1); i++) pages.push(i)
    if (cur < total - 2) pages.push('...')
    pages.push(total)
  }
  return pages
})

const levels = computed(() => {
  const set = new Set(results.value.map(r => r.level).filter(Boolean))
  return Array.from(set) as string[]
})

async function fetchResults() {
  loading.value = true
  error.value = ''
  try {
    const res = await api.get<ApiResponse<ScreeningResultWithProfile[]>>('/admin/screening-results')
    if (res.data.success) results.value = res.data.data
  } catch (e: any) {
    error.value = e.response?.data?.message || '데이터를 불러오지 못했습니다.'
  } finally {
    loading.value = false
  }
}

async function deleteResult(result: ScreeningResultWithProfile) {
  if (!confirm('이 진단 결과를 정말 삭제하시겠습니까?')) return
  try {
    await api.delete(`/admin/screening-results/${result.result_id}`)
    results.value = results.value.filter(r => r.result_id !== result.result_id)
  } catch (e: any) {
    toast.error(e.response?.data?.message || '삭제에 실패했습니다.')
  }
}

function formatDate(dateStr?: string): string {
  if (!dateStr) return '-'
  const d = new Date(dateStr)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')} ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`
}

function levelColor(level: string | null): string {
  if (level === '우수' || level === '상') return 'bg-[#E8F5E9] text-[#4CAF50]'
  if (level === '보통' || level === '중') return 'bg-[#FFF3E0] text-[#FF9800]'
  if (level === '노력' || level === '하') return 'bg-red-50 text-red-500'
  return 'bg-[#F5F5F5] text-[#888]'
}

const expandedResultId = ref<number | null>(null)
const domainModalResult = ref<ScreeningResultWithProfile | null>(null)
const domainModalName = ref('')

function toggleResultDetail(resultId: number) {
  expandedResultId.value = expandedResultId.value === resultId ? null : resultId
}

function openDomainModal(result: ScreeningResultWithProfile, domain: string) {
  domainModalResult.value = result
  domainModalName.value = domain
}

function closeDomainModal() {
  domainModalResult.value = null
  domainModalName.value = ''
}

function getDomainQuestions(result: ScreeningResultWithProfile, domain: string) {
  const questions = (result.test as any)?.questions ?? []
  return questions.filter((q: any) => q.sub_domain === domain)
}

const exportLoading = ref(false)

async function handleExportResults() {
  exportLoading.value = true
  try {
    await exportApi.screeningResults()
    toast.success('다운로드가 시작되었습니다.')
  } catch {
    toast.error('내보내기에 실패했습니다.')
  } finally {
    exportLoading.value = false
  }
}

onMounted(fetchResults)
</script>

<template>
  <div class="p-6">
    <div class="max-w-[1200px] mx-auto">
      <!-- 상단 -->
      <div class="flex items-center justify-between mb-4">
        <p class="text-[14px] text-[#888]">진단 검사 결과를 조회합니다.</p>
        <div class="flex items-center gap-3">
          <!-- 사용자 검색 -->
          <div class="relative">
            <input
              v-model="filterUser"
              type="text"
              placeholder="사용자 검색..."
              class="bg-white border border-[#E8E8E8] rounded-[10px] pl-8 pr-8 py-2 text-[13px] w-[180px] focus:border-[#4CAF50] focus:outline-none"
            />
            <svg class="w-4 h-4 text-[#999] absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8" /><path d="m21 21-4.3-4.3" /></svg>
            <button
              v-if="filterUser"
              @click="filterUser = ''"
              class="absolute right-2 top-1/2 -translate-y-1/2 text-[#999] hover:text-[#555]"
            >
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
          </div>
          <!-- 레벨 필터 -->
          <select
            v-model="filterLevel"
            class="bg-white border border-[#E8E8E8] rounded-[10px] px-3 py-2 text-[13px] focus:border-[#4CAF50] focus:outline-none"
          >
            <option value="">전체 레벨</option>
            <option v-for="level in levels" :key="level" :value="level">{{ level }}</option>
          </select>
          <!-- 내보내기 -->
          <button
            :disabled="exportLoading"
            @click="handleExportResults"
            class="flex items-center gap-1.5 px-4 py-2 rounded-[10px] text-[13px] font-medium bg-white border border-[#E8E8E8] text-[#555] hover:bg-[#F8F8F8] disabled:opacity-50 transition-colors"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
            {{ exportLoading ? '내보내는 중...' : 'CSV 내보내기' }}
          </button>
        </div>
      </div>

      <!-- 로딩 -->
      <div v-if="loading" class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] p-8 text-center">
        <p class="text-[#888]">진단 결과를 불러오는 중...</p>
      </div>

      <!-- 에러 -->
      <div v-else-if="error" class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] p-8 text-center">
        <p class="text-red-500 mb-3">{{ error }}</p>
        <button
          @click="fetchResults"
          class="bg-[#4CAF50] hover:bg-[#388E3C] text-white rounded-[12px] px-5 py-2.5 text-[14px] font-medium active:scale-[0.98] transition-all"
        >
          다시 시도
        </button>
      </div>

      <!-- 빈 상태 -->
      <div v-else-if="filteredResults.length === 0" class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] p-8 text-center">
        <p class="text-[#888]">{{ filterLevel ? `'${filterLevel}' 레벨의 결과가 없습니다.` : '진단 결과가 없습니다.' }}</p>
      </div>

      <!-- 테이블 -->
      <div v-else class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-[14px]">
            <thead>
              <tr class="border-b border-[#E8E8E8] bg-[#FAFAFA]">
                <th class="px-5 py-3 font-semibold text-[#555]">사용자</th>
                <th class="px-5 py-3 font-semibold text-[#555]">진단 테스트</th>
                <th class="px-5 py-3 font-semibold text-[#555]">점수</th>
                <th class="px-5 py-3 font-semibold text-[#555]">레벨</th>
                <th class="px-5 py-3 font-semibold text-[#555]">검사일</th>
                <th class="px-5 py-3 font-semibold text-[#555]">액션</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="result in pagedResults" :key="result.result_id">
              <tr class="border-b border-[#F0F0F0] hover:bg-[#FAFAFA] transition-colors">
                <td class="px-5 py-3.5">
                  <div>
                    <p class="text-[#333] font-medium">{{ result.profile?.name || '-' }}</p>
                    <p v-if="result.profile?.email" class="text-[12px] text-[#888] mt-0.5">{{ result.profile.email }}</p>
                  </div>
                </td>
                <td class="px-5 py-3.5">
                  <div class="flex items-center gap-2">
                    <span class="text-[#555]">{{ result.test?.title || '-' }}</span>
                    <span v-if="result.test?.test_type === 'LIKERT'" class="inline-block bg-[#E3F2FD] text-[#2196F3] px-1.5 py-0.5 rounded-full text-[10px] font-semibold">리커트</span>
                  </div>
                </td>
                <td class="px-5 py-3.5">
                  <span class="text-[16px] font-bold text-[#333]">{{ result.score }}</span>
                  <span class="text-[12px] text-[#888]">점</span>
                </td>
                <td class="px-5 py-3.5">
                  <span
                    :class="levelColor(result.level)"
                    class="inline-block px-2.5 py-1 rounded-full text-[12px] font-semibold"
                  >
                    {{ result.level || '-' }}
                  </span>
                </td>
                <td class="px-5 py-3.5 text-[#888] text-[13px]">{{ formatDate(result.created_at) }}</td>
                <td class="px-5 py-3.5">
                  <div class="flex items-center gap-2">
                    <button
                      v-if="result.sub_scores && Object.keys(result.sub_scores).length > 0"
                      @click="toggleResultDetail(result.result_id)"
                      class="text-[#2196F3] hover:text-[#1976D2] text-[13px] font-medium transition-colors"
                    >
                      {{ expandedResultId === result.result_id ? '접기' : '하위영역' }}
                    </button>
                    <button
                      @click="deleteResult(result)"
                      class="text-red-500 hover:text-red-700 text-[13px] font-medium transition-colors"
                    >
                      삭제
                    </button>
                  </div>
                </td>
              </tr>
              <!-- 하위영역 상세 -->
              <tr v-if="expandedResultId === result.result_id && result.sub_scores">
                <td colspan="6" class="px-5 pb-4 pt-0">
                  <div class="bg-[#F8F8F8] rounded-[12px] p-4 mt-2">
                    <!-- 레이더 차트 -->
                    <p class="text-[13px] font-semibold text-[#333] mb-3">하위영역 분석</p>
                    <div class="flex justify-center mb-4">
                      <svg viewBox="0 0 280 280" class="w-[400px] h-[400px]">
                        <!-- 그리드 -->
                        <polygon
                          v-for="level in [1,2,3,4,5]"
                          :key="'g'+level"
                          :points="Object.keys(result.sub_scores!).map((_,i,arr) => {
                            const angle = (2*Math.PI/arr.length)*i - Math.PI/2;
                            const r = 70*(level/5);
                            return `${140+r*Math.cos(angle)},${140+r*Math.sin(angle)}`;
                          }).join(' ')"
                          fill="none" stroke="#E0E0E0" stroke-width="0.5"
                        />
                        <!-- 축선 -->
                        <line
                          v-for="(_,i) in Object.keys(result.sub_scores!)"
                          :key="'a'+i"
                          x1="140" y1="140"
                          :x2="140+70*Math.cos((2*Math.PI/Object.keys(result.sub_scores!).length)*i - Math.PI/2)"
                          :y2="140+70*Math.sin((2*Math.PI/Object.keys(result.sub_scores!).length)*i - Math.PI/2)"
                          stroke="#E0E0E0" stroke-width="0.5"
                        />
                        <!-- 데이터 -->
                        <polygon
                          :points="Object.values(result.sub_scores!).map((d:any,i:number,arr:any[]) => {
                            const angle = (2*Math.PI/arr.length)*i - Math.PI/2;
                            const r = 70*(d.avg/5);
                            return `${140+r*Math.cos(angle)},${140+r*Math.sin(angle)}`;
                          }).join(' ')"
                          fill="rgba(76,175,80,0.2)" stroke="#4CAF50" stroke-width="2"
                        />
                        <!-- 라벨 -->
                        <text
                          v-for="(domain,i) in Object.keys(result.sub_scores!)"
                          :key="'l'+i"
                          :x="140+92*Math.cos((2*Math.PI/Object.keys(result.sub_scores!).length)*i - Math.PI/2)"
                          :y="140+92*Math.sin((2*Math.PI/Object.keys(result.sub_scores!).length)*i - Math.PI/2)"
                          text-anchor="middle" dominant-baseline="central"
                          class="text-[9px] font-semibold" fill="#333"
                        >
                          {{ domain }}({{ (result.sub_scores as any)[domain].avg }})
                        </text>
                      </svg>
                    </div>

                    <!-- 바 차트 (클릭하면 해당 영역 문항 모달) -->
                    <p class="text-[13px] font-semibold text-[#333] mb-2">하위영역 점수 <span class="text-[11px] text-[#999] font-normal">클릭하면 문항별 응답을 볼 수 있습니다</span></p>
                    <div class="grid grid-cols-2 gap-3">
                      <div
                        v-for="(data, domain) in result.sub_scores"
                        :key="String(domain)"
                        class="bg-white rounded-[10px] border border-[#E8E8E8] p-3 cursor-pointer hover:border-[#4CAF50] hover:shadow-sm transition-all"
                        @click="openDomainModal(result, String(domain))"
                      >
                        <div class="flex items-center justify-between mb-1">
                          <p class="text-[12px] font-semibold text-[#555]">{{ domain }}</p>
                          <svg class="w-3.5 h-3.5 text-[#CCC]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6" /></svg>
                        </div>
                        <div class="flex items-baseline gap-1">
                          <span class="text-[18px] font-bold text-[#333]">{{ data.avg }}</span>
                          <span class="text-[12px] text-[#888]">/ 5.0</span>
                        </div>
                        <div class="bg-[#F0F0F0] rounded-full h-2 mt-1.5 overflow-hidden">
                          <div class="h-full rounded-full bg-[#4CAF50] transition-all" :style="{ width: `${(data.avg / 5) * 100}%` }" />
                        </div>
                        <p class="text-[11px] text-[#888] mt-1">{{ data.score }}점 / {{ data.max }}점</p>
                      </div>
                    </div>
                  </div>
                </td>
              </tr>
              </template>
            </tbody>
          </table>
        </div>
        <div class="px-5 py-3 border-t border-[#F0F0F0] flex items-center justify-between">
          <span class="text-[13px] text-[#888]">
            총 {{ filteredResults.length }}건{{ filterUser || filterLevel ? ` (${[filterUser, filterLevel].filter(Boolean).join(', ')})` : '' }}
          </span>
          <!-- 페이징 -->
          <div v-if="totalPages > 1" class="flex items-center gap-1">
            <button
              @click="currentPage = Math.max(1, currentPage - 1)"
              :disabled="currentPage === 1"
              class="w-8 h-8 flex items-center justify-center rounded-[8px] text-[13px] transition-colors disabled:opacity-30"
              :class="currentPage === 1 ? 'text-[#CCC]' : 'text-[#555] hover:bg-[#F0F0F0]'"
            >
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6" /></svg>
            </button>
            <template v-for="(p, idx) in pageNumbers" :key="idx">
              <span v-if="p === '...'" class="w-8 h-8 flex items-center justify-center text-[12px] text-[#999]">...</span>
              <button
                v-else
                @click="currentPage = p"
                class="w-8 h-8 flex items-center justify-center rounded-[8px] text-[13px] font-medium transition-colors"
                :class="currentPage === p ? 'bg-[#4CAF50] text-white' : 'text-[#555] hover:bg-[#F0F0F0]'"
              >
                {{ p }}
              </button>
            </template>
            <button
              @click="currentPage = Math.min(totalPages, currentPage + 1)"
              :disabled="currentPage === totalPages"
              class="w-8 h-8 flex items-center justify-center rounded-[8px] text-[13px] transition-colors disabled:opacity-30"
              :class="currentPage === totalPages ? 'text-[#CCC]' : 'text-[#555] hover:bg-[#F0F0F0]'"
            >
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6" /></svg>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- 하위영역 문항 모달 -->
    <Teleport to="body">
      <div v-if="domainModalResult" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/40" @click="closeDomainModal"></div>
        <div class="relative bg-white rounded-[16px] shadow-[0_0_30px_rgba(0,0,0,0.2)] w-full max-w-[520px] mx-4 max-h-[80vh] flex flex-col">
          <!-- 헤더 -->
          <div class="flex items-center justify-between px-5 py-4 border-b border-[#E8E8E8]">
            <div>
              <h3 class="text-[16px] font-bold text-[#333]">{{ domainModalName }}</h3>
              <p class="text-[12px] text-[#888] mt-0.5">
                {{ domainModalResult.profile?.name }} · {{ (domainModalResult.sub_scores as any)?.[domainModalName]?.avg ?? '-' }}/5.0 평균
              </p>
            </div>
            <button @click="closeDomainModal" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-[#F0F0F0]">
              <svg class="w-5 h-5 text-[#999]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
          </div>
          <!-- 문항 목록 -->
          <div class="flex-1 overflow-y-auto p-5 space-y-3">
            <div
              v-for="q in getDomainQuestions(domainModalResult, domainModalName)"
              :key="q.question_id"
              class="bg-[#F8F8F8] rounded-[10px] p-3"
            >
              <div class="flex items-start gap-2 mb-2">
                <span class="shrink-0 w-6 h-6 flex items-center justify-center bg-[#4CAF50] text-white text-[11px] font-bold rounded-full">{{ q.order }}</span>
                <p class="text-[13px] text-[#333] leading-relaxed">{{ q.content }}</p>
              </div>
              <!-- 리커트 응답 -->
              <div v-if="q.question_type === 'likert_5'" class="flex gap-1.5 ml-8">
                <div
                  v-for="n in 5"
                  :key="n"
                  class="flex flex-col items-center gap-0.5"
                >
                  <span
                    class="w-8 h-8 flex items-center justify-center rounded-full text-[12px] font-bold transition-all"
                    :class="Number(domainModalResult.analysis?.[q.question_id]) === n
                      ? 'bg-[#4CAF50] text-white scale-110'
                      : 'bg-white border border-[#E0E0E0] text-[#CCC]'"
                  >
                    {{ n }}
                  </span>
                  <span class="text-[8px] text-[#BBB]">{{ ['', '전혀', '아니', '보통', '그렇', '매우'][n] }}</span>
                </div>
              </div>
              <!-- 객관식 응답 -->
              <div v-else-if="domainModalResult.analysis?.[q.question_id] !== undefined" class="ml-8">
                <span class="text-[12px] px-2.5 py-1 rounded-full bg-[#E3F2FD] text-[#2196F3] font-medium">
                  {{ domainModalResult.analysis[q.question_id] }}
                </span>
              </div>
            </div>
            <div v-if="getDomainQuestions(domainModalResult, domainModalName).length === 0" class="text-center py-8 text-[#888] text-[13px]">
              해당 영역의 문항이 없습니다.
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
