<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@shared/api'
import { contentAssignmentApi } from '@shared/api/contentAssignmentApi'
import { learnerMemoApi } from '@shared/api/learnerMemoApi'
import type { ApiResponse, UserDetailData, LearnerMemo } from '@shared/types'
import { useToastStore } from '@shared/stores/toastStore'
import BottomSheet from '../../components/BottomSheet.vue'

const route = useRoute()
const router = useRouter()
const toast = useToastStore()
const userId = computed(() => Number(route.params.id))

const user = ref<UserDetailData | null>(null)
const loading = ref(true)
const error = ref('')
const activeTab = ref('info')

const tabs = [
  { key: 'info', label: '기본정보' },
  { key: 'assignments', label: '할당' },
  { key: 'screening', label: '진단' },
  { key: 'learning', label: '학습' },
  { key: 'challenges', label: '챌린지' },
  { key: 'memos', label: '메모' },
]

// 할당 필터
const assignmentTypeFilter = ref<string | null>(null)

function filteredAssignments() {
  let all = user.value?.assignments ?? []
  if (assignmentTypeFilter.value) all = all.filter(a => a.assignable_type === assignmentTypeFilter.value)
  if (assignmentTypeFilter.value === 'challenge') all = [...all].sort((a, b) => ((a as any).sort_order ?? 0) - ((b as any).sort_order ?? 0))
  return all
}

// 수정 폼
const showEditSheet = ref(false)
const editForm = ref({ username: '', password: '', name: '', phone: '', email: '' })
const editLoading = ref(false)

// 비밀번호 초기화
const showResetPw = ref(false)
const resetPwLoading = ref(false)
const tempPassword = ref('')

// 메모
const memos = ref<LearnerMemo[]>([])
const memoLoading = ref(false)
const showMemoSheet = ref(false)
const editingMemo = ref<LearnerMemo | null>(null)
const memoForm = ref({ category: 'general' as LearnerMemo['category'], content: '', is_pinned: false })
const memoCategoryFilter = ref<string | null>(null)

const categoryLabels: Record<string, string> = { observation: '관찰', consultation: '상담', handover: '인수인계', general: '일반' }
const categoryColors: Record<string, string> = { observation: 'bg-blue-50 text-blue-600', consultation: 'bg-purple-50 text-purple-600', handover: 'bg-orange-50 text-orange-600', general: 'bg-gray-100 text-gray-600' }

const filteredMemos = computed(() => {
  let list = [...memos.value].sort((a, b) => {
    if (a.is_pinned !== b.is_pinned) return a.is_pinned ? -1 : 1
    return 0
  })
  if (memoCategoryFilter.value) list = list.filter(m => m.category === memoCategoryFilter.value)
  return list
})

async function fetchUser() {
  loading.value = true
  error.value = ''
  try {
    const res = await api.get<ApiResponse<UserDetailData>>(`/admin/users/${userId.value}`)
    if (res.data.success) user.value = res.data.data
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    error.value = err.response?.data?.message || '사용자 정보를 불러오지 못했습니다.'
  } finally {
    loading.value = false
  }
}

function displayName(): string {
  if (!user.value) return ''
  return user.value.profile?.decrypted_name || user.value.profile?.name || user.value.username
}

function formatDate(d: string | null | undefined): string {
  if (!d) return '-'
  return new Date(d).toLocaleDateString('ko-KR')
}

function assignableTypeLabel(type: string): string {
  return { screening_test: '진단', learning_content: '학습', challenge: '챌린지' }[type] ?? type
}

function statusColor(status: string): string {
  return { COMPLETED: 'bg-green-50 text-green-600', IN_PROGRESS: 'bg-blue-50 text-blue-600', ASSIGNED: 'bg-gray-100 text-[#888]' }[status] ?? 'bg-gray-100 text-[#888]'
}

