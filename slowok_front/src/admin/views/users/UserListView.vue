<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@shared/api'
import { exportApi } from '@shared/api/exportApi'
import type { Account, ApiResponse } from '@shared/types'
import { useToastStore } from '@shared/stores/toastStore'

const router = useRouter()
const toast = useToastStore()

const users = ref<Account[]>([])
const loading = ref(true)
const error = ref('')
const searchQuery = ref('')
const togglingId = ref<number | null>(null)

const filteredUsers = computed(() => {
  if (!searchQuery.value.trim()) return users.value
  const q = searchQuery.value.toLowerCase()
  return users.value.filter(
    (u) =>
      u.username.toLowerCase().includes(q) ||
      (u.profile?.decrypted_name ?? u.profile?.name ?? '').toLowerCase().includes(q)
  )
})

async function fetchUsers() {
  loading.value = true
  error.value = ''
  try {
    const res = await api.get<ApiResponse<Account[]>>('/admin/users')
    if (res.data.success) {
      users.value = res.data.data
    }
  } catch (e: any) {
    error.value = e.response?.data?.message || '사용자 목록을 불러오지 못했습니다.'
  } finally {
    loading.value = false
  }
}

async function toggleActive(user: Account) {
  togglingId.value = user.account_id
  try {
    const res = await api.put<ApiResponse<Account>>(`/admin/users/${user.account_id}`, {
      is_active: !user.is_active,
    })
    if (res.data.success) {
      user.is_active = !user.is_active
    }
  } catch (e: any) {
    toast.error(e.response?.data?.message || '상태 변경에 실패했습니다.')
  } finally {
    togglingId.value = null
  }
}

async function deleteUser(user: Account) {
  if (!confirm(`"${user.profile?.name || user.username}" 사용자를 정말 삭제하시겠습니까?\n이 작업은 되돌릴 수 없습니다.`)) return
  try {
    await api.delete(`/admin/users/${user.account_id}`)
    users.value = users.value.filter((u) => u.account_id !== user.account_id)
  } catch (e: any) {
    toast.error(e.response?.data?.message || '삭제에 실패했습니다.')
  }
}

function formatDate(dateStr: string | null | undefined): string {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString('ko-KR', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  })
}

function userTypeLabel(type?: string): string {
  switch (type) {
    case 'LEARNER': return '학습자'
    case 'PARENT': return '보호자'
    default: return '-'
  }
}

const exporting = ref<string | null>(null)

async function handleExport(type: 'users' | 'learningProgress') {
  exporting.value = type
  try {
    await exportApi[type]()
    toast.success('다운로드가 시작되었습니다.')
  } catch {
    toast.error('내보내기에 실패했습니다.')
  } finally {
    exporting.value = null
  }
}

onMounted(fetchUsers)
</script>

