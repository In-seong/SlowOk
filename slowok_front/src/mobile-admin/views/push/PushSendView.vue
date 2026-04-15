<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@shared/api'
import { adminApi } from '@shared/api/adminApi'
import type { Account, ApiResponse } from '@shared/types'
import { useToastStore } from '@shared/stores/toastStore'

const router = useRouter()
const toast = useToastStore()

const title = ref('')
const body = ref('')
const target = ref<'all' | 'specific'>('all')
const sending = ref(false)
const error = ref('')

const stats = ref<{ total_tokens: number; unique_accounts: number; account_ids_with_token?: number[] } | null>(null)
const users = ref<Account[]>([])
const selectedIds = ref<Set<number>>(new Set())
const userSearch = ref('')
const loadingUsers = ref(false)

async function fetchStats() {
  try {
    const res = await adminApi.getPushStats()
    if (res.data.success) stats.value = res.data.data
  } catch { /* ignore */ }
}

async function fetchUsers() {
  loadingUsers.value = true
  try {
    const res = await api.get<ApiResponse<Account[]>>('/admin/users')
    if (res.data.success) users.value = res.data.data.filter(u => u.role === 'USER')
  } catch { /* ignore */ }
  finally { loadingUsers.value = false }
}

onMounted(() => { fetchStats(); fetchUsers() })

const filteredUsers = computed(() => {
  if (!userSearch.value.trim()) return users.value
  const q = userSearch.value.toLowerCase()
  return users.value.filter(u =>
    u.username.toLowerCase().includes(q) ||
    (u.profile?.name ?? '').toLowerCase().includes(q)
  )
})

function toggleUser(id: number) {
  const s = new Set(selectedIds.value)
  if (s.has(id)) s.delete(id)
  else s.add(id)
  selectedIds.value = s
}

function selectAll() {
  if (selectedIds.value.size === filteredUsers.value.length) {
    selectedIds.value = new Set()
  } else {
    selectedIds.value = new Set(filteredUsers.value.map(u => u.account_id))
  }
}

async function handleSend() {
  if (!title.value.trim()) { error.value = '제목을 입력해주세요.'; return }
  if (!body.value.trim()) { error.value = '내용을 입력해주세요.'; return }
  if (target.value === 'specific' && selectedIds.value.size === 0) {
    error.value = '발송 대상을 선택해주세요.'; return
  }

  const targetLabel = target.value === 'all' ? '전체 사용자' : `${selectedIds.value.size}명`
  if (!confirm(`"${title.value}"를 ${targetLabel}에게 발송하시겠습니까?`)) return

  sending.value = true
  error.value = ''
  try {
    const res = await adminApi.sendPush({
      title: title.value.trim(),
      body: body.value.trim(),
      target: target.value,
      account_ids: target.value === 'specific' ? Array.from(selectedIds.value) : undefined,
    })
    if (res.data.success) {
      toast.success(res.data.message || '발송 완료!')
      fetchStats()
    }
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    error.value = err.response?.data?.message || '발송에 실패했습니다.'
  } finally {
    sending.value = false
  }
}
</script>

