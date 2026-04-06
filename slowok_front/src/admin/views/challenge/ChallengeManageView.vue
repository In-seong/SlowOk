<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@shared/api'
import { adminApi } from '@shared/api/adminApi'
import type { Challenge, LearningCategory, ApiResponse } from '@shared/types'
import { useToastStore } from '@shared/stores/toastStore'

const toast = useToastStore()
const router = useRouter()

const challenges = ref<Challenge[]>([])
const categories = ref<LearningCategory[]>([])
const loading = ref(true)
const error = ref('')

// 챌린지 모달 상태
const showModal = ref(false)
const modalMode = ref<'create' | 'edit'>('create')
const saving = ref(false)
const modalError = ref('')
const editingId = ref<number | null>(null)

const CHALLENGE_TYPE_OPTIONS = [
  '1주차', '2주차', '3주차', '4주차', '5주차', '6주차', '7주차', '8주차',
  '9주차', '10주차', '11주차', '12주차', '기타',
]

const form = ref({
  title: '',
  category_id: '' as number | '',
  challenge_type: '',
  difficulty_level: 1,
  allow_retry: true,
})

function resetForm() {
  form.value = {
    title: '',
    category_id: '',
    challenge_type: '',
    difficulty_level: 1,
    allow_retry: true,
  }
  modalError.value = ''
  editingId.value = null
}

function openCreateModal() {
  resetForm()
  modalMode.value = 'create'
  showModal.value = true
}

function openEditModal(challenge: Challenge) {
  modalMode.value = 'edit'
  editingId.value = challenge.challenge_id
  form.value = {
    title: challenge.title,
    category_id: challenge.category_id,
    challenge_type: challenge.challenge_type,
    difficulty_level: challenge.difficulty_level,
    allow_retry: challenge.allow_retry !== false,
  }
  modalError.value = ''
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  resetForm()
}

async function fetchData() {
  loading.value = true
  error.value = ''
  try {
    const [challengesRes, catsRes] = await Promise.all([
      api.get<ApiResponse<Challenge[]>>('/admin/challenges'),
      api.get<ApiResponse<LearningCategory[]>>('/admin/learning-categories'),
    ])
    if (challengesRes.data.success) challenges.value = challengesRes.data.data
    if (catsRes.data.success) categories.value = catsRes.data.data
  } catch (e: any) {
    error.value = e.response?.data?.message || '데이터를 불러오지 못했습니다.'
  } finally {
    loading.value = false
  }
}

function validateForm(): string | null {
  if (!form.value.title.trim()) return '제목을 입력해주세요.'
  if (!form.value.category_id) return '카테고리를 선택해주세요.'
  if (!form.value.challenge_type) return '주차를 선택해주세요.'
  return null
}

async function handleSubmit() {
  const validationError = validateForm()
  if (validationError) {
    modalError.value = validationError
    return
  }

  saving.value = true
  modalError.value = ''

  const payload = {
    title: form.value.title.trim(),
    category_id: Number(form.value.category_id),
    challenge_type: form.value.challenge_type.trim(),
    difficulty_level: form.value.difficulty_level,
    allow_retry: form.value.allow_retry,
  }

  try {
    if (modalMode.value === 'edit' && editingId.value) {
      const res = await api.put<ApiResponse<Challenge>>(`/admin/challenges/${editingId.value}`, payload)
      if (res.data.success) {
        const idx = challenges.value.findIndex((c) => c.challenge_id === editingId.value)
        if (idx !== -1) challenges.value[idx] = res.data.data
      }
    } else {
      const res = await api.post<ApiResponse<Challenge>>('/admin/challenges', payload)
      if (res.data.success) {
        challenges.value.push(res.data.data)
        closeModal()
        // 생성 후 바로 문항 편집으로 이동
        goToQuestionEdit(res.data.data.challenge_id)
        return
      }
    }
    closeModal()
  } catch (e: any) {
    modalError.value = e.response?.data?.message || '저장에 실패했습니다.'
  } finally {
    saving.value = false
  }
}