function levelColor(level: string | null): string {
  return { '우수': 'text-[#4CAF50]', '양호': 'text-[#2196F3]', '보통': 'text-[#FF9800]', '주의': 'text-[#FF4444]' }[level ?? ''] ?? 'text-[#888]'
}

// 수정
function openEditSheet() {
  if (!user.value) return
  editForm.value = {
    username: user.value.username || '',
    password: '',
    name: user.value.profile?.decrypted_name || user.value.profile?.name || '',
    phone: user.value.profile?.decrypted_phone || user.value.profile?.phone || '',
    email: user.value.profile?.decrypted_email || user.value.profile?.email || '',
  }
  showEditSheet.value = true
}

async function handleEditSubmit() {
  editLoading.value = true
  try {
    const payload: Record<string, unknown> = { ...editForm.value }
    if (!payload.password) delete payload.password
    const res = await api.put(`/admin/users/${userId.value}`, payload)
    if (res.data.success) {
      toast.success('수정되었습니다.')
      showEditSheet.value = false
      await fetchUser()
    }
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    toast.error(err.response?.data?.message || '수정에 실패했습니다.')
  } finally {
    editLoading.value = false
  }
}

// 비밀번호 초기화
async function handleResetPassword() {
  resetPwLoading.value = true
  tempPassword.value = ''
  try {
    const res = await api.post(`/admin/users/${userId.value}/reset-password`)
    if (res.data.success) tempPassword.value = res.data.data.temp_password
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    toast.error(err.response?.data?.message || '초기화에 실패했습니다.')
    showResetPw.value = false
  } finally {
    resetPwLoading.value = false
  }
}

// 할당
async function removeAssignment(id: number) {
  if (!confirm('할당을 해제하시겠습니까?')) return
  try {
    await contentAssignmentApi.deleteAssignment(id)
    await fetchUser()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    toast.error(err.response?.data?.message || '해제에 실패했습니다.')
  }
}

async function resetAttempts(assignmentId: number, title: string) {
  if (!confirm(`"${title}" 챌린지 결과를 초기화하시겠습니까?`)) return
  try {
    const res = await api.post(`/admin/content-assignments/${assignmentId}/reset`)
    if (res.data.success) { toast.success('초기화 완료'); await fetchUser() }
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    toast.error(err.response?.data?.message || '초기화에 실패했습니다.')
  }
}

async function toggleRetry(assignmentId: number, currentValue: boolean) {
  try {
    await api.put(`/admin/content-assignments/${assignmentId}`, { allow_retry: !currentValue })
    await fetchUser()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    toast.error(err.response?.data?.message || '변경에 실패했습니다.')
  }
}

// 메모
async function fetchMemos() {
  if (!user.value?.profile) return
  memoLoading.value = true
  try {
    const res = await learnerMemoApi.list(user.value.profile.profile_id)
    if (res.data.success) memos.value = res.data.data
  } catch { toast.error('메모를 불러오지 못했습니다.') }
  finally { memoLoading.value = false }
}

function openMemoSheet(memo?: LearnerMemo) {
  if (memo) {
    editingMemo.value = memo
    memoForm.value = { category: memo.category, content: memo.content, is_pinned: memo.is_pinned }
  } else {
    editingMemo.value = null
    memoForm.value = { category: 'general', content: '', is_pinned: false }
  }
  showMemoSheet.value = true
}

async function handleMemoSubmit() {
  if (!user.value?.profile) return
  if (!memoForm.value.content.trim()) { toast.error('내용을 입력해주세요.'); return }
  try {
    if (editingMemo.value) {
      await learnerMemoApi.update(editingMemo.value.memo_id, memoForm.value)
    } else {
      await learnerMemoApi.create(user.value.profile.profile_id, memoForm.value)
    }
    showMemoSheet.value = false
    await fetchMemos()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    toast.error(err.response?.data?.message || '저장에 실패했습니다.')
  }
}