<template>
  <div class="min-h-screen bg-[#FAFAFA]">
    <header class="sticky top-0 z-40 bg-white border-b border-[#E8E8E8] h-[56px] flex items-center px-4">
      <button @click="router.back()" class="w-10 h-10 flex items-center justify-center">
        <svg class="w-5 h-5 text-[#333]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
      </button>
      <h1 class="flex-1 text-center text-[16px] font-bold text-[#333]">푸시 알림</h1>
      <div class="w-10" />
    </header>

    <div class="px-4 py-5 space-y-4">
      <!-- 통계 -->
      <div v-if="stats" class="grid grid-cols-2 gap-3">
        <div class="bg-white rounded-[16px] shadow-sm border border-[#E8E8E8] p-4 text-center">
          <p class="text-[20px] font-bold text-[#333]">{{ stats.total_tokens }}</p>
          <p class="text-[12px] text-[#888]">등록 기기</p>
        </div>
        <div class="bg-white rounded-[16px] shadow-sm border border-[#E8E8E8] p-4 text-center">
          <p class="text-[20px] font-bold text-[#4CAF50]">{{ stats.unique_accounts }}</p>
          <p class="text-[12px] text-[#888]">사용자</p>
        </div>
      </div>

      <!-- 발송 폼 -->
      <div class="bg-white rounded-[16px] shadow-sm border border-[#E8E8E8] p-4 space-y-4">
        <div>
          <label class="block text-[13px] font-medium text-[#555] mb-1">제목</label>
          <input v-model="title" type="text" placeholder="알림 제목" maxlength="100" class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] px-4 py-3.5 text-[15px] focus:border-[#4CAF50] focus:outline-none" />
        </div>

        <div>
          <label class="block text-[13px] font-medium text-[#555] mb-1">내용</label>
          <textarea v-model="body" rows="3" placeholder="알림 내용을 입력하세요" maxlength="500" class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] px-4 py-3.5 text-[15px] focus:border-[#4CAF50] focus:outline-none resize-none" />
          <p class="text-[11px] text-[#999] mt-1 text-right">{{ body.length }}/500</p>
        </div>

        <!-- 발송 대상 -->
        <div>
          <label class="block text-[13px] font-medium text-[#555] mb-2">발송 대상</label>
          <div class="flex gap-2 mb-3">
            <button @click="target = 'all'" :class="target === 'all' ? 'bg-[#4CAF50] text-white' : 'bg-[#F0F0F0] text-[#666]'" class="flex-1 py-3 rounded-[12px] text-[14px] font-medium">전체 발송</button>
            <button @click="target = 'specific'" :class="target === 'specific' ? 'bg-[#4CAF50] text-white' : 'bg-[#F0F0F0] text-[#666]'" class="flex-1 py-3 rounded-[12px] text-[14px] font-medium">선택 발송</button>
          </div>

          <div v-if="target === 'specific'" class="border border-[#E8E8E8] rounded-[12px] p-3">
            <div class="flex items-center gap-2 mb-2">
              <input v-model="userSearch" type="text" placeholder="이름/아이디 검색..." class="flex-1 bg-[#F8F8F8] border border-[#E8E8E8] rounded-[8px] px-3 py-2.5 text-[13px] focus:border-[#4CAF50] focus:outline-none" />
              <button @click="selectAll" class="text-[12px] text-[#4CAF50] font-medium shrink-0">
                {{ selectedIds.size === filteredUsers.length ? '전체 해제' : '전체 선택' }}
              </button>
            </div>
            <div class="max-h-[240px] overflow-y-auto space-y-0.5">
              <div
                v-for="u in filteredUsers"
                :key="u.account_id"
                @click="toggleUser(u.account_id)"
                class="flex items-center gap-2 px-2 py-2.5 rounded-[8px] active:bg-[#F0F0F0]"
                :class="selectedIds.has(u.account_id) ? 'bg-[#E8F5E9]' : ''"
              >
                <input type="checkbox" :checked="selectedIds.has(u.account_id)" class="w-4 h-4 accent-[#4CAF50] pointer-events-none" />
                <span class="text-[14px] text-[#333]">{{ u.profile?.name ?? u.username }}</span>
                <span class="text-[11px] text-[#999]">({{ u.username }})</span>
              </div>
            </div>
            <p class="text-[12px] text-[#888] mt-2">{{ selectedIds.size }}명 선택</p>
          </div>
        </div>

        <p v-if="error" class="text-[13px] text-[#FF4444]">{{ error }}</p>

        <button
          @click="handleSend"
          :disabled="sending"
          class="w-full py-3.5 bg-[#4CAF50] text-white rounded-[12px] text-[15px] font-semibold disabled:opacity-50 active:bg-[#388E3C] flex items-center justify-center gap-2"
        >
          <div v-if="sending" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" />
          {{ sending ? '발송 중...' : target === 'all' ? '전체 발송' : `${selectedIds.size}명에게 발송` }}
        </button>
      </div>
    </div>
  </div>
</template>