async function deleteChallenge(challenge: Challenge) {
  if (!confirm(`"${challenge.title}" 챌린지를 정말 삭제하시겠습니까?\n이 작업은 되돌릴 수 없습니다.`)) return
  try {
    await api.delete(`/admin/challenges/${challenge.challenge_id}`)
    challenges.value = challenges.value.filter((c) => c.challenge_id !== challenge.challenge_id)
  } catch (e: any) {
    toast.error(e.response?.data?.message || '삭제에 실패했습니다.')
  }
}

function getCategoryName(categoryId: number): string {
  return categories.value.find((c) => c.category_id === categoryId)?.name || '-'
}

function difficultyStars(level: number): string {
  return '★'.repeat(level) + '☆'.repeat(5 - level)
}

function goToQuestionEdit(challengeId: number) {
  router.push({ name: 'challenge-question-edit', params: { id: challengeId } })
}

async function duplicateChallenge(challenge: Challenge) {
  if (!confirm(`"${challenge.title}"을(를) 복제하시겠습니까?\n문항도 함께 복제됩니다.`)) return
  try {
    const res = await adminApi.duplicateChallenge(challenge.challenge_id)
    if (res.data.success) {
      challenges.value.push(res.data.data)
      toast.success(res.data.message || '복제 완료!')
    }
  } catch (e: any) {
    toast.error(e.response?.data?.message || '복제에 실패했습니다.')
  }
}

// 검색 + 유형 필터 + 정렬 + 페이징
const searchQuery = ref('')
const filterType = ref('')
const sortBy = ref<'type' | 'title' | 'difficulty'>('type')
const currentPage = ref(1)
const perPage = 15

const challengeTypes = computed(() => {
  const types = new Set(challenges.value.map(c => c.challenge_type).filter(Boolean))
  return Array.from(types).sort()
})

function weekOrder(type: string): number {
  const match = type.match(/(\d+)주차/)
  return match?.[1] ? parseInt(match[1]) : 999
}

const filteredChallenges = computed(() => {
  let filtered = challenges.value
  if (filterType.value) {
    filtered = filtered.filter(c => c.challenge_type === filterType.value)
  }
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase()
    filtered = filtered.filter(c =>
      c.title.toLowerCase().includes(q) ||
      (c.challenge_type ?? '').toLowerCase().includes(q) ||
      (c.category?.name ?? '').toLowerCase().includes(q)
    )
  }
  // 정렬
  return [...filtered].sort((a, b) => {
    if (sortBy.value === 'type') {
      const diff = weekOrder(a.challenge_type ?? '') - weekOrder(b.challenge_type ?? '')
      return diff !== 0 ? diff : a.title.localeCompare(b.title)
    }
    if (sortBy.value === 'title') return a.title.localeCompare(b.title)
    if (sortBy.value === 'difficulty') return a.difficulty_level - b.difficulty_level
    return 0
  })
})

const totalPages = computed(() => Math.ceil(filteredChallenges.value.length / perPage))

const pagedChallenges = computed(() => {
  const start = (currentPage.value - 1) * perPage
  return filteredChallenges.value.slice(start, start + perPage)
})

watch([searchQuery, filterType, sortBy], () => { currentPage.value = 1 })

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

onMounted(fetchData)
</script>

