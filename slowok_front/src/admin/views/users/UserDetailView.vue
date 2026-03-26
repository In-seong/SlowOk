<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@shared/api'
import { contentAssignmentApi } from '@shared/api/contentAssignmentApi'
import { learnerMemoApi } from '@shared/api/learnerMemoApi'
import type { ApiResponse, UserDetailData, LearnerMemo } from '@shared/types'
import { useToastStore } from '@shared/stores/toastStore'

const route = useRoute()
const router = useRouter()
const toast = useToastStore()
const userId = computed(() => Number(route.params.id))

const user = ref<UserDetailData | null>(null)
const loading = ref(true)
const error = ref('')
const activeTab = ref('info')

// 프로필별 할당 필터
const selectedProfileId = ref<number | null>(null)

function allProfiles(): import('@shared/types').UserProfile[] {
  if (!user.value) return []
  return user.value.profiles ?? (user.value.profile ? [user.value.profile] : [])
}

function profileName(p: import('@shared/types').UserProfile): string {
  return p.decrypted_name ?? p.name ?? '-'
}

const assignmentTypeFilter = ref<string | null>(null)

function filteredAssignments() {
  let all = user.value?.assignments ?? []
  if (selectedProfileId.value) {
    all = all.filter(a => a.profile_id === selectedProfileId.value)
  }
  if (assignmentTypeFilter.value) {
    all = all.filter(a => a.assignable_type === assignmentTypeFilter.value)
  }
  // 챌린지 필터일 때 sort_order 순 정렬
  if (assignmentTypeFilter.value === 'challenge') {
    all = [...all].sort((a, b) => ((a as any).sort_order ?? 0) - ((b as any).sort_order ?? 0))
  }
  return all
}

const reordering = ref(false)

async function moveAssignment(assignmentId: number, direction: 'up' | 'down') {
  const list = filteredAssignments()
  const idx = list.findIndex(a => a.assignment_id === assignmentId)
  if (idx < 0) return
  const swapIdx = direction === 'up' ? idx - 1 : idx + 1
  if (swapIdx < 0 || swapIdx >= list.length) return

  const current = list[idx]!
  const swap = list[swapIdx]!

  // 순서 교환
  const orders = [
    { assignment_id: current.assignment_id, sort_order: (swap as any).sort_order ?? swapIdx },
    { assignment_id: swap.assignment_id, sort_order: (current as any).sort_order ?? idx },
  ]

  reordering.value = true
  try {
    await contentAssignmentApi.reorder(orders)
    await fetchUser()
  } catch (e: any) {
    toast.error(e.response?.data?.message || '순서 변경에 실패했습니다.')
  } finally {
    reordering.value = false
  }
}

// 수정 모달
const showEditModal = ref(false)
const editForm = ref({ name: '', phone: '', email: '' })
const editLoading = ref(false)
const editError = ref('')
const editSuccess = ref('')

// 비밀번호 초기화
const showResetPwModal = ref(false)
const resetPwLoading = ref(false)
const tempPassword = ref('')

// 메모
const memos = ref<LearnerMemo[]>([])
const memoLoading = ref(false)
const showMemoModal = ref(false)
const editingMemo = ref<LearnerMemo | null>(null)
const memoForm = ref({ category: 'general' as LearnerMemo['category'], content: '', is_pinned: false })

const categoryLabels: Record<string, string> = {
  observation: '관찰',
  consultation: '상담',
  handover: '인수인계',
  general: '일반',
}
const categoryColors: Record<string, string> = {
  observation: 'bg-blue-50 text-blue-600',
  consultation: 'bg-purple-50 text-purple-600',
  handover: 'bg-orange-50 text-orange-600',
  general: 'bg-gray-100 text-gray-600',
}

const tabs = [
  { key: 'info', label: '기본정보' },
  { key: 'assignments', label: '할당 콘텐츠' },
  { key: 'screening', label: '진단결과' },
  { key: 'learning', label: '학습진도' },
  { key: 'challenges', label: '챌린지결과' },
  { key: 'memos', label: '메모' },
]

