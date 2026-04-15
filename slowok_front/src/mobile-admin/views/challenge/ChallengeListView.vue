<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@shared/api'
import { adminApi } from '@shared/api/adminApi'
import type { Challenge, LearningCategory, ApiResponse } from '@shared/types'
import { useToastStore } from '@shared/stores/toastStore'
import BottomSheet from '../../components/BottomSheet.vue'

const router = useRouter()
const toast = useToastStore()

const challenges = ref<Challenge[]>([])
const categories = ref<LearningCategory[]>([])
const loading = ref(true)

const search = ref('')
const filterWeek = ref('')
const sortBy = ref<'sort_order' | 'title' | 'created'>('sort_order')

// 폼
const showForm = ref(false)
const editingId = ref<number | null>(null)
const saving = ref(false)
const formError = ref('')
const form = ref({ title: '', category_id: '' as number | '', challenge_type: '', difficulty_level: 1, allow_retry: true, sort_order: 0 })

const WEEK_OPTIONS = ['1주차','2주차','3주차','4주차','5주차','6주차','7주차','8주차','9주차','10주차','11주차','12주차','기타']

const weekOptions = computed(() => {
  const set = new Set(challenges.value.map(c => c.challenge_type).filter(Boolean))
  return Array.from(set).sort()
})

const filteredChallenges = computed(() => {
  let list = [...challenges.value]
  if (search.value.trim()) {
    const q = search.value.toLowerCase()
    list = list.filter(c => c.title.toLowerCase().includes(q) || getCategoryName(c.category_id).toLowerCase().includes(q))
  }
  if (filterWeek.value) {
    list = list.filter(c => c.challenge_type === filterWeek.value)
  }
  if (sortBy.value === 'title') list.sort((a, b) => a.title.localeCompare(b.title))
  else if (sortBy.value === 'created') list.sort((a, b) => b.challenge_id - a.challenge_id)
  else list.sort((a, b) => ((a as any).sort_order ?? 0) - ((b as any).sort_order ?? 0))
  return list
})

function getCategoryName(id: number): string {
  return categories.value.find(c => c.category_id === id)?.name || '-'
}

function difficultyStars(level: number): string {
  return '★'.repeat(level) + '☆'.repeat(5 - level)
}

