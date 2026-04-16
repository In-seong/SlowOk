<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
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

// 챌린지 모달
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
  sort_order: 0,
})

function resetForm() {
  form.value = { title: '', category_id: '', challenge_type: '', difficulty_level: 1, allow_retry: true, sort_order: 0 }
  modalError.value = ''
  editingId.value = null
}

function openCreateModal() {
  resetForm()
  if (activeTab.value !== 'all') form.value.challenge_type = activeTab.value
  modalMode.value = 'create'
  showModal.value = true
}

function openEditModal(challenge: Challenge) {
  modalMode.value = 'edit'
  editingId.value = challenge.challenge_id
  form.value = {
    title: challenge.title,
    category_id: challenge.category_id,
    challenge_type: challenge.challenge_type ?? '',
    difficulty_level: challenge.difficulty_level,
    allow_retry: challenge.allow_retry !== false,
    sort_order: challenge.sort_order ?? 0,
  }
  modalError.value = ''
  showModal.value = true
}

function closeModal() { showModal.value = false; resetForm() }

async function fetchData() {
  loading.value = true; error.value = ''
  try {
    const [cr, ca] = await Promise.all([
      api.get<ApiResponse<Challenge[]>>('/admin/challenges'),
      api.get<ApiResponse<LearningCategory[]>>('/admin/learning-categories'),
    ])
    if (cr.data.success) challenges.value = cr.data.data
    if (ca.data.success) categories.value = ca.data.data
  } catch (e: any) {
    error.value = e.response?.data?.message || '데이터를 불러오지 못했습니다.'
  } finally { loading.value = false }
}

function validateForm(): string | null {
  if (!form.value.title.trim()) return '제목을 입력해주세요.'
  if (!form.value.category_id) return '카테고리를 선택해주세요.'
  return null
}

async function handleSubmit() {
  const err = validateForm()
  if (err) { modalError.value = err; return }
  saving.value = true; modalError.value = ''
  const payload = {
    title: form.value.title.trim(),
    category_id: Number(form.value.category_id),
    challenge_type: form.value.challenge_type.trim() || null,
    difficulty_level: form.value.difficulty_level,
    allow_retry: form.value.allow_retry,
    sort_order: form.value.sort_order,
  }
  try {
    if (modalMode.value === 'edit' && editingId.value) {
      const res = await api.put<ApiResponse<Challenge>>(`/admin/challenges/${editingId.value}`, payload)
      if (res.data.success) {
        const idx = challenges.value.findIndex(c => c.challenge_id === editingId.value)
        if (idx !== -1) challenges.value[idx] = res.data.data
      }
    } else {
      const res = await api.post<ApiResponse<Challenge>>('/admin/challenges', payload)
      if (res.data.success) {
        challenges.value.push(res.data.data)
        closeModal()
        goToQuestionEdit(res.data.data.challenge_id)
        return
      }
    }
    closeModal()
  } catch (e: any) {
    modalError.value = e.response?.data?.message || '저장에 실패했습니다.'
  } finally { saving.value = false }
}

async function deleteChallenge(ch: Challenge) {
  if (!confirm(`"${ch.title}" 챌린지를 정말 삭제하시겠습니까?`)) return
  try {
    await api.delete(`/admin/challenges/${ch.challenge_id}`)
    challenges.value = challenges.value.filter(c => c.challenge_id !== ch.challenge_id)
  } catch (e: any) { toast.error(e.response?.data?.message || '삭제에 실패했습니다.') }
}

function getCategoryName(id: number): string {
  return categories.value.find(c => c.category_id === id)?.name || '-'
}

function difficultyStars(lv: number): string {
  return '★'.repeat(lv) + '☆'.repeat(5 - lv)
}

function goToQuestionEdit(id: number) {
  router.push({ name: 'challenge-question-edit', params: { id } })
}

async function duplicateChallenge(ch: Challenge) {
  if (!confirm(`"${ch.title}"을(를) 복제하시겠습니까?`)) return
  try {
    const res = await adminApi.duplicateChallenge(ch.challenge_id)
    if (res.data.success) { challenges.value.push(res.data.data); toast.success(res.data.message || '복제 완료!') }
  } catch (e: any) { toast.error(e.response?.data?.message || '복제에 실패했습니다.') }
}