async function fetchUser() {
  loading.value = true
  error.value = ''
  try {
    const res = await api.get<ApiResponse<UserDetailData>>(`/admin/users/${userId.value}`)
    if (res.data.success) {
      user.value = res.data.data
    }
  } catch (e: any) {
    error.value = e.response?.data?.message || '사용자 정보를 불러오지 못했습니다.'
  } finally {
    loading.value = false
  }
}

async function removeAssignment(assignmentId: number) {
  if (!confirm('할당을 해제하시겠습니까?')) return
  try {
    await contentAssignmentApi.deleteAssignment(assignmentId)
    await fetchUser()
  } catch (e: any) {
    toast.error(e.response?.data?.message || '해제에 실패했습니다.')
  }
}

function openEditModal() {
  if (!user.value) return
  editForm.value = {
    name: user.value.profile?.decrypted_name || user.value.profile?.name || '',
    phone: user.value.profile?.decrypted_phone || user.value.profile?.phone || '',
    email: user.value.profile?.decrypted_email || user.value.profile?.email || '',
  }
  editError.value = ''
  editSuccess.value = ''
  showEditModal.value = true
}

async function handleEditSubmit() {
  editLoading.value = true
  editError.value = ''
  editSuccess.value = ''
  try {
    const res = await api.put(`/admin/users/${userId.value}`, editForm.value)
    if (res.data.success) {
      editSuccess.value = '사용자 정보가 수정되었습니다.'
      await fetchUser()
      setTimeout(() => { showEditModal.value = false }, 1000)
    }
  } catch (e: any) {
    editError.value = e.response?.data?.message || '수정에 실패했습니다.'
  } finally {
    editLoading.value = false
  }
}

async function handleResetPassword() {
  resetPwLoading.value = true
  tempPassword.value = ''
  try {
    const res = await api.post(`/admin/users/${userId.value}/reset-password`)
    if (res.data.success) {
      tempPassword.value = res.data.data.temp_password
    }
  } catch (e: any) {
    toast.error(e.response?.data?.message || '비밀번호 초기화에 실패했습니다.')
    showResetPwModal.value = false
  } finally {
    resetPwLoading.value = false
  }
}

function formatDate(d: string | null | undefined): string {
  if (!d) return '-'
  return new Date(d).toLocaleDateString('ko-KR')
}

function assignableTypeLabel(type: string): string {
  const map: Record<string, string> = {
    screening_test: '진단',
    learning_content: '학습 콘텐츠',
    challenge: '챌린지',
  }
  return map[type] ?? type
}

async function fetchMemos() {
  if (!user.value?.profile) return
  memoLoading.value = true
  try {
    const res = await learnerMemoApi.list(user.value.profile.profile_id)
    if (res.data.success) {
      memos.value = res.data.data
    }
  } catch {
    toast.error('메모를 불러오지 못했습니다.')
  } finally {
    memoLoading.value = false
  }
}

function openMemoModal(memo?: LearnerMemo) {
  if (memo) {
    editingMemo.value = memo
    memoForm.value = { category: memo.category, content: memo.content, is_pinned: memo.is_pinned }
  } else {
    editingMemo.value = null
    memoForm.value = { category: 'general', content: '', is_pinned: false }
  }
  showMemoModal.value = true
}

async function handleMemoSubmit() {
  if (!user.value?.profile) return
  try {
    if (editingMemo.value) {
      await learnerMemoApi.update(editingMemo.value.memo_id, memoForm.value)
      toast.success('메모가 수정되었습니다.')
    } else {
      await learnerMemoApi.create(user.value.profile.profile_id, memoForm.value)
      toast.success('메모가 등록되었습니다.')
    }
    showMemoModal.value = false
    await fetchMemos()
  } catch (e: any) {
    toast.error(e.response?.data?.message || '메모 저장에 실패했습니다.')
  }
}

async function deleteMemo(memoId: number) {
  if (!confirm('메모를 삭제하시겠습니까?')) return
  try {
    await learnerMemoApi.remove(memoId)
    toast.success('메모가 삭제되었습니다.')
    await fetchMemos()
  } catch (e: any) {
    toast.error(e.response?.data?.message || '메모 삭제에 실패했습니다.')
  }
}