async function fetchData() {
  loading.value = true
  try {
    const [cRes, catRes] = await Promise.all([
      api.get<ApiResponse<Challenge[]>>('/admin/challenges'),
      api.get<ApiResponse<LearningCategory[]>>('/admin/learning-categories'),
    ])
    if (cRes.data.success) challenges.value = cRes.data.data
    if (catRes.data.success) categories.value = catRes.data.data
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    toast.error(err.response?.data?.message || '데이터를 불러오지 못했습니다.')
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editingId.value = null
  form.value = { title: '', category_id: '', challenge_type: '', difficulty_level: 1, allow_retry: true, sort_order: 0 }
  formError.value = ''
  showForm.value = true
}

function openEdit(c: Challenge) {
  editingId.value = c.challenge_id
  form.value = {
    title: c.title,
    category_id: c.category_id,
    challenge_type: c.challenge_type,
    difficulty_level: c.difficulty_level,
    allow_retry: c.allow_retry !== false,
    sort_order: (c as any).sort_order ?? 0,
  }
  formError.value = ''
  showForm.value = true
}

async function handleSubmit() {
  if (!form.value.title.trim()) { formError.value = '제목을 입력해주세요.'; return }
  if (!form.value.category_id) { formError.value = '카테고리를 선택해주세요.'; return }
  if (!form.value.challenge_type) { formError.value = '주차를 선택해주세요.'; return }
  saving.value = true
  formError.value = ''
  const payload = { ...form.value, category_id: Number(form.value.category_id) }
  try {
    if (editingId.value) {
      await api.put(`/admin/challenges/${editingId.value}`, payload)
    } else {
      const res = await api.post<ApiResponse<Challenge>>('/admin/challenges', payload)
      if (res.data.success) {
        showForm.value = false
        router.push({ name: 'challenge-questions', params: { id: res.data.data.challenge_id } })
        return
      }
    }
    showForm.value = false
    await fetchData()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    formError.value = err.response?.data?.message || '저장에 실패했습니다.'
  } finally {
    saving.value = false
  }
}

async function handleDelete(c: Challenge) {
  if (!confirm(`"${c.title}"을(를) 삭제하시겠습니까?`)) return
  try {
    await api.delete(`/admin/challenges/${c.challenge_id}`)
    challenges.value = challenges.value.filter(x => x.challenge_id !== c.challenge_id)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    toast.error(err.response?.data?.message || '삭제에 실패했습니다.')
  }
}

async function handleDuplicate(c: Challenge) {
  if (!confirm(`"${c.title}"을(를) 복제하시겠습니까?`)) return
  try {
    const res = await adminApi.duplicateChallenge(c.challenge_id)
    if (res.data.success) {
      challenges.value.push(res.data.data)
      toast.success('복제 완료!')
    }
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    toast.error(err.response?.data?.message || '복제에 실패했습니다.')
  }
}

onMounted(fetchData)
</script>

<template>
  <div class="min-h-screen bg-[#FAFAFA]">
    <header class="sticky top-0 z-40 bg-white border-b border-[#E8E8E8] h-[56px] flex items-center px-4">
      <button @click="router.back()" class="w-10 h-10 flex items-center justify-center">
        <svg class="w-5 h-5 text-[#333]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
      </button>
      <h1 class="flex-1 text-center text-[16px] font-bold text-[#333]">챌린지 관리</h1>
      <button @click="openCreate" class="w-10 h-10 flex items-center justify-center text-[#4CAF50]">
        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      </button>
    </header>

    <!-- 검색/필터 -->
    <div class="sticky top-[56px] z-30 bg-[#FAFAFA] px-4 pt-3 pb-2 space-y-2">
      <input v-model="search" type="text" placeholder="제목/카테고리 검색" class="w-full bg-white border border-[#E8E8E8] rounded-[12px] px-4 py-3 text-[14px] focus:outline-none focus:border-[#4CAF50]" />
      <div class="flex gap-2">
        <select v-model="filterWeek" class="flex-1 bg-white border border-[#E8E8E8] rounded-[10px] px-3 py-2 text-[13px] text-[#333]">
          <option value="">전체 주차</option>
          <option v-for="w in weekOptions" :key="w" :value="w">{{ w }}</option>
        </select>
        <select v-model="sortBy" class="flex-1 bg-white border border-[#E8E8E8] rounded-[10px] px-3 py-2 text-[13px] text-[#333]">
          <option value="sort_order">순서순</option>
          <option value="title">제목순</option>
          <option value="created">최신순</option>
        </select>
      </div>
    </div>

    <div v-if="loading" class="flex justify-center py-20">
      <div class="w-8 h-8 border-3 border-[#4CAF50] border-t-transparent rounded-full animate-spin" />
    </div>

    <div v-else class="px-4 pb-4 space-y-3">
      <div v-if="filteredChallenges.length === 0" class="bg-white rounded-[16px] shadow-sm border border-[#E8E8E8] p-8 text-center">
        <p class="text-[14px] text-[#888]">챌린지가 없습니다.</p>
      </div>

      <div v-for="c in filteredChallenges" :key="c.challenge_id" class="bg-white rounded-[16px] shadow-sm border border-[#E8E8E8] p-4">
        <p class="text-[15px] font-semibold text-[#333]">{{ c.title }}</p>
        <div class="flex flex-wrap items-center gap-2 mt-1.5">
          <span class="text-[11px] font-medium px-2 py-0.5 bg-[#E8F5E9] text-[#4CAF50] rounded-[6px]">{{ c.challenge_type }}</span>
          <span class="text-[12px] text-[#888]">{{ getCategoryName(c.category_id) }}</span>
        </div>
        <div class="flex items-center gap-3 mt-1.5 text-[12px] text-[#888]">
          <span>문항 {{ c.questions?.length ?? 0 }}개</span>
          <span class="text-[#FF9800]">{{ difficultyStars(c.difficulty_level) }}</span>
          <span>순서: {{ (c as any).sort_order ?? 0 }}</span>
        </div>
        <div class="flex gap-2 mt-3 overflow-x-auto">
          <button @click="router.push({ name: 'challenge-questions', params: { id: c.challenge_id } })" class="shrink-0 text-[12px] font-medium px-3 py-1.5 bg-[#E8F5E9] text-[#4CAF50] rounded-[8px] active:opacity-70">문항관리</button>
          <button @click="openEdit(c)" class="shrink-0 text-[12px] font-medium px-3 py-1.5 bg-[#F0F0F0] text-[#555] rounded-[8px] active:opacity-70">수정</button>
          <button @click="handleDuplicate(c)" class="shrink-0 text-[12px] font-medium px-3 py-1.5 bg-[#F0F0F0] text-[#555] rounded-[8px] active:opacity-70">복제</button>
          <button @click="handleDelete(c)" class="shrink-0 text-[12px] font-medium px-3 py-1.5 bg-[#FFF0F0] text-[#FF4444] rounded-[8px] active:opacity-70">삭제</button>
        </div>
      </div>

      <p class="text-center text-[12px] text-[#888] pt-2">총 {{ filteredChallenges.length }}개</p>
    </div>

    <!-- 생성/수정 폼 -->
    <BottomSheet v-model="showForm" :title="editingId ? '챌린지 수정' : '챌린지 생성'" max-height="100vh">
      <div class="space-y-4 pb-4">
        <div v-if="formError" class="bg-red-50 text-[#FF4444] text-[13px] px-4 py-2.5 rounded-[10px]">{{ formError }}</div>

        <div>
          <label class="block text-[13px] font-medium text-[#555] mb-1">제목 <span class="text-[#FF4444]">*</span></label>
          <input v-model="form.title" type="text" placeholder="챌린지 제목" class="w-full px-4 py-3.5 border border-[#E8E8E8] rounded-[12px] text-[15px] focus:outline-none focus:border-[#4CAF50]" />
        </div>
        <div>
          <label class="block text-[13px] font-medium text-[#555] mb-1">카테고리 <span class="text-[#FF4444]">*</span></label>
          <select v-model="form.category_id" class="w-full px-4 py-3.5 border border-[#E8E8E8] rounded-[12px] text-[15px] bg-white focus:outline-none focus:border-[#4CAF50]">
            <option value="">선택</option>
            <option v-for="cat in categories" :key="cat.category_id" :value="cat.category_id">{{ cat.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-[13px] font-medium text-[#555] mb-1">주차 <span class="text-[#FF4444]">*</span></label>
          <select v-model="form.challenge_type" class="w-full px-4 py-3.5 border border-[#E8E8E8] rounded-[12px] text-[15px] bg-white focus:outline-none focus:border-[#4CAF50]">
            <option value="">선택</option>
            <option v-for="w in WEEK_OPTIONS" :key="w" :value="w">{{ w }}</option>
          </select>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-[13px] font-medium text-[#555] mb-1">난이도 (1~5)</label>
            <input v-model.number="form.difficulty_level" type="number" min="1" max="5" class="w-full px-4 py-3.5 border border-[#E8E8E8] rounded-[12px] text-[15px] focus:outline-none focus:border-[#4CAF50]" />
          </div>
          <div>
            <label class="block text-[13px] font-medium text-[#555] mb-1">순서</label>
            <input v-model.number="form.sort_order" type="number" min="0" class="w-full px-4 py-3.5 border border-[#E8E8E8] rounded-[12px] text-[15px] focus:outline-none focus:border-[#4CAF50]" />
          </div>
        </div>
        <div class="flex items-center gap-2">
          <input v-model="form.allow_retry" type="checkbox" id="allow_retry" class="w-5 h-5 accent-[#4CAF50]" />
          <label for="allow_retry" class="text-[14px] text-[#333]">재도전 허용</label>
        </div>

        <button @click="handleSubmit" :disabled="saving" class="w-full py-3.5 bg-[#4CAF50] text-white rounded-[12px] text-[15px] font-semibold disabled:opacity-50 active:bg-[#388E3C]">
          {{ saving ? '저장 중...' : (editingId ? '수정' : '생성') }}
        </button>
      </div>
    </BottomSheet>
  </div>
</template>
