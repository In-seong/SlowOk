<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@shared/api'
import type { ScreeningTest, LearningCategory, ApiResponse } from '@shared/types'
import { useToastStore } from '@shared/stores/toastStore'
import BottomSheet from '../../components/BottomSheet.vue'

const router = useRouter()
const toast = useToastStore()

const tests = ref<ScreeningTest[]>([])
const categories = ref<LearningCategory[]>([])
const loading = ref(true)

const showForm = ref(false)
const editingId = ref<number | null>(null)
const saving = ref(false)
const formError = ref('')
const form = ref({
  title: '',
  description: '',
  test_type: 'MULTIPLE_CHOICE' as 'MULTIPLE_CHOICE' | 'LIKERT',
  category_id: '' as number | '',
  question_count: 10,
  time_limit: '' as number | '',
})

async function fetchData() {
  loading.value = true
  try {
    const [tRes, cRes] = await Promise.all([
      api.get<ApiResponse<ScreeningTest[]>>('/admin/screening-tests'),
      api.get<ApiResponse<LearningCategory[]>>('/admin/learning-categories'),
    ])
    if (tRes.data.success) tests.value = tRes.data.data
    if (cRes.data.success) categories.value = cRes.data.data
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    toast.error(err.response?.data?.message || '데이터를 불러오지 못했습니다.')
  } finally {
    loading.value = false
  }
}

function getCategoryName(id: number): string {
  return categories.value.find(c => c.category_id === id)?.name || '-'
}

function openCreate() {
  editingId.value = null
  form.value = { title: '', description: '', test_type: 'MULTIPLE_CHOICE', category_id: '', question_count: 10, time_limit: '' }
  formError.value = ''
  showForm.value = true
}

function openEdit(t: ScreeningTest) {
  editingId.value = t.test_id
  form.value = {
    title: t.title,
    description: t.description || '',
    test_type: t.test_type,
    category_id: t.category_id,
    question_count: t.question_count,
    time_limit: t.time_limit ?? '',
  }
  formError.value = ''
  showForm.value = true
}