async function handleTogglePin(memoId: number) {
  try {
    await learnerMemoApi.togglePin(memoId)
    await fetchMemos()
  } catch {
    toast.error('고정 상태 변경에 실패했습니다.')
  }
}

watch(activeTab, (tab) => {
  if (tab === 'memos' && memos.value.length === 0) {
    fetchMemos()
  }
})

onMounted(fetchUser)
</script>

<template>
  <div class="p-6">
    <div class="flex items-center gap-3 mb-6">
      <button @click="router.push({ name: 'users' })" class="text-[#888] hover:text-[#333]">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
        </svg>
      </button>
      <h2 class="text-xl font-bold text-[#333]">고객 상세</h2>
    </div>

    <div v-if="loading" class="text-center py-10 text-[#888]">불러오는 중...</div>
    <div v-else-if="error" class="text-center py-10 text-red-500">{{ error }}</div>
    <div v-else-if="user">
      <!-- 탭 네비게이션 -->
      <div class="flex gap-1 mb-4 overflow-x-auto">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          @click="activeTab = tab.key"
          class="px-4 py-2 rounded-[10px] text-[14px] whitespace-nowrap transition-colors"
          :class="activeTab === tab.key ? 'bg-[#4CAF50] text-white font-medium' : 'bg-[#F0F0F0] text-[#666] hover:bg-[#E0E0E0]'"
        >
          {{ tab.label }}
        </button>
      </div>

      <!-- 기본정보 탭 -->
      <div v-if="activeTab === 'info'" class="space-y-4">
        <div class="bg-white rounded-[16px] border border-[#E8E8E8] p-6">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-[14px]">
            <div><span class="text-[#888]">아이디:</span> <span class="ml-2 text-[#333]">{{ user.username }}</span></div>
            <div><span class="text-[#888]">상태:</span>
              <span class="ml-2 px-2 py-0.5 rounded-full text-[12px] font-medium" :class="user.is_active ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-500'">
                {{ user.is_active ? '활성' : '비활성' }}
              </span>
            </div>
            <div><span class="text-[#888]">마지막 로그인:</span> <span class="ml-2 text-[#333]">{{ formatDate(user.last_login_at) }}</span></div>
          </div>

          <!-- 액션 버튼 -->
          <div class="flex gap-3 mt-6 pt-4 border-t border-[#F0F0F0]">
            <button class="px-4 py-2 rounded-[10px] text-[13px] font-medium bg-[#4CAF50] text-white hover:bg-[#388E3C] transition-colors" @click="openEditModal">정보 수정</button>
            <button class="px-4 py-2 rounded-[10px] text-[13px] font-medium bg-[#FF9800] text-white hover:bg-[#F57C00] transition-colors" @click="showResetPwModal = true; tempPassword = ''">비밀번호 초기화</button>
          </div>
        </div>

        <!-- 프로필 목록 -->
        <div class="bg-white rounded-[16px] border border-[#E8E8E8] p-6">
          <h3 class="text-[15px] font-bold text-[#333] mb-4">프로필 ({{ allProfiles().length }}개)</h3>
          <div class="space-y-3">
            <div
              v-for="p in allProfiles()"
              :key="p.profile_id"
              class="flex items-center gap-3 p-3 rounded-[10px] bg-[#FAFAFA]"
            >
              <div class="w-9 h-9 rounded-full flex items-center justify-center text-[13px] font-bold text-white" :class="p.user_type === 'PARENT' ? 'bg-purple-400' : 'bg-blue-400'">
                {{ profileName(p).charAt(0) }}
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                  <span class="text-[14px] font-medium text-[#333]">{{ profileName(p) }}</span>
                  <span class="px-2 py-0.5 rounded-full text-[11px] font-medium" :class="p.user_type === 'PARENT' ? 'bg-purple-50 text-purple-600' : 'bg-blue-50 text-blue-600'">
                    {{ p.user_type === 'PARENT' ? '보호자' : '학습자' }}
                  </span>
                </div>
                <div class="text-[12px] text-[#888] mt-0.5">
                  {{ p.decrypted_phone ?? p.phone ?? '' }}
                  {{ p.decrypted_email ?? p.email ?? '' }}
                </div>
              </div>
            </div>
            <div v-if="allProfiles().length === 0" class="text-center text-[13px] text-[#888] py-3">프로필이 없습니다.</div>
          </div>
        </div>
      </div>

      <!-- 할당 콘텐츠 탭 -->
      <div v-if="activeTab === 'assignments'" class="space-y-3">
        <!-- 필터 영역 -->
        <div class="flex flex-wrap gap-3">
          <!-- 프로필 필터 -->
          <div v-if="allProfiles().length > 1" class="flex gap-1.5 flex-wrap">
            <button
              class="px-3 py-1.5 rounded-[8px] text-[13px] transition-colors"
              :class="!selectedProfileId ? 'bg-[#4CAF50] text-white' : 'bg-[#F0F0F0] text-[#666] hover:bg-[#E0E0E0]'"
              @click="selectedProfileId = null"
            >
              전체
            </button>
            <button
              v-for="p in allProfiles()"
              :key="p.profile_id"
              class="px-3 py-1.5 rounded-[8px] text-[13px] transition-colors"
              :class="selectedProfileId === p.profile_id ? 'bg-[#4CAF50] text-white' : 'bg-[#F0F0F0] text-[#666] hover:bg-[#E0E0E0]'"
              @click="selectedProfileId = p.profile_id"
            >
              {{ profileName(p) }}
              <span class="text-[11px] opacity-70">({{ p.user_type === 'PARENT' ? '보호자' : '학습자' }})</span>
            </button>
          </div>

          <!-- 유형 필터 -->
          <div class="flex gap-1.5 ml-auto">
            <button
              class="px-2.5 py-1.5 rounded-[8px] text-[12px] transition-colors"
              :class="!assignmentTypeFilter ? 'bg-[#333] text-white' : 'bg-[#F0F0F0] text-[#666] hover:bg-[#E0E0E0]'"
              @click="assignmentTypeFilter = null"
            >전체</button>
            <button
              class="px-2.5 py-1.5 rounded-[8px] text-[12px] transition-colors"
              :class="assignmentTypeFilter === 'challenge' ? 'bg-orange-500 text-white' : 'bg-[#F0F0F0] text-[#666] hover:bg-[#E0E0E0]'"
              @click="assignmentTypeFilter = 'challenge'"
            >챌린지</button>
            <button
              class="px-2.5 py-1.5 rounded-[8px] text-[12px] transition-colors"
              :class="assignmentTypeFilter === 'learning_content' ? 'bg-green-500 text-white' : 'bg-[#F0F0F0] text-[#666] hover:bg-[#E0E0E0]'"
              @click="assignmentTypeFilter = 'learning_content'"
            >학습</button>
            <button
              class="px-2.5 py-1.5 rounded-[8px] text-[12px] transition-colors"
              :class="assignmentTypeFilter === 'screening_test' ? 'bg-blue-500 text-white' : 'bg-[#F0F0F0] text-[#666] hover:bg-[#E0E0E0]'"
              @click="assignmentTypeFilter = 'screening_test'"
            >진단</button>
          </div>
        </div>

        <!-- 순서 변경 안내 (전체 보기일 때) -->
        <div v-if="!assignmentTypeFilter" class="flex items-center gap-2 px-4 py-2.5 bg-[#FFF8E1] rounded-[10px] text-[12px] text-[#E65100]">
          <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
          챌린지 순서를 변경하려면 위 필터에서 <button class="font-bold underline underline-offset-2" @click="assignmentTypeFilter = 'challenge'">"챌린지"</button>를 선택하세요
        </div>

        <div class="bg-white rounded-[16px] border border-[#E8E8E8] overflow-hidden">
          <div v-if="filteredAssignments().length === 0" class="p-6 text-center text-[#888]">할당된 콘텐츠가 없습니다.</div>
          <p v-if="assignmentTypeFilter === 'challenge'" class="px-4 py-2 text-[12px] text-[#888] bg-[#FAFAFA] border-b border-[#E8E8E8]">
            ↕ 화살표 버튼으로 챌린지 순서를 변경할 수 있습니다 (학습자 레벨맵에 반영됨)
          </p>
          <table v-else-if="filteredAssignments().length > 0" class="hidden"></table>
          <table v-if="filteredAssignments().length > 0" class="w-full text-[14px]">
            <thead>
              <tr class="bg-[#F8F8F8] text-[#666]">
                <th v-if="assignmentTypeFilter === 'challenge'" class="text-center px-2 py-3 font-medium w-[70px]">순서</th>
                <th class="text-left px-4 py-3 font-medium">유형</th>
                <th class="text-left px-4 py-3 font-medium">콘텐츠명</th>
                <th v-if="allProfiles().length > 1 && !selectedProfileId" class="text-left px-4 py-3 font-medium">프로필</th>
                <th class="text-left px-4 py-3 font-medium">상태</th>
                <th class="text-left px-4 py-3 font-medium">할당일</th>
                <th class="text-center px-4 py-3 font-medium">관리</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(a, idx) in filteredAssignments()" :key="a.assignment_id" class="border-t border-[#F0F0F0]">
                <td v-if="assignmentTypeFilter === 'challenge'" class="px-2 py-3 text-center">
                  <div class="flex flex-col items-center gap-0.5">
                    <button
                      :disabled="idx === 0 || reordering"
                      @click.stop="moveAssignment(a.assignment_id, 'up')"
                      class="w-6 h-6 rounded flex items-center justify-center text-[#999] hover:bg-[#F0F0F0] disabled:opacity-30 transition-colors"
                    >
                      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" /></svg>
                    </button>
                    <span class="text-[11px] text-[#BBB] font-mono">{{ idx + 1 }}</span>
                    <button
                      :disabled="idx === filteredAssignments().length - 1 || reordering"
                      @click.stop="moveAssignment(a.assignment_id, 'down')"
                      class="w-6 h-6 rounded flex items-center justify-center text-[#999] hover:bg-[#F0F0F0] disabled:opacity-30 transition-colors"
                    >
                      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                    </button>
                  </div>
                </td>
                <td class="px-4 py-3">
                  <span class="px-2 py-0.5 rounded-full text-[11px] font-medium" :class="{
                    'bg-blue-50 text-blue-600': a.assignable_type === 'screening_test',
                    'bg-green-50 text-green-600': a.assignable_type === 'learning_content',
                    'bg-orange-50 text-orange-600': a.assignable_type === 'challenge',
                  }">
                    {{ assignableTypeLabel(a.assignable_type) }}
                  </span>
                </td>
                <td class="px-4 py-3 font-medium text-[#333]">{{ a.assignable_title || `#${a.assignable_id}` }}</td>
                <td v-if="allProfiles().length > 1 && !selectedProfileId" class="px-4 py-3 text-[12px] text-[#888]">
                  {{ a.profile ? (a.profile.decrypted_name ?? a.profile.name ?? '-') : '-' }}
                </td>
                <td class="px-4 py-3">
                  <span class="px-2 py-0.5 rounded-full text-[12px]"
                    :class="a.status === 'COMPLETED' ? 'bg-green-50 text-green-600' : a.status === 'IN_PROGRESS' ? 'bg-blue-50 text-blue-600' : 'bg-gray-100 text-gray-600'">
                    {{ a.status ?? 'ASSIGNED' }}
                  </span>
                </td>
                <td class="px-4 py-3 text-[#888]">{{ formatDate(a.assigned_at) }}</td>
                <td class="px-4 py-3 text-center">
                  <button @click="removeAssignment(a.assignment_id)" class="text-red-500 hover:underline text-[13px]">해제</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- 진단결과 탭 -->
      <div v-if="activeTab === 'screening'" class="bg-white rounded-[16px] border border-[#E8E8E8] overflow-hidden">
        <div v-if="!user.screening_results?.length" class="p-6 text-center text-[#888]">진단 결과가 없습니다.</div>
        <table v-else class="w-full text-[14px]">
          <thead>
            <tr class="bg-[#F8F8F8] text-[#666]">
              <th class="text-left px-4 py-3 font-medium">진단명</th>
              <th class="text-center px-4 py-3 font-medium">점수</th>
              <th class="text-center px-4 py-3 font-medium">레벨</th>
              <th class="text-left px-4 py-3 font-medium">일자</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in user.screening_results" :key="r.result_id" class="border-t border-[#F0F0F0]">
              <td class="px-4 py-3">{{ r.test?.title ?? '-' }}</td>
              <td class="px-4 py-3 text-center font-medium">{{ r.score }}점</td>
              <td class="px-4 py-3 text-center">{{ r.level ?? '-' }}</td>
              <td class="px-4 py-3">{{ formatDate(r.created_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- 학습진도 탭 -->
      <div v-if="activeTab === 'learning'" class="bg-white rounded-[16px] border border-[#E8E8E8] overflow-hidden">
        <div v-if="!user.learning_progress?.length" class="p-6 text-center text-[#888]">학습 기록이 없습니다.</div>
        <table v-else class="w-full text-[14px]">
          <thead>
            <tr class="bg-[#F8F8F8] text-[#666]">
              <th class="text-left px-4 py-3 font-medium">콘텐츠</th>
              <th class="text-center px-4 py-3 font-medium">상태</th>
              <th class="text-center px-4 py-3 font-medium">점수</th>
              <th class="text-center px-4 py-3 font-medium">시도횟수</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in user.learning_progress" :key="p.progress_id" class="border-t border-[#F0F0F0]">
              <td class="px-4 py-3">{{ p.content?.title ?? '-' }}</td>
              <td class="px-4 py-3 text-center">
                <span class="px-2 py-0.5 rounded-full text-[12px]"
                  :class="p.status === 'COMPLETED' ? 'bg-green-50 text-green-600' : p.status === 'IN_PROGRESS' ? 'bg-blue-50 text-blue-600' : 'bg-gray-100 text-gray-600'">
                  {{ p.status }}
                </span>
              </td>
              <td class="px-4 py-3 text-center">{{ p.score ?? '-' }}</td>
              <td class="px-4 py-3 text-center">{{ p.attempts }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- 챌린지결과 탭 -->
      <div v-if="activeTab === 'challenges'" class="bg-white rounded-[16px] border border-[#E8E8E8] overflow-hidden">
        <div v-if="!user.challenge_attempts?.length" class="p-6 text-center text-[#888]">챌린지 기록이 없습니다.</div>
        <table v-else class="w-full text-[14px]">
          <thead>
            <tr class="bg-[#F8F8F8] text-[#666]">
              <th class="text-left px-4 py-3 font-medium">챌린지</th>
              <th class="text-center px-4 py-3 font-medium">점수</th>
              <th class="text-center px-4 py-3 font-medium">통과</th>
              <th class="text-left px-4 py-3 font-medium">일자</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="a in user.challenge_attempts" :key="a.attempt_id" class="border-t border-[#F0F0F0]">
              <td class="px-4 py-3">{{ a.challenge?.title ?? '-' }}</td>
              <td class="px-4 py-3 text-center font-medium">{{ a.score }}점</td>
              <td class="px-4 py-3 text-center">
                <span class="px-2 py-0.5 rounded-full text-[12px]" :class="a.is_passed ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-500'">
                  {{ a.is_passed ? '통과' : '미통과' }}
                </span>
              </td>
              <td class="px-4 py-3">{{ formatDate(a.created_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- 메모 탭 -->
      <div v-if="activeTab === 'memos'">
        <div class="flex justify-between items-center mb-3">
          <p class="text-[13px] text-[#888]">내부 직원용 메모 (학부모/학습자에게 보이지 않음)</p>
          <button
            class="px-4 py-2 rounded-[10px] text-[13px] font-medium bg-[#4CAF50] text-white hover:bg-[#388E3C] transition-colors"
            @click="openMemoModal()"
          >
            메모 작성
          </button>
        </div>

        <div v-if="memoLoading" class="text-center py-10 text-[#888]">불러오는 중...</div>
        <div v-else-if="!memos.length" class="bg-white rounded-[16px] border border-[#E8E8E8] p-6 text-center text-[#888]">
          작성된 메모가 없습니다.
        </div>
        <div v-else class="space-y-3">
          <div
            v-for="memo in memos"
            :key="memo.memo_id"
            class="bg-white rounded-[16px] border p-4"
            :class="memo.is_pinned ? 'border-[#4CAF50] bg-green-50/30' : 'border-[#E8E8E8]'"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-2">
                  <span v-if="memo.is_pinned" class="text-[#4CAF50] text-[14px]">&#128204;</span>
                  <span class="px-2 py-0.5 rounded-full text-[11px] font-medium" :class="categoryColors[memo.category] || 'bg-gray-100 text-gray-600'">
                    {{ categoryLabels[memo.category] || memo.category }}
                  </span>
                  <span class="text-[12px] text-[#999]">{{ memo.account?.username || '-' }}</span>
                  <span class="text-[12px] text-[#CCC]">|</span>
                  <span class="text-[12px] text-[#999]">{{ formatDate(memo.created_at) }}</span>
                </div>
                <p class="text-[14px] text-[#333] whitespace-pre-wrap">{{ memo.content }}</p>
              </div>
              <div class="flex gap-1 shrink-0">
                <button
                  class="p-1.5 rounded-[8px] text-[#999] hover:bg-[#F0F0F0] transition-colors"
                  :class="memo.is_pinned ? 'text-[#4CAF50]' : ''"
                  title="고정 토글"
                  @click="handleTogglePin(memo.memo_id)"
                >
                  <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a1 1 0 011 1v1.323l3.954 1.582 1.599-.8a1 1 0 01.894 1.79l-1.233.616 1.738 5.42a1 1 0 01-.285 1.05A3.989 3.989 0 0115 15a3.989 3.989 0 01-2.667-1.019 1 1 0 01-.285-1.05l1.715-5.349L11 6.477V16h2a1 1 0 110 2H7a1 1 0 110-2h2V6.477L6.237 7.582l1.715 5.349a1 1 0 01-.285 1.05A3.989 3.989 0 015 15a3.989 3.989 0 01-2.667-1.019 1 1 0 01-.285-1.05l1.738-5.42-1.233-.617a1 1 0 01.894-1.789l1.599.799L9 4.323V3a1 1 0 011-1z" /></svg>
                </button>
                <button
                  class="p-1.5 rounded-[8px] text-[#999] hover:bg-[#F0F0F0] transition-colors"
                  title="수정"
                  @click="openMemoModal(memo)"
                >
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" /></svg>
                </button>
                <button
                  class="p-1.5 rounded-[8px] text-[#999] hover:bg-red-50 hover:text-red-500 transition-colors"
                  title="삭제"
                  @click="deleteMemo(memo.memo_id)"
                >
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 정보 수정 모달 -->
    <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div class="w-full max-w-md bg-white rounded-[16px] p-6 mx-4 space-y-4">
        <h3 class="text-[16px] font-bold text-[#333]">사용자 정보 수정</h3>
        <div v-if="editSuccess" class="bg-[#E8F5E9] rounded-[8px] p-2.5 text-[12px] text-[#4CAF50]">{{ editSuccess }}</div>
        <div v-if="editError" class="bg-[#FFE5E5] rounded-[8px] p-2.5 text-[12px] text-[#FF4444]">{{ editError }}</div>
        <form @submit.prevent="handleEditSubmit" class="space-y-3">
          <div>
            <label class="block text-[13px] font-medium text-[#555] mb-1">이름</label>
            <input v-model="editForm.name" type="text" class="w-full border border-[#E8E8E8] rounded-[10px] px-3 py-2.5 text-[14px] outline-none focus:border-[#4CAF50]" />
          </div>
          <div>
            <label class="block text-[13px] font-medium text-[#555] mb-1">연락처</label>
            <input v-model="editForm.phone" type="tel" class="w-full border border-[#E8E8E8] rounded-[10px] px-3 py-2.5 text-[14px] outline-none focus:border-[#4CAF50]" />
          </div>
          <div>
            <label class="block text-[13px] font-medium text-[#555] mb-1">이메일</label>
            <input v-model="editForm.email" type="email" class="w-full border border-[#E8E8E8] rounded-[10px] px-3 py-2.5 text-[14px] outline-none focus:border-[#4CAF50]" />
          </div>
          <div class="flex gap-3 pt-2">
            <button type="button" class="flex-1 py-2.5 rounded-[10px] text-[13px] font-medium bg-[#F0F0F0] text-[#666]" @click="showEditModal = false">취소</button>
            <button type="submit" :disabled="editLoading" class="flex-1 py-2.5 rounded-[10px] text-[13px] font-medium bg-[#4CAF50] text-white disabled:opacity-50">
              {{ editLoading ? '저장 중...' : '저장' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- 비밀번호 초기화 모달 -->
    <div v-if="showResetPwModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div class="w-full max-w-sm bg-white rounded-[16px] p-6 mx-4 space-y-4">
        <h3 class="text-[16px] font-bold text-[#333]">비밀번호 초기화</h3>
        <template v-if="!tempPassword">
          <p class="text-[13px] text-[#666]">해당 사용자의 비밀번호를 임시 비밀번호로 초기화합니다.</p>
          <div class="flex gap-3">
            <button class="flex-1 py-2.5 rounded-[10px] text-[13px] font-medium bg-[#F0F0F0] text-[#666]" @click="showResetPwModal = false">취소</button>
            <button :disabled="resetPwLoading" class="flex-1 py-2.5 rounded-[10px] text-[13px] font-medium bg-[#FF9800] text-white disabled:opacity-50" @click="handleResetPassword">
              {{ resetPwLoading ? '처리 중...' : '초기화' }}
            </button>
          </div>
        </template>
        <template v-else>
          <div class="bg-[#FFF3E0] rounded-[10px] p-4 text-center">
            <p class="text-[12px] text-[#888] mb-2">임시 비밀번호</p>
            <p class="text-[20px] font-bold text-[#E65100] tracking-wider font-mono">{{ tempPassword }}</p>
          </div>
          <p class="text-[12px] text-[#FF4444] text-center">이 비밀번호를 사용자에게 전달해주세요. 이 창을 닫으면 다시 확인할 수 없습니다.</p>
          <button class="w-full py-2.5 rounded-[10px] text-[13px] font-medium bg-[#4CAF50] text-white" @click="showResetPwModal = false">확인</button>
        </template>
      </div>
    </div>
    <!-- 메모 작성/수정 모달 -->
    <div v-if="showMemoModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div class="w-full max-w-md bg-white rounded-[16px] p-6 mx-4 space-y-4">
        <h3 class="text-[16px] font-bold text-[#333]">{{ editingMemo ? '메모 수정' : '메모 작성' }}</h3>
        <form @submit.prevent="handleMemoSubmit" class="space-y-3">
          <div>
            <label class="block text-[13px] font-medium text-[#555] mb-1">카테고리</label>
            <select v-model="memoForm.category" class="w-full border border-[#E8E8E8] rounded-[10px] px-3 py-2.5 text-[14px] outline-none focus:border-[#4CAF50] bg-white">
              <option value="observation">관찰</option>
              <option value="consultation">상담</option>
              <option value="handover">인수인계</option>
              <option value="general">일반</option>
            </select>
          </div>
          <div>
            <label class="block text-[13px] font-medium text-[#555] mb-1">내용</label>
            <textarea
              v-model="memoForm.content"
              rows="5"
              maxlength="2000"
              placeholder="메모 내용을 입력하세요..."
              class="w-full border border-[#E8E8E8] rounded-[10px] px-3 py-2.5 text-[14px] outline-none focus:border-[#4CAF50] resize-none"
            ></textarea>
            <p class="text-right text-[11px] text-[#CCC] mt-0.5">{{ memoForm.content.length }}/2000</p>
          </div>
          <label class="flex items-center gap-2 text-[13px] text-[#555]">
            <input type="checkbox" v-model="memoForm.is_pinned" class="accent-[#4CAF50]" />
            상단 고정
          </label>
          <div class="flex gap-3 pt-2">
            <button type="button" class="flex-1 py-2.5 rounded-[10px] text-[13px] font-medium bg-[#F0F0F0] text-[#666]" @click="showMemoModal = false">취소</button>
            <button type="submit" :disabled="!memoForm.content.trim()" class="flex-1 py-2.5 rounded-[10px] text-[13px] font-medium bg-[#4CAF50] text-white disabled:opacity-50">
              {{ editingMemo ? '수정' : '등록' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