// ========== 미배정 → 현재 주차로 원클릭 배정 ==========
async function assignToCurrentTab(ch: Challenge) {
  if (activeTab.value === 'all') return
  const week = activeTab.value
  const currentCount = challenges.value.filter(c => c.challenge_type === week).length
  try {
    const res = await api.put<ApiResponse<Challenge>>(`/admin/challenges/${ch.challenge_id}`, {
      challenge_type: week,
      sort_order: currentCount + 1,
    })
    if (res.data.success) {
      const idx = challenges.value.findIndex(c => c.challenge_id === ch.challenge_id)
      if (idx !== -1) challenges.value[idx] = res.data.data
      toast.success(`"${ch.title}" → ${week} 배정 완료`)
    }
  } catch (e: any) { toast.error(e.response?.data?.message || '배정에 실패했습니다.') }
}

// ========== 주차 탭 내 드래그 순서 변경 ==========
const dragIdx = ref<number | null>(null)
const dragOverIdx = ref<number | null>(null)

function onDragStart(idx: number) { dragIdx.value = idx }
function onDragOver(idx: number, e: DragEvent) { e.preventDefault(); dragOverIdx.value = idx }
function onDragEnd() { dragIdx.value = null; dragOverIdx.value = null }

async function onDrop(idx: number) {
  if (dragIdx.value === null || dragIdx.value === idx || activeTab.value === 'all') {
    dragIdx.value = null; dragOverIdx.value = null; return
  }

  // tabChallenges를 복제해서 순서 변경
  const list = [...tabChallenges.value]
  const item = list.splice(dragIdx.value, 1)[0]
  if (!item) { dragIdx.value = null; dragOverIdx.value = null; return }
  list.splice(idx, 0, item)

  dragIdx.value = null; dragOverIdx.value = null

  // sort_order 1부터 재배정 후 API 호출
  const promises = list.map((ch, i) => {
    const newOrder = i + 1
    if (ch.sort_order === newOrder) return null
    return api.put<ApiResponse<Challenge>>(`/admin/challenges/${ch.challenge_id}`, { sort_order: newOrder })
      .then(res => {
        if (res.data.success) {
          const orig = challenges.value.find(c => c.challenge_id === ch.challenge_id)
          if (orig) orig.sort_order = newOrder
        }
      })
  }).filter(Boolean)

  if (promises.length > 0) {
    try {
      await Promise.all(promises)
      toast.success('순서가 변경되었습니다.')
    } catch { toast.error('순서 변경에 실패했습니다.') }
  }
}

// ========== 주차 탭 ==========
const activeTab = ref('all')
const searchQuery = ref('')

function weekOrder(type: string): number {
  const m = type.match(/(\d+)주차/)
  return m?.[1] ? parseInt(m[1]) : 999
}

const weekTabs = computed(() => {
  const types = new Set(challenges.value.map(c => c.challenge_type).filter(Boolean))
  return Array.from(types).sort((a, b) => weekOrder(a) - weekOrder(b))
})

const unassignedChallenges = computed(() =>
  challenges.value.filter(c => !c.challenge_type || c.challenge_type.trim() === '')
    .sort((a, b) => b.challenge_id - a.challenge_id)
)

const tabChallenges = computed(() => {
  let list: Challenge[]
  if (activeTab.value === 'all') {
    list = challenges.value.filter(c => c.challenge_type && c.challenge_type.trim() !== '')
    list = [...list].sort((a, b) => {
      const w = weekOrder(a.challenge_type ?? '') - weekOrder(b.challenge_type ?? '')
      if (w !== 0) return w
      return (a.sort_order || 999) - (b.sort_order || 999)
    })
  } else {
    list = challenges.value.filter(c => c.challenge_type === activeTab.value)
      .sort((a, b) => (a.sort_order || 999) - (b.sort_order || 999))
  }
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase()
    list = list.filter(c => c.title.toLowerCase().includes(q) || (c.category?.name ?? '').toLowerCase().includes(q))
  }
  return list
})

const showUnassigned = computed(() => {
  if (searchQuery.value.trim()) return []
  return unassignedChallenges.value
})

function getTabCount(type: string): number {
  return challenges.value.filter(c => c.challenge_type === type).length
}