async function handleSubmit() {
  if (!form.value.title.trim()) { formError.value = '제목을 입력해주세요.'; return }
  if (!form.value.category_id) { formError.value = '카테고리를 선택해주세요.'; return }
  saving.value = true
  formError.value = ''
  const payload = {
    title: form.value.title.trim(),
    description: form.value.description || null,
    test_type: form.value.test_type,
    category_id: Number(form.value.category_id),
    question_count: form.value.question_count,
    time_limit: form.value.time_limit || null,
  }
  try {
    if (editingId.value) {
      await api.put(`/admin/screening-tests/${editingId.value}`, payload)
    } else {
      await api.post('/admin/screening-tests', payload)
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

async function handleDelete(t: ScreeningTest) {
  if (!confirm(`"${t.title}"을(를) 삭제하시겠습니까?`)) return
  try {
    await api.delete(`/admin/screening-tests/${t.test_id}`)
    tests.value = tests.value.filter(x => x.test_id !== t.test_id)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    toast.error(err.response?.data?.message || '삭제에 실패했습니다.')
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
      <h1 class="flex-1 text-center text-[16px] font-bold text-[#333]">진단 관리</h1>
      <button @click="openCreate" class="w-10 h-10 flex items-center justify-center text-[#4CAF50]">
        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      </button>
    </header>

    <div v-if="loading" class="flex justify-center py-20">
      <div class="w-8 h-8 border-3 border-[#4CAF50] border-t-transparent rounded-full animate-spin" />
    </div>

    <div v-else class="px-4 py-4 space-y-3">
      <div v-if="tests.length === 0" class="bg-white rounded-[16px] shadow-sm border border-[#E8E8E8] p-8 text-center">
        <p class="text-[14px] text-[#888]">등록된 진단검사가 없습니다.</p>
      </div>

      <div v-for="t in tests" :key="t.test_id" class="bg-white rounded-[16px] shadow-sm border border-[#E8E8E8] p-4">
        <p class="text-[15px] font-semibold text-[#333]">{{ t.title }}</p>
        <div class="flex flex-wrap items-center gap-2 mt-1.5">
          <span class="text-[11px] font-medium px-2 py-0.5 rounded-[6px] whitespace-nowrap" :class="t.test_type === 'LIKERT' ? 'bg-purple-50 text-purple-600' : 'bg-blue-50 text-blue-600'">
            {{ t.test_type === 'LIKERT' ? '리커트' : '객관식' }}
          </span>
          <span class="text-[12px] text-[#888]">{{ getCategoryName(t.category_id) }}</span>
          <span class="text-[12px] text-[#888]">문항 {{ t.question_count }}개</span>
        </div>
        <p v-if="t.description" class="text-[12px] text-[#888] mt-1.5 line-clamp-2">{{ t.description }}</p>
        <div class="flex gap-2 mt-3">
          <button @click="openEdit(t)" class="text-[12px] font-medium px-3 py-1.5 bg-[#E8F5E9] text-[#4CAF50] rounded-[8px] active:opacity-70">수정</button>
          <button @click="handleDelete(t)" class="text-[12px] font-medium px-3 py-1.5 bg-[#FFF0F0] text-[#FF4444] rounded-[8px] active:opacity-70">삭제</button>
        </div>
      </div>
    </div>

    <BottomSheet v-model="showForm" :title="editingId ? '진단검사 수정' : '진단검사 추가'" max-height="100vh">
      <div class="space-y-4 pb-4">
        <div v-if="formError" class="bg-red-50 text-[#FF4444] text-[13px] px-4 py-2.5 rounded-[10px]">{{ formError }}</div>

        <div>
          <label class="block text-[13px] font-medium text-[#555] mb-1">제목 <span class="text-[#FF4444]">*</span></label>
          <input v-model="form.title" type="text" placeholder="진단검사 제목" class="w-full px-4 py-3.5 border border-[#E8E8E8] rounded-[12px] text-[15px] focus:outline-none focus:border-[#4CAF50]" />
        </div>
        <div>
          <label class="block text-[13px] font-medium text-[#555] mb-1">설명</label>
          <textarea v-model="form.description" rows="2" placeholder="간단한 설명" class="w-full px-4 py-3.5 border border-[#E8E8E8] rounded-[12px] text-[15px] focus:outline-none focus:border-[#4CAF50] resize-none" />
        </div>
        <div>
          <label class="block text-[13px] font-medium text-[#555] mb-1">유형</label>
          <div class="flex gap-2">
            <button @click="form.test_type = 'MULTIPLE_CHOICE'" :class="form.test_type === 'MULTIPLE_CHOICE' ? 'bg-[#4CAF50] text-white' : 'bg-[#F0F0F0] text-[#666]'" class="flex-1 py-3 rounded-[12px] text-[14px] font-medium">객관식</button>
            <button @click="form.test_type = 'LIKERT'" :class="form.test_type === 'LIKERT' ? 'bg-[#4CAF50] text-white' : 'bg-[#F0F0F0] text-[#666]'" class="flex-1 py-3 rounded-[12px] text-[14px] font-medium">리커트</button>
          </div>
        </div>
        <div>
          <label class="block text-[13px] font-medium text-[#555] mb-1">카테고리 <span class="text-[#FF4444]">*</span></label>
          <select v-model="form.category_id" class="w-full px-4 py-3.5 border border-[#E8E8E8] rounded-[12px] text-[15px] bg-white focus:outline-none focus:border-[#4CAF50]">
            <option value="">선택</option>
            <option v-for="cat in categories" :key="cat.category_id" :value="cat.category_id">{{ cat.name }}</option>
          </select>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-[13px] font-medium text-[#555] mb-1">문항 수</label>
            <input v-model.number="form.question_count" type="number" min="1" class="w-full px-4 py-3.5 border border-[#E8E8E8] rounded-[12px] text-[15px] focus:outline-none focus:border-[#4CAF50]" />
          </div>
          <div>
            <label class="block text-[13px] font-medium text-[#555] mb-1">제한시간(분)</label>
            <input v-model.number="form.time_limit" type="number" min="0" placeholder="없음" class="w-full px-4 py-3.5 border border-[#E8E8E8] rounded-[12px] text-[15px] focus:outline-none focus:border-[#4CAF50]" />
          </div>
        </div>

        <button @click="handleSubmit" :disabled="saving" class="w-full py-3.5 bg-[#4CAF50] text-white rounded-[12px] text-[15px] font-semibold disabled:opacity-50 active:bg-[#388E3C]">
          {{ saving ? '저장 중...' : (editingId ? '수정' : '추가') }}
        </button>
      </div>
    </BottomSheet>
  </div>
</template>