<template>
  <div class="p-6">
    <div class="max-w-[1200px] mx-auto">
      <!-- 상단 -->
      <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-3">
          <p class="text-[14px] text-[#888]">챌린지를 관리합니다.</p>
          <div class="relative">
            <input
              v-model="searchQuery"
              type="text"
              placeholder="제목/카테고리 검색..."
              class="bg-white border border-[#E8E8E8] rounded-[10px] pl-8 pr-8 py-2 text-[13px] w-[200px] focus:border-[#4CAF50] focus:outline-none"
            />
            <svg class="w-4 h-4 text-[#999] absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8" /><path d="m21 21-4.3-4.3" /></svg>
            <button v-if="searchQuery" @click="searchQuery = ''" class="absolute right-2 top-1/2 -translate-y-1/2 text-[#999] hover:text-[#555]">
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
          </div>
          <!-- 주차 필터 -->
          <select
            v-if="challengeTypes.length > 0"
            v-model="filterType"
            class="bg-white border border-[#E8E8E8] rounded-[10px] px-3 py-2 text-[13px] focus:border-[#4CAF50] focus:outline-none"
          >
            <option value="">전체 주차</option>
            <option v-for="t in challengeTypes" :key="t" :value="t">{{ t }}</option>
          </select>
          <!-- 정렬 -->
          <select
            v-model="sortBy"
            class="bg-white border border-[#E8E8E8] rounded-[10px] px-3 py-2 text-[13px] focus:border-[#4CAF50] focus:outline-none"
          >
            <option value="type">주차순</option>
            <option value="title">제목순</option>
            <option value="difficulty">난이도순</option>
          </select>
        </div>
        <button
          @click="openCreateModal"
          class="bg-[#4CAF50] hover:bg-[#388E3C] text-white rounded-[12px] px-5 py-2.5 text-[14px] font-medium active:scale-[0.98] transition-all"
        >
          + 챌린지 추가
        </button>
      </div>

      <!-- 로딩 -->
      <div v-if="loading" class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] p-8 text-center">
        <p class="text-[#888]">챌린지 목록을 불러오는 중...</p>
      </div>

      <!-- 에러 -->
      <div v-else-if="error" class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] p-8 text-center">
        <p class="text-red-500 mb-3">{{ error }}</p>
        <button
          @click="fetchData"
          class="bg-[#4CAF50] hover:bg-[#388E3C] text-white rounded-[12px] px-5 py-2.5 text-[14px] font-medium active:scale-[0.98] transition-all"
        >
          다시 시도
        </button>
      </div>

      <!-- 빈 상태 -->
      <div v-else-if="filteredChallenges.length === 0" class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] p-8 text-center">
        <p class="text-[#888]">{{ searchQuery ? '검색 결과가 없습니다.' : '등록된 챌린지가 없습니다.' }}</p>
      </div>

      <!-- 테이블 -->
      <div v-else class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-[14px]">
            <thead>
              <tr class="border-b border-[#E8E8E8] bg-[#FAFAFA]">
                <th class="px-3 py-3 font-semibold text-[#555] w-[50px]">#</th>
                <th class="px-5 py-3 font-semibold text-[#555]">제목</th>
                <th class="px-5 py-3 font-semibold text-[#555]">카테고리</th>
                <th class="px-5 py-3 font-semibold text-[#555]">유형</th>
                <th class="px-5 py-3 font-semibold text-[#555]">문항</th>
                <th class="px-5 py-3 font-semibold text-[#555]">난이도</th>
                <th class="px-5 py-3 font-semibold text-[#555]">재도전</th>
                <th class="px-5 py-3 font-semibold text-[#555]">액션</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(challenge, idx) in pagedChallenges"
                :key="challenge.challenge_id"
                class="border-b border-[#F0F0F0] hover:bg-[#FAFAFA] transition-colors"
              >
                <td class="px-3 py-3.5 text-[12px] text-[#999] font-mono">{{ (currentPage - 1) * perPage + idx + 1 }}</td>
                <td class="px-5 py-3.5 text-[#333] font-medium">{{ challenge.title }}</td>
                <td class="px-5 py-3.5 text-[#555]">{{ challenge.category?.name || getCategoryName(challenge.category_id) }}</td>
                <td class="px-5 py-3.5">
                  <span class="px-2 py-0.5 rounded-full text-[12px] font-medium bg-indigo-50 text-indigo-600">
                    {{ challenge.challenge_type }}
                  </span>
                </td>
                <td class="px-5 py-3.5">
                  <span class="text-[13px] font-medium" :class="(challenge.questions?.length ?? 0) === 0 ? 'text-red-400' : 'text-[#555]'">
                    {{ challenge.questions?.length ?? 0 }}개
                  </span>
                </td>
                <td class="px-5 py-3.5 text-[#888] text-[13px]">
                  {{ difficultyStars(challenge.difficulty_level) }}
                </td>
                <td class="px-5 py-3.5">
                  <span
                    class="px-2 py-0.5 rounded-full text-[11px] font-medium"
                    :class="challenge.allow_retry !== false ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-500'"
                  >
                    {{ challenge.allow_retry !== false ? '허용' : '1회만' }}
                  </span>
                </td>
                <td class="px-5 py-3.5">
                  <div class="flex items-center gap-3">
                    <button
                      @click="goToQuestionEdit(challenge.challenge_id)"
                      class="border border-[#4CAF50] text-[#4CAF50] hover:bg-[#4CAF50] hover:text-white rounded-[8px] px-3 py-1 text-[13px] font-medium transition-all"
                    >
                      문항 관리
                    </button>
                    <button
                      @click="openEditModal(challenge)"
                      class="text-[#4CAF50] hover:text-[#388E3C] text-[13px] font-medium transition-colors"
                    >
                      수정
                    </button>
                    <button
                      @click="duplicateChallenge(challenge)"
                      class="text-[#2196F3] hover:text-[#1976D2] text-[13px] font-medium transition-colors"
                    >
                      복제
                    </button>
                    <button
                      @click="deleteChallenge(challenge)"
                      class="text-red-500 hover:text-red-700 text-[13px] font-medium transition-colors"
                    >
                      삭제
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="px-5 py-3 border-t border-[#F0F0F0] flex items-center justify-between">
          <span class="text-[13px] text-[#888]">총 {{ filteredChallenges.length }}개{{ searchQuery ? ` (검색: "${searchQuery}")` : '' }}</span>
          <div v-if="totalPages > 1" class="flex items-center gap-1">
            <button @click="currentPage = Math.max(1, currentPage - 1)" :disabled="currentPage === 1" class="w-8 h-8 flex items-center justify-center rounded-[8px] text-[13px] transition-colors disabled:opacity-30" :class="currentPage === 1 ? 'text-[#CCC]' : 'text-[#555] hover:bg-[#F0F0F0]'">
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6" /></svg>
            </button>
            <template v-for="(p, idx) in pageNumbers" :key="idx">
              <span v-if="p === '...'" class="w-8 h-8 flex items-center justify-center text-[12px] text-[#999]">...</span>
              <button v-else @click="currentPage = p" class="w-8 h-8 flex items-center justify-center rounded-[8px] text-[13px] font-medium transition-colors" :class="currentPage === p ? 'bg-[#4CAF50] text-white' : 'text-[#555] hover:bg-[#F0F0F0]'">{{ p }}</button>
            </template>
            <button @click="currentPage = Math.min(totalPages, currentPage + 1)" :disabled="currentPage === totalPages" class="w-8 h-8 flex items-center justify-center rounded-[8px] text-[13px] transition-colors disabled:opacity-30" :class="currentPage === totalPages ? 'text-[#CCC]' : 'text-[#555] hover:bg-[#F0F0F0]'">
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6" /></svg>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- 챌린지 모달 -->
    <Teleport to="body">
      <div
        v-if="showModal"
        class="fixed inset-0 z-50 flex items-center justify-center"
      >
        <!-- 오버레이 -->
        <div class="absolute inset-0 bg-black/40" @click="closeModal"></div>

        <!-- 모달 컨텐츠 -->
        <div class="relative bg-white rounded-[16px] shadow-[0_0_30px_rgba(0,0,0,0.2)] w-full max-w-[520px] mx-4 p-6">
          <h2 class="text-[18px] font-bold text-[#333] mb-5">
            {{ modalMode === 'edit' ? '챌린지 수정' : '챌린지 추가' }}
          </h2>

          <!-- 모달 에러 -->
          <div v-if="modalError" class="mb-4 bg-red-50 border border-red-200 rounded-[12px] px-4 py-3 text-red-600 text-[14px]">
            {{ modalError }}
          </div>

          <form @submit.prevent="handleSubmit">
            <!-- 제목 -->
            <div class="mb-4">
              <label class="block text-[14px] font-semibold text-[#333] mb-1.5">제목</label>
              <input
                v-model="form.title"
                type="text"
                placeholder="챌린지 제목"
                class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] px-4 py-3 text-[15px] focus:border-[#4CAF50] focus:outline-none transition-colors"
              />
            </div>

            <!-- 카테고리 -->
            <div class="mb-4">
              <label class="block text-[14px] font-semibold text-[#333] mb-1.5">카테고리</label>
              <select
                v-model="form.category_id"
                class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] px-4 py-3 text-[15px] focus:border-[#4CAF50] focus:outline-none transition-colors"
              >
                <option value="" disabled>카테고리 선택</option>
                <option v-for="cat in categories" :key="cat.category_id" :value="cat.category_id">
                  {{ cat.name }}
                </option>
              </select>
            </div>

            <!-- 챌린지 유형 (주차) -->
            <div class="mb-4">
              <label class="block text-[14px] font-semibold text-[#333] mb-1.5">주차 구분</label>
              <select
                v-model="form.challenge_type"
                class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] px-4 py-3 text-[15px] focus:border-[#4CAF50] focus:outline-none transition-colors"
              >
                <option value="" disabled>주차 선택</option>
                <option v-for="opt in CHALLENGE_TYPE_OPTIONS" :key="opt" :value="opt">{{ opt }}</option>
              </select>
            </div>

            <!-- 난이도 -->
            <div class="mb-5">
              <label class="block text-[14px] font-semibold text-[#333] mb-1.5">
                난이도 ({{ form.difficulty_level }}/5)
              </label>
              <div class="flex items-center gap-3">
                <input
                  v-model.number="form.difficulty_level"
                  type="range"
                  min="1"
                  max="5"
                  step="1"
                  class="flex-1 accent-[#4CAF50]"
                />
                <span class="text-[14px] text-[#888] w-[100px] text-right">
                  {{ '★'.repeat(form.difficulty_level) }}{{ '☆'.repeat(5 - form.difficulty_level) }}
                </span>
              </div>
            </div>

            <!-- 재도전 허용 -->
            <div class="mb-5">
              <label class="flex items-center gap-3 cursor-pointer">
                <div class="relative inline-flex items-center">
                  <input type="checkbox" v-model="form.allow_retry" class="sr-only peer" />
                  <div class="w-11 h-6 bg-gray-200 rounded-full peer-checked:bg-[#4CAF50] transition-colors"></div>
                  <div class="absolute left-[2px] top-[2px] w-5 h-5 bg-white rounded-full shadow-sm transition-transform peer-checked:translate-x-5"></div>
                </div>
                <div>
                  <span class="text-[14px] font-semibold text-[#333]">재도전 허용</span>
                  <p class="text-[12px] text-[#888]">끄면 학습자가 한 번 통과 후 다시 풀 수 없습니다</p>
                </div>
              </label>
            </div>

            <!-- 버튼 -->
            <div class="flex items-center gap-3">
              <button
                type="submit"
                :disabled="saving"
                class="bg-[#4CAF50] hover:bg-[#388E3C] disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-[12px] px-6 py-3 text-[15px] font-medium active:scale-[0.98] transition-all"
              >
                {{ saving ? '저장 중...' : modalMode === 'edit' ? '수정하기' : '추가하기' }}
              </button>
              <button
                type="button"
                @click="closeModal"
                class="bg-[#F8F8F8] hover:bg-[#E8E8E8] text-[#555] rounded-[12px] px-6 py-3 text-[15px] font-medium active:scale-[0.98] transition-all"
              >
                취소
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </div>
</template>
