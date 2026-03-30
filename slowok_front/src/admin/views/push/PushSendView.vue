<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@shared/api'
import { adminApi } from '@shared/api/adminApi'
import type { Account, ApiResponse } from '@shared/types'
import { useToastStore } from '@shared/stores/toastStore'

const toast = useToastStore()

const title = ref('')
const body = ref('')
const target = ref<'all' | 'specific'>('all')
const sending = ref(false)
const error = ref('')

// 통계
const stats = ref<{ total_tokens: number; unique_accounts: number } | null>(null)

// 사용자 목록 (specific 모드용)
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

onMounted(() => {
  fetchStats()
  fetchUsers()
})

function toggleUser(id: number) {
  if (selectedIds.value.has(id)) {
    selectedIds.value.delete(id)
  } else {
    selectedIds.value.add(id)
  }
  selectedIds.value = new Set(selectedIds.value)
}

function selectAll() {
  const filtered = filteredUsers.value
  if (selectedIds.value.size === filtered.length) {
    selectedIds.value = new Set()
  } else {
    selectedIds.value = new Set(filtered.map(u => u.account_id))
  }
}

import { computed } from 'vue'

const filteredUsers = computed(() => {
  if (!userSearch.value.trim()) return users.value
  const q = userSearch.value.toLowerCase()
  return users.value.filter(u =>
    u.username.toLowerCase().includes(q) ||
    (u.profile?.name ?? '').toLowerCase().includes(q)
  )
})

async function handleSend() {
  if (!title.value.trim()) { error.value = '제목을 입력해주세요.'; return }
  if (!body.value.trim()) { error.value = '내용을 입력해주세요.'; return }
  if (target.value === 'specific' && selectedIds.value.size === 0) {
    error.value = '발송 대상을 선택해주세요.'
    return
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
      title.value = ''
      body.value = ''
      selectedIds.value = new Set()
    }
  } catch (e: any) {
    error.value = e.response?.data?.message || '발송에 실패했습니다.'
  } finally {
    sending.value = false
  }
}
</script>

<template>
  <div class="p-6">
    <div class="max-w-[800px] mx-auto">
      <div class="mb-6">
        <h2 class="text-[18px] font-bold text-[#333]">푸시 알림 발송</h2>
        <p class="text-[13px] text-[#888] mt-1">사용자에게 푸시 알림을 발송합니다.</p>
      </div>

      <!-- 통계 -->
      <div v-if="stats" class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.08)] p-4 mb-5 flex gap-6">
        <div>
          <p class="text-[11px] text-[#999]">등록된 기기</p>
          <p class="text-[20px] font-bold text-[#333]">{{ stats.total_tokens }}</p>
        </div>
        <div>
          <p class="text-[11px] text-[#999]">사용자 수</p>
          <p class="text-[20px] font-bold text-[#4CAF50]">{{ stats.unique_accounts }}</p>
        </div>
      </div>

      <!-- 발송 폼 -->
      <div class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.08)] p-5 space-y-4">
        <!-- 제목 -->
        <div>
          <label class="block text-[13px] font-semibold text-[#333] mb-1.5">제목</label>
          <input
            v-model="title"
            type="text"
            placeholder="알림 제목"
            maxlength="100"
            class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] px-4 py-3 text-[14px] focus:border-[#4CAF50] focus:outline-none transition-colors"
          />
        </div>

        <!-- 내용 -->
        <div>
          <label class="block text-[13px] font-semibold text-[#333] mb-1.5">내용</label>
          <textarea
            v-model="body"
            rows="3"
            placeholder="알림 내용을 입력하세요"
            maxlength="500"
            class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] px-4 py-3 text-[14px] focus:border-[#4CAF50] focus:outline-none transition-colors resize-none"
          />
          <p class="text-[11px] text-[#999] mt-1 text-right">{{ body.length }}/500</p>
        </div>

        <!-- 발송 대상 -->
        <div>
          <label class="block text-[13px] font-semibold text-[#333] mb-2">발송 대상</label>
          <div class="flex gap-2 mb-3">
            <button
              @click="target = 'all'"
              :class="target === 'all' ? 'bg-[#4CAF50] text-white' : 'bg-[#F0F0F0] text-[#666]'"
              class="px-4 py-2 rounded-[10px] text-[13px] font-medium transition-colors"
            >
              전체 발송
            </button>
            <button
              @click="target = 'specific'"
              :class="target === 'specific' ? 'bg-[#4CAF50] text-white' : 'bg-[#F0F0F0] text-[#666]'"
              class="px-4 py-2 rounded-[10px] text-[13px] font-medium transition-colors"
            >
              선택 발송
            </button>
          </div>

          <!-- 사용자 선택 (specific) -->
          <div v-if="target === 'specific'" class="border border-[#E8E8E8] rounded-[12px] p-3">
            <div class="flex items-center gap-2 mb-2">
              <div class="relative flex-1">
                <input
                  v-model="userSearch"
                  type="text"
                  placeholder="이름/아이디 검색..."
                  class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[8px] pl-8 pr-3 py-2 text-[13px] focus:border-[#4CAF50] focus:outline-none"
                />
                <svg class="w-4 h-4 text-[#999] absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8" /><path d="m21 21-4.3-4.3" /></svg>
              </div>
              <button @click="selectAll" class="text-[12px] text-[#4CAF50] font-medium hover:underline shrink-0">
                {{ selectedIds.size === filteredUsers.length ? '전체 해제' : '전체 선택' }}
              </button>
            </div>
            <div class="max-h-[200px] overflow-y-auto space-y-0.5">
              <div
                v-for="u in filteredUsers"
                :key="u.account_id"
                @click="toggleUser(u.account_id)"
                class="flex items-center gap-2 px-2 py-1.5 rounded-[6px] cursor-pointer transition-colors"
                :class="selectedIds.has(u.account_id) ? 'bg-[#E8F5E9]' : 'hover:bg-[#F5F5F5]'"
              >
                <input type="checkbox" :checked="selectedIds.has(u.account_id)" class="w-3.5 h-3.5 accent-[#4CAF50] pointer-events-none" />
                <span class="text-[13px]">{{ u.profile?.name ?? u.username }}</span>
                <span class="text-[11px] text-[#999]">({{ u.username }})</span>
              </div>
            </div>
            <p class="text-[11px] text-[#888] mt-2">{{ selectedIds.size }}명 선택</p>
          </div>
        </div>

        <!-- 에러 -->
        <p v-if="error" class="text-[13px] text-red-500">{{ error }}</p>

        <!-- 발송 버튼 -->
        <button
          @click="handleSend"
          :disabled="sending"
          class="w-full bg-[#4CAF50] hover:bg-[#388E3C] disabled:bg-[#ccc] text-white rounded-[12px] py-3 text-[14px] font-medium transition-all flex items-center justify-center gap-2"
        >
          <svg v-if="sending" class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
          {{ sending ? '발송 중...' : target === 'all' ? '전체 발송' : `${selectedIds.size}명에게 발송` }}
        </button>
      </div>
    </div>
  </div>
</template>