const isDraggable = computed(() => activeTab.value !== 'all' && !searchQuery.value.trim())

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
            <input v-model="searchQuery" type="text" placeholder="제목/카테고리 검색..." class="bg-white border border-[#E8E8E8] rounded-[10px] pl-8 pr-8 py-2 text-[13px] w-[200px] focus:border-[#4CAF50] focus:outline-none" />
            <svg class="w-4 h-4 text-[#999] absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8" /><path d="m21 21-4.3-4.3" /></svg>
            <button v-if="searchQuery" @click="searchQuery = ''" class="absolute right-2 top-1/2 -translate-y-1/2 text-[#999] hover:text-[#555]">
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
          </div>
        </div>
        <button @click="openCreateModal" class="bg-[#4CAF50] hover:bg-[#388E3C] text-white rounded-[12px] px-5 py-2.5 text-[14px] font-medium active:scale-[0.98] transition-all">+ 챌린지 추가</button>
      </div>

      <!-- 주차 탭 -->
      <div class="flex items-center gap-1.5 mb-4 overflow-x-auto pb-1">
        <button @click="activeTab = 'all'" class="shrink-0 px-4 py-2 rounded-[10px] text-[13px] font-medium transition-all" :class="activeTab === 'all' ? 'bg-[#4CAF50] text-white' : 'bg-white border border-[#E8E8E8] text-[#555] hover:bg-[#F0F0F0]'">
          전체 ({{ challenges.length }})
        </button>
        <button v-for="week in weekTabs" :key="week" @click="activeTab = week" class="shrink-0 px-4 py-2 rounded-[10px] text-[13px] font-medium transition-all" :class="activeTab === week ? 'bg-[#4CAF50] text-white' : 'bg-white border border-[#E8E8E8] text-[#555] hover:bg-[#F0F0F0]'">
          {{ week }} ({{ getTabCount(week) }})
        </button>
      </div>

      <!-- 드래그 안내 -->
      <div v-if="isDraggable && tabChallenges.length > 1" class="mb-3 flex items-center gap-2 text-[12px] text-[#888]">
        <svg class="w-4 h-4 text-[#4CAF50]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M2 12h20" /></svg>
        드래그하여 순서를 변경할 수 있습니다 · 변경 즉시 저장
      </div>

      <!-- 로딩 -->
      <div v-if="loading" class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] p-8 text-center">
        <p class="text-[#888]">챌린지 목록을 불러오는 중...</p>
      </div>

      <!-- 에러 -->
      <div v-else-if="error" class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] p-8 text-center">
        <p class="text-red-500 mb-3">{{ error }}</p>
        <button @click="fetchData" class="bg-[#4CAF50] hover:bg-[#388E3C] text-white rounded-[12px] px-5 py-2.5 text-[14px] font-medium">다시 시도</button>
      </div>

      <!-- 빈 상태 -->
      <div v-else-if="tabChallenges.length === 0 && showUnassigned.length === 0" class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] p-8 text-center">
        <p class="text-[#888]">{{ searchQuery ? '검색 결과가 없습니다.' : activeTab === 'all' ? '등록된 챌린지가 없습니다.' : `${activeTab}에 챌린지가 없습니다.` }}</p>
      </div>

      <!-- 챌린지 목록 -->
      <div v-else class="space-y-4">
        <!-- 배정된 챌린지 -->
        <div v-if="tabChallenges.length > 0" class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full text-left text-[14px]">
              <thead>
                <tr class="border-b border-[#E8E8E8] bg-[#FAFAFA]">
                  <th v-if="isDraggable" class="w-[36px]"></th>
                  <th class="px-3 py-3 font-semibold text-[#555] w-[40px]">#</th>
                  <th class="px-5 py-3 font-semibold text-[#555]">제목</th>
                  <th class="px-5 py-3 font-semibold text-[#555]">카테고리</th>
                  <th v-if="activeTab === 'all'" class="px-5 py-3 font-semibold text-[#555]">주차</th>
                  <th class="px-5 py-3 font-semibold text-[#555]">문항</th>
                  <th class="px-5 py-3 font-semibold text-[#555]">난이도</th>
                  <th class="px-5 py-3 font-semibold text-[#555]">재도전</th>
                  <th class="px-5 py-3 font-semibold text-[#555]">액션</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="(challenge, idx) in tabChallenges"
                  :key="challenge.challenge_id"
                  :draggable="isDraggable"
                  @dragstart="isDraggable && onDragStart(idx)"
                  @dragover="isDraggable && onDragOver(idx, $event)"
                  @drop="isDraggable && onDrop(idx)"
                  @dragend="onDragEnd"
                  class="border-b border-[#F0F0F0] transition-colors"
                  :class="[
                    isDraggable ? 'cursor-grab active:cursor-grabbing' : '',
                    dragOverIdx === idx ? 'bg-[#E8F5E9] border-t-2 border-t-[#4CAF50]' : 'hover:bg-[#FAFAFA]',
                    dragIdx === idx ? 'opacity-40' : '',
                  ]"
                >
                  <!-- 드래그 핸들 -->
                  <td v-if="isDraggable" class="pl-3 pr-0 py-3.5">
                    <svg class="w-4 h-4 text-[#CCC]" viewBox="0 0 24 24" fill="currentColor"><circle cx="9" cy="5" r="1.5"/><circle cx="15" cy="5" r="1.5"/><circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="9" cy="19" r="1.5"/><circle cx="15" cy="19" r="1.5"/></svg>
                  </td>
                  <td class="px-3 py-3.5 text-[12px] text-[#999] font-mono">{{ idx + 1 }}</td>
                  <td class="px-5 py-3.5 text-[#333] font-medium">{{ challenge.title }}</td>
                  <td class="px-5 py-3.5 text-[#555]">{{ challenge.category?.name || getCategoryName(challenge.category_id) }}</td>
                  <td v-if="activeTab === 'all'" class="px-5 py-3.5">
                    <span class="px-2 py-0.5 rounded-full text-[12px] font-medium bg-indigo-50 text-indigo-600">{{ challenge.challenge_type }}</span>
                  </td>
                  <td class="px-5 py-3.5">
                    <span class="text-[13px] font-medium" :class="(challenge.questions?.length ?? 0) === 0 ? 'text-red-400' : 'text-[#555]'">{{ challenge.questions?.length ?? 0 }}개</span>
                  </td>
                  <td class="px-5 py-3.5 text-[#888] text-[13px]">{{ difficultyStars(challenge.difficulty_level) }}</td>
                  <td class="px-5 py-3.5">
                    <span class="px-2 py-0.5 rounded-full text-[11px] font-medium" :class="challenge.allow_retry !== false ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-500'">{{ challenge.allow_retry !== false ? '허용' : '1회만' }}</span>
                  </td>
                  <td class="px-5 py-3.5">
                    <div class="flex items-center gap-3">
                      <button @click="goToQuestionEdit(challenge.challenge_id)" class="border border-[#4CAF50] text-[#4CAF50] hover:bg-[#4CAF50] hover:text-white rounded-[8px] px-3 py-1 text-[13px] font-medium transition-all">문항 관리</button>
                      <button @click="openEditModal(challenge)" class="text-[#4CAF50] hover:text-[#388E3C] text-[13px] font-medium transition-colors">수정</button>
                      <button @click="duplicateChallenge(challenge)" class="text-[#2196F3] hover:text-[#1976D2] text-[13px] font-medium transition-colors">복제</button>
                      <button @click="deleteChallenge(challenge)" class="text-red-500 hover:text-red-700 text-[13px] font-medium transition-colors">삭제</button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="px-5 py-2.5 border-t border-[#F0F0F0] text-[13px] text-[#888]">{{ tabChallenges.length }}개</div>
        </div>

        <!-- 미배정 챌린지 -->
        <div v-if="showUnassigned.length > 0" class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] overflow-hidden border-2 border-dashed border-orange-200">
          <div class="px-5 py-2.5 bg-orange-50 border-b border-orange-200 flex items-center gap-2">
            <span class="text-[13px] font-bold text-orange-500">미배정 ({{ showUnassigned.length }})</span>
            <span v-if="activeTab !== 'all'" class="text-[12px] text-orange-400">→ "{{ activeTab }}에 추가" 버튼으로 바로 배정</span>
            <span v-else class="text-[12px] text-orange-400">주차 탭을 선택하면 원클릭 배정할 수 있습니다</span>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-left text-[14px]">
              <tbody>
                <tr v-for="ch in showUnassigned" :key="ch.challenge_id" class="border-b border-[#F0F0F0] hover:bg-orange-50/30 transition-colors">
                  <td class="px-3 py-3.5 w-[40px]">
                    <span class="w-5 h-5 flex items-center justify-center rounded-full bg-orange-100 text-orange-400 text-[10px]">!</span>
                  </td>
                  <td class="px-5 py-3.5 text-[#333] font-medium">{{ ch.title }}</td>
                  <td class="px-5 py-3.5 text-[#555]">{{ ch.category?.name || getCategoryName(ch.category_id) }}</td>
                  <td class="px-5 py-3.5">
                    <span class="text-[13px] font-medium" :class="(ch.questions?.length ?? 0) === 0 ? 'text-red-400' : 'text-[#555]'">{{ ch.questions?.length ?? 0 }}개</span>
                  </td>
                  <td class="px-5 py-3.5">
                    <div class="flex items-center gap-2">
                      <!-- 주차 탭 선택 중이면 원클릭 배정 -->
                      <button v-if="activeTab !== 'all'" @click="assignToCurrentTab(ch)" class="bg-[#4CAF50] hover:bg-[#388E3C] text-white rounded-[8px] px-3 py-1 text-[13px] font-medium transition-all">
                        {{ activeTab }}에 추가
                      </button>
                      <button v-else @click="openEditModal(ch)" class="bg-orange-500 hover:bg-orange-600 text-white rounded-[8px] px-3 py-1 text-[13px] font-medium transition-all">주차 배정</button>
                      <button @click="goToQuestionEdit(ch.challenge_id)" class="text-[#4CAF50] hover:text-[#388E3C] text-[13px] font-medium transition-colors">문항</button>
                      <button @click="deleteChallenge(ch)" class="text-red-500 hover:text-red-700 text-[13px] font-medium transition-colors">삭제</button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- 챌린지 모달 -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/40" @click="closeModal"></div>
        <div class="relative bg-white rounded-[16px] shadow-[0_0_30px_rgba(0,0,0,0.2)] w-full max-w-[520px] mx-4 p-6">
          <h2 class="text-[18px] font-bold text-[#333] mb-5">{{ modalMode === 'edit' ? '챌린지 수정' : '챌린지 추가' }}</h2>
          <div v-if="modalError" class="mb-4 bg-red-50 border border-red-200 rounded-[12px] px-4 py-3 text-red-600 text-[14px]">{{ modalError }}</div>
          <form @submit.prevent="handleSubmit">
            <div class="mb-4">
              <label class="block text-[14px] font-semibold text-[#333] mb-1.5">제목</label>
              <input v-model="form.title" type="text" placeholder="챌린지 제목" class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] px-4 py-3 text-[15px] focus:border-[#4CAF50] focus:outline-none transition-colors" />
            </div>
            <div class="mb-4">
              <label class="block text-[14px] font-semibold text-[#333] mb-1.5">카테고리</label>
              <select v-model="form.category_id" class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] px-4 py-3 text-[15px] focus:border-[#4CAF50] focus:outline-none transition-colors">
                <option value="" disabled>카테고리 선택</option>
                <option v-for="cat in categories" :key="cat.category_id" :value="cat.category_id">{{ cat.name }}</option>
              </select>
            </div>
            <div class="mb-4">
              <label class="block text-[14px] font-semibold text-[#333] mb-1.5">주차 구분</label>
              <select v-model="form.challenge_type" class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] px-4 py-3 text-[15px] focus:border-[#4CAF50] focus:outline-none transition-colors">
                <option value="">미배정</option>
                <option v-for="opt in CHALLENGE_TYPE_OPTIONS" :key="opt" :value="opt">{{ opt }}</option>
              </select>
            </div>
            <div class="mb-5">
              <label class="block text-[14px] font-semibold text-[#333] mb-1.5">난이도 ({{ form.difficulty_level }}/5)</label>
              <div class="flex items-center gap-3">
                <input v-model.number="form.difficulty_level" type="range" min="1" max="5" step="1" class="flex-1 accent-[#4CAF50]" />
                <span class="text-[14px] text-[#888] w-[100px] text-right">{{ '★'.repeat(form.difficulty_level) }}{{ '☆'.repeat(5 - form.difficulty_level) }}</span>
              </div>
            </div>
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
            <div class="flex items-center gap-3">
              <button type="submit" :disabled="saving" class="bg-[#4CAF50] hover:bg-[#388E3C] disabled:opacity-50 text-white rounded-[12px] px-6 py-3 text-[15px] font-medium active:scale-[0.98] transition-all">{{ saving ? '저장 중...' : modalMode === 'edit' ? '수정하기' : '추가하기' }}</button>
              <button type="button" @click="closeModal" class="bg-[#F8F8F8] hover:bg-[#E8E8E8] text-[#555] rounded-[12px] px-6 py-3 text-[15px] font-medium active:scale-[0.98] transition-all">취소</button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </div>
</template>