async function deleteMemo(id: number) {
  if (!confirm('메모를 삭제하시겠습니까?')) return
  try {
    await learnerMemoApi.remove(id)
    await fetchMemos()
  } catch { toast.error('삭제에 실패했습니다.') }
}

async function togglePin(id: number) {
  try { await learnerMemoApi.togglePin(id); await fetchMemos() }
  catch { toast.error('고정 상태 변경에 실패했습니다.') }
}

watch(activeTab, (tab) => { if (tab === 'memos' && memos.value.length === 0) fetchMemos() })

onMounted(fetchUser)
</script>

<template>
  <div class="min-h-screen bg-[#FAFAFA]">
    <!-- 헤더 -->
    <header class="sticky top-0 z-40 bg-white border-b border-[#E8E8E8] h-[56px] flex items-center px-4">
      <button @click="router.back()" class="w-10 h-10 flex items-center justify-center">
        <svg class="w-5 h-5 text-[#333]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
      </button>
      <h1 class="flex-1 text-center text-[16px] font-bold text-[#333] truncate px-2">{{ displayName() }}</h1>
      <div class="w-10" />
    </header>

    <!-- 탭바 -->
    <div class="sticky top-[56px] z-30 bg-white border-b border-[#E8E8E8] px-4 py-2">
      <div class="flex gap-1.5 overflow-x-auto">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          @click="activeTab = tab.key"
          class="shrink-0 px-4 py-2 rounded-[10px] text-[13px] font-medium transition-colors"
          :class="activeTab === tab.key ? 'bg-[#4CAF50] text-white' : 'bg-[#F0F0F0] text-[#666]'"
        >{{ tab.label }}</button>
      </div>
    </div>

    <div v-if="loading" class="flex justify-center py-20">
      <div class="w-8 h-8 border-3 border-[#4CAF50] border-t-transparent rounded-full animate-spin" />
    </div>
    <div v-else-if="error" class="px-4 py-10 text-center">
      <p class="text-[14px] text-[#FF4444]">{{ error }}</p>
    </div>

    <div v-else-if="user" class="px-4 py-4">
      <!-- 기본정보 -->
      <div v-if="activeTab === 'info'" class="space-y-4">
        <div class="bg-white rounded-[16px] shadow-sm border border-[#E8E8E8] p-4 space-y-3">
          <div class="flex justify-between"><span class="text-[13px] text-[#888]">아이디</span><span class="text-[14px] text-[#333]">{{ user.username }}</span></div>
          <div class="flex justify-between"><span class="text-[13px] text-[#888]">상태</span><span class="text-[14px]" :class="user.is_active ? 'text-[#4CAF50]' : 'text-[#FF4444]'">{{ user.is_active ? '활성' : '비활성' }}</span></div>
          <div class="flex justify-between"><span class="text-[13px] text-[#888]">마지막 로그인</span><span class="text-[14px] text-[#333]">{{ formatDate(user.last_login_at) }}</span></div>
        </div>

        <div class="flex gap-3">
          <button @click="openEditSheet" class="flex-1 py-3 bg-[#4CAF50] text-white rounded-[12px] text-[14px] font-medium active:bg-[#388E3C]">정보 수정</button>
          <button @click="showResetPw = true" class="flex-1 py-3 bg-[#F0F0F0] text-[#555] rounded-[12px] text-[14px] font-medium active:bg-[#E0E0E0]">비밀번호 초기화</button>
        </div>

        <div v-if="user.profile" class="bg-white rounded-[16px] shadow-sm border border-[#E8E8E8] p-4">
          <p class="text-[13px] font-semibold text-[#555] mb-2">프로필</p>
          <div class="space-y-2">
            <div class="flex justify-between"><span class="text-[13px] text-[#888]">이름</span><span class="text-[14px] text-[#333]">{{ user.profile.decrypted_name || user.profile.name }}</span></div>
            <div class="flex justify-between"><span class="text-[13px] text-[#888]">연락처</span><span class="text-[14px] text-[#333]">{{ user.profile.decrypted_phone || user.profile.phone || '-' }}</span></div>
            <div class="flex justify-between"><span class="text-[13px] text-[#888]">이메일</span><span class="text-[14px] text-[#333]">{{ user.profile.decrypted_email || user.profile.email || '-' }}</span></div>
          </div>
        </div>
      </div>

      <!-- 할당 콘텐츠 -->
      <div v-else-if="activeTab === 'assignments'" class="space-y-3">
        <div class="flex gap-2 overflow-x-auto pb-1">
          <button @click="assignmentTypeFilter = null" :class="!assignmentTypeFilter ? 'bg-[#4CAF50] text-white' : 'bg-[#F0F0F0] text-[#666]'" class="shrink-0 px-3 py-1.5 rounded-[10px] text-[13px] font-medium">전체</button>
          <button @click="assignmentTypeFilter = 'challenge'" :class="assignmentTypeFilter === 'challenge' ? 'bg-[#4CAF50] text-white' : 'bg-[#F0F0F0] text-[#666]'" class="shrink-0 px-3 py-1.5 rounded-[10px] text-[13px] font-medium">챌린지</button>
          <button @click="assignmentTypeFilter = 'learning_content'" :class="assignmentTypeFilter === 'learning_content' ? 'bg-[#4CAF50] text-white' : 'bg-[#F0F0F0] text-[#666]'" class="shrink-0 px-3 py-1.5 rounded-[10px] text-[13px] font-medium">학습</button>
          <button @click="assignmentTypeFilter = 'screening_test'" :class="assignmentTypeFilter === 'screening_test' ? 'bg-[#4CAF50] text-white' : 'bg-[#F0F0F0] text-[#666]'" class="shrink-0 px-3 py-1.5 rounded-[10px] text-[13px] font-medium">진단</button>
        </div>

        <div v-if="filteredAssignments().length === 0" class="bg-white rounded-[16px] shadow-sm border border-[#E8E8E8] p-6 text-center">
          <p class="text-[13px] text-[#888]">할당된 콘텐츠가 없습니다.</p>
        </div>

        <div v-for="a in filteredAssignments()" :key="a.assignment_id" class="bg-white rounded-[16px] shadow-sm border border-[#E8E8E8] p-4">
          <div class="flex items-start justify-between">
            <div class="flex-1 min-w-0">
              <p class="text-[14px] font-semibold text-[#333]">{{ a.assignable_title || '-' }}</p>
              <div class="flex items-center gap-2 mt-1">
                <span class="text-[11px] font-medium px-2 py-0.5 bg-[#F0F0F0] text-[#555] rounded-[6px]">{{ assignableTypeLabel(a.assignable_type) }}</span>
                <span class="text-[11px] font-medium px-2 py-0.5 rounded-[6px]" :class="statusColor(a.status)">{{ a.status }}</span>
              </div>
            </div>
          </div>
          <p class="text-[11px] text-[#888] mt-1.5">할당일: {{ formatDate(a.assigned_at) }}</p>
          <div class="flex gap-2 mt-2 overflow-x-auto">
            <template v-if="a.assignable_type === 'challenge'">
              <button @click="toggleRetry(a.assignment_id, (a as any).allow_retry ?? true)" class="shrink-0 text-[11px] px-2.5 py-1 rounded-[6px] bg-[#F0F0F0] text-[#555] active:opacity-70">
                {{ (a as any).allow_retry !== false ? '재도전 차단' : '재도전 허용' }}
              </button>
              <button @click="resetAttempts(a.assignment_id, a.assignable_title || '')" class="shrink-0 text-[11px] px-2.5 py-1 rounded-[6px] bg-[#FFF8E1] text-[#FF9800] active:opacity-70">초기화</button>
            </template>
            <button @click="removeAssignment(a.assignment_id)" class="shrink-0 text-[11px] px-2.5 py-1 rounded-[6px] bg-[#FFF0F0] text-[#FF4444] active:opacity-70">해제</button>
          </div>
        </div>
      </div>

      <!-- 진단결과 -->
      <div v-else-if="activeTab === 'screening'" class="space-y-3">
        <div v-if="!user.screening_results?.length" class="bg-white rounded-[16px] shadow-sm border border-[#E8E8E8] p-6 text-center">
          <p class="text-[13px] text-[#888]">진단 결과가 없습니다.</p>
        </div>
        <div v-for="r in user.screening_results" :key="r.result_id" class="bg-white rounded-[16px] shadow-sm border border-[#E8E8E8] p-4">
          <p class="text-[14px] font-semibold text-[#333]">{{ r.test?.title }}</p>
          <div class="flex items-center gap-3 mt-1.5">
            <span class="text-[15px] font-bold text-[#333]">{{ r.score }}점</span>
            <span v-if="r.level" class="text-[13px] font-medium" :class="levelColor(r.level)">{{ r.level }}</span>
          </div>
          <p class="text-[11px] text-[#888] mt-1">{{ formatDate(r.created_at) }}</p>
        </div>
      </div>

      <!-- 학습진도 -->
      <div v-else-if="activeTab === 'learning'" class="space-y-3">
        <div v-if="!user.learning_progress?.length" class="bg-white rounded-[16px] shadow-sm border border-[#E8E8E8] p-6 text-center">
          <p class="text-[13px] text-[#888]">학습 기록이 없습니다.</p>
        </div>
        <div v-for="lp in user.learning_progress" :key="lp.progress_id" class="bg-white rounded-[16px] shadow-sm border border-[#E8E8E8] p-4">
          <p class="text-[14px] font-semibold text-[#333]">{{ lp.content?.title || '-' }}</p>
          <div class="flex items-center gap-3 mt-1.5">
            <span class="text-[11px] font-medium px-2 py-0.5 rounded-[6px]" :class="statusColor(lp.status)">{{ lp.status }}</span>
            <span v-if="lp.score != null" class="text-[13px] font-semibold text-[#333]">{{ lp.score }}점</span>
          </div>
          <p v-if="lp.attempts" class="text-[11px] text-[#888] mt-1">시도횟수: {{ lp.attempts }}</p>
        </div>
      </div>

      <!-- 챌린지결과 -->
      <div v-else-if="activeTab === 'challenges'" class="space-y-3">
        <div v-if="!user.challenge_attempts?.length" class="bg-white rounded-[16px] shadow-sm border border-[#E8E8E8] p-6 text-center">
          <p class="text-[13px] text-[#888]">챌린지 결과가 없습니다.</p>
        </div>
        <div v-for="ca in user.challenge_attempts" :key="ca.attempt_id" class="bg-white rounded-[16px] shadow-sm border border-[#E8E8E8] p-4">
          <p class="text-[14px] font-semibold text-[#333]">{{ ca.challenge?.title || '-' }}</p>
          <div class="flex items-center gap-3 mt-1.5">
            <span class="text-[15px] font-bold text-[#333]">{{ ca.score }}점</span>
            <span v-if="ca.is_passed" class="text-[12px] font-medium text-[#4CAF50]">통과 ✓</span>
            <span v-else class="text-[12px] font-medium text-[#FF4444]">미통과</span>
          </div>
          <p class="text-[11px] text-[#888] mt-1">{{ formatDate(ca.created_at) }}</p>
        </div>
      </div>

      <!-- 메모 -->
      <div v-else-if="activeTab === 'memos'" class="space-y-3">
        <div class="flex gap-2 overflow-x-auto pb-1">
          <button @click="memoCategoryFilter = null" :class="!memoCategoryFilter ? 'bg-[#4CAF50] text-white' : 'bg-[#F0F0F0] text-[#666]'" class="shrink-0 px-3 py-1.5 rounded-[10px] text-[13px] font-medium">전체</button>
          <button v-for="(label, key) in categoryLabels" :key="key" @click="memoCategoryFilter = key" :class="memoCategoryFilter === key ? 'bg-[#4CAF50] text-white' : 'bg-[#F0F0F0] text-[#666]'" class="shrink-0 px-3 py-1.5 rounded-[10px] text-[13px] font-medium">{{ label }}</button>
        </div>

        <div v-if="memoLoading" class="text-center py-8">
          <div class="w-6 h-6 border-2 border-[#4CAF50] border-t-transparent rounded-full animate-spin mx-auto" />
        </div>

        <div v-else-if="filteredMemos.length === 0" class="bg-white rounded-[16px] shadow-sm border border-[#E8E8E8] p-6 text-center">
          <p class="text-[13px] text-[#888]">메모가 없습니다.</p>
        </div>

        <div v-for="m in filteredMemos" :key="m.memo_id" class="bg-white rounded-[16px] shadow-sm border border-[#E8E8E8] p-4">
          <div class="flex items-center gap-2 mb-1.5">
            <span v-if="m.is_pinned" class="text-[14px]">📌</span>
            <span class="text-[11px] font-medium px-2 py-0.5 rounded-[6px]" :class="categoryColors[m.category]">{{ categoryLabels[m.category] }}</span>
            <span class="text-[11px] text-[#888]">{{ m.account?.profile?.name ?? m.account?.username ?? '' }}</span>
            <span class="text-[11px] text-[#BBB]">{{ formatDate(m.created_at) }}</span>
          </div>
          <p class="text-[14px] text-[#333] whitespace-pre-wrap">{{ m.content }}</p>
          <div class="flex gap-2 mt-2">
            <button @click="togglePin(m.memo_id)" class="text-[11px] px-2 py-1 rounded-[6px] bg-[#F0F0F0] text-[#555] active:opacity-70">{{ m.is_pinned ? '고정 해제' : '고정' }}</button>
            <button @click="openMemoSheet(m)" class="text-[11px] px-2 py-1 rounded-[6px] bg-[#E8F5E9] text-[#4CAF50] active:opacity-70">수정</button>
            <button @click="deleteMemo(m.memo_id)" class="text-[11px] px-2 py-1 rounded-[6px] bg-[#FFF0F0] text-[#FF4444] active:opacity-70">삭제</button>
          </div>
        </div>

        <!-- FAB -->
        <button
          @click="openMemoSheet()"
          class="fixed bottom-6 right-6 w-[56px] h-[56px] bg-[#4CAF50] text-white rounded-full shadow-lg flex items-center justify-center text-[24px] active:bg-[#388E3C] z-50"
        >+</button>
      </div>
    </div>

    <!-- 정보 수정 바텀시트 -->
    <BottomSheet v-model="showEditSheet" title="정보 수정" max-height="90vh">
      <div class="space-y-4 pb-4">
        <div>
          <label class="block text-[13px] font-medium text-[#555] mb-1">아이디</label>
          <input v-model="editForm.username" type="text" class="w-full px-4 py-3.5 border border-[#E8E8E8] rounded-[12px] text-[15px] focus:outline-none focus:border-[#4CAF50]" />
        </div>
        <div>
          <label class="block text-[13px] font-medium text-[#555] mb-1">비밀번호 (변경 시 입력)</label>
          <input v-model="editForm.password" type="password" class="w-full px-4 py-3.5 border border-[#E8E8E8] rounded-[12px] text-[15px] focus:outline-none focus:border-[#4CAF50]" />
        </div>
        <div>
          <label class="block text-[13px] font-medium text-[#555] mb-1">이름</label>
          <input v-model="editForm.name" type="text" class="w-full px-4 py-3.5 border border-[#E8E8E8] rounded-[12px] text-[15px] focus:outline-none focus:border-[#4CAF50]" />
        </div>
        <div>
          <label class="block text-[13px] font-medium text-[#555] mb-1">연락처</label>
          <input v-model="editForm.phone" type="text" class="w-full px-4 py-3.5 border border-[#E8E8E8] rounded-[12px] text-[15px] focus:outline-none focus:border-[#4CAF50]" />
        </div>
        <div>
          <label class="block text-[13px] font-medium text-[#555] mb-1">이메일</label>
          <input v-model="editForm.email" type="text" class="w-full px-4 py-3.5 border border-[#E8E8E8] rounded-[12px] text-[15px] focus:outline-none focus:border-[#4CAF50]" />
        </div>
        <button @click="handleEditSubmit" :disabled="editLoading" class="w-full py-3.5 bg-[#4CAF50] text-white rounded-[12px] text-[15px] font-semibold disabled:opacity-50 active:bg-[#388E3C]">
          {{ editLoading ? '저장 중...' : '저장' }}
        </button>
      </div>
    </BottomSheet>

    <!-- 비밀번호 초기화 바텀시트 -->
    <BottomSheet v-model="showResetPw" title="비밀번호 초기화">
      <div class="space-y-4 pb-4">
        <div v-if="!tempPassword">
          <p class="text-[14px] text-[#333]">비밀번호를 초기화하시겠습니까?</p>
          <p class="text-[13px] text-[#888] mt-1">임시 비밀번호가 생성됩니다.</p>
          <button @click="handleResetPassword" :disabled="resetPwLoading" class="w-full mt-4 py-3.5 bg-[#FF9800] text-white rounded-[12px] text-[15px] font-semibold disabled:opacity-50 active:opacity-80">
            {{ resetPwLoading ? '처리 중...' : '초기화' }}
          </button>
        </div>
        <div v-else>
          <p class="text-[13px] text-[#888]">임시 비밀번호:</p>
          <p class="text-[20px] font-bold text-[#333] bg-[#F8F8F8] rounded-[12px] p-4 text-center mt-2 font-mono select-all">{{ tempPassword }}</p>
          <p class="text-[12px] text-[#FF4444] mt-2">이 비밀번호를 사용자에게 전달해주세요.</p>
        </div>
      </div>
    </BottomSheet>

    <!-- 메모 바텀시트 -->
    <BottomSheet v-model="showMemoSheet" :title="editingMemo ? '메모 수정' : '메모 추가'">
      <div class="space-y-4 pb-4">
        <div>
          <label class="block text-[13px] font-medium text-[#555] mb-2">카테고리</label>
          <div class="flex gap-2 flex-wrap">
            <button
              v-for="(label, key) in categoryLabels"
              :key="key"
              @click="memoForm.category = key as LearnerMemo['category']"
              class="px-3 py-1.5 rounded-[10px] text-[13px] font-medium"
              :class="memoForm.category === key ? 'bg-[#4CAF50] text-white' : 'bg-[#F0F0F0] text-[#666]'"
            >{{ label }}</button>
          </div>
        </div>
        <div>
          <label class="block text-[13px] font-medium text-[#555] mb-1">내용</label>
          <textarea v-model="memoForm.content" rows="4" placeholder="메모 내용을 입력하세요" class="w-full px-4 py-3.5 border border-[#E8E8E8] rounded-[12px] text-[15px] focus:outline-none focus:border-[#4CAF50] resize-none" />
        </div>
        <div class="flex items-center gap-2">
          <input v-model="memoForm.is_pinned" type="checkbox" id="memo_pin" class="w-5 h-5 accent-[#4CAF50]" />
          <label for="memo_pin" class="text-[14px] text-[#333]">상단 고정</label>
        </div>
        <button @click="handleMemoSubmit" class="w-full py-3.5 bg-[#4CAF50] text-white rounded-[12px] text-[15px] font-semibold active:bg-[#388E3C]">
          {{ editingMemo ? '수정' : '등록' }}
        </button>
      </div>
    </BottomSheet>
  </div>
</template>