<template>
  <div class="p-6">
    <div class="max-w-[1200px] mx-auto">
      <!-- 검색 바 + 내보내기 버튼 -->
      <div class="flex items-center justify-between gap-3 mb-4 flex-wrap">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="이름 또는 아이디로 검색..."
          class="flex-1 min-w-[200px] max-w-[400px] bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] px-4 py-3 text-[15px] focus:border-[#4CAF50] focus:outline-none transition-colors"
        />
        <div class="flex gap-2">
          <button
            :disabled="exporting === 'users'"
            @click="handleExport('users')"
            class="flex items-center gap-1.5 px-4 py-2.5 rounded-[10px] text-[13px] font-medium bg-white border border-[#E8E8E8] text-[#555] hover:bg-[#F8F8F8] disabled:opacity-50 transition-colors"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
            {{ exporting === 'users' ? '내보내는 중...' : '사용자 목록' }}
          </button>
          <button
            :disabled="exporting === 'learningProgress'"
            @click="handleExport('learningProgress')"
            class="flex items-center gap-1.5 px-4 py-2.5 rounded-[10px] text-[13px] font-medium bg-white border border-[#E8E8E8] text-[#555] hover:bg-[#F8F8F8] disabled:opacity-50 transition-colors"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
            {{ exporting === 'learningProgress' ? '내보내는 중...' : '학습 진도' }}
          </button>
        </div>
      </div>

      <!-- 로딩 -->
      <div v-if="loading" class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] p-8 text-center">
        <p class="text-[#888]">사용자 목록을 불러오는 중...</p>
      </div>

      <!-- 에러 -->
      <div v-else-if="error" class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] p-8 text-center">
        <p class="text-red-500 mb-3">{{ error }}</p>
        <button
          @click="fetchUsers"
          class="bg-[#4CAF50] hover:bg-[#388E3C] text-white rounded-[12px] px-5 py-2.5 text-[14px] font-medium active:scale-[0.98] transition-all"
        >
          다시 시도
        </button>
      </div>

      <!-- 빈 상태 -->
      <div v-else-if="filteredUsers.length === 0" class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] p-8 text-center">
        <p class="text-[#888]">
          {{ searchQuery ? '검색 결과가 없습니다.' : '등록된 사용자가 없습니다.' }}
        </p>
      </div>

      <!-- 테이블 -->
      <div v-else class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-[14px]">
            <thead>
              <tr class="border-b border-[#E8E8E8] bg-[#FAFAFA]">
                <th class="px-5 py-3 font-semibold text-[#555]">이름</th>
                <th class="px-5 py-3 font-semibold text-[#555]">아이디</th>
                <th class="px-5 py-3 font-semibold text-[#555]">유형</th>
                <th class="px-5 py-3 font-semibold text-[#555]">역할</th>
                <th class="px-5 py-3 font-semibold text-[#555]">마지막 로그인</th>
                <th class="px-5 py-3 font-semibold text-[#555]">상태</th>
                <th class="px-5 py-3 font-semibold text-[#555]">액션</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="user in filteredUsers"
                :key="user.account_id"
                class="border-b border-[#F0F0F0] hover:bg-[#FAFAFA] transition-colors cursor-pointer"
                @click="router.push({ name: 'user-detail', params: { id: user.account_id } })"
              >
                <td class="px-5 py-3.5 text-[#333] font-medium">
                  {{ user.profile?.decrypted_name || user.profile?.name || '-' }}
                </td>
                <td class="px-5 py-3.5 text-[#555]">{{ user.username }}</td>
                <td class="px-5 py-3.5">
                  <span
                    class="px-2 py-0.5 rounded-full text-[12px] font-medium"
                    :class="user.profile?.user_type === 'LEARNER'
                      ? 'bg-blue-50 text-blue-600'
                      : 'bg-purple-50 text-purple-600'"
                  >
                    {{ userTypeLabel(user.profile?.user_type) }}
                  </span>
                </td>
                <td class="px-5 py-3.5">
                  <span
                    class="px-2 py-0.5 rounded-full text-[12px] font-medium"
                    :class="user.role === 'ADMIN'
                      ? 'bg-red-50 text-red-600'
                      : 'bg-gray-100 text-gray-600'"
                  >
                    {{ user.role === 'ADMIN' ? '관리자' : '사용자' }}
                  </span>
                </td>
                <td class="px-5 py-3.5 text-[#888]">{{ formatDate(user.last_login_at) }}</td>
                <td class="px-5 py-3.5">
                  <button
                    @click="toggleActive(user)"
                    :disabled="togglingId === user.account_id"
                    class="px-3 py-1 rounded-full text-[12px] font-medium transition-colors"
                    :class="user.is_active
                      ? 'bg-green-50 text-green-600 hover:bg-green-100'
                      : 'bg-gray-100 text-gray-500 hover:bg-gray-200'"
                  >
                    {{ togglingId === user.account_id ? '처리중...' : user.is_active ? '활성' : '비활성' }}
                  </button>
                </td>
                <td class="px-5 py-3.5">
                  <button
                    @click="deleteUser(user)"
                    class="text-red-500 hover:text-red-700 text-[13px] font-medium transition-colors"
                  >
                    삭제
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="px-5 py-3 border-t border-[#F0F0F0] text-[13px] text-[#888]">
          총 {{ filteredUsers.length }}명
          <span v-if="searchQuery"> (전체 {{ users.length }}명 중)</span>
        </div>
      </div>
    </div>
  </div>
</template>
