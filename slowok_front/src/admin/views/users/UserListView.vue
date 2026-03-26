<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@shared/api'
import { adminApi } from '@shared/api/adminApi'
import { exportApi } from '@shared/api/exportApi'
import type { Account, ApiResponse, UserProfile } from '@shared/types'
import { useToastStore } from '@shared/stores/toastStore'

const router = useRouter()
const toast = useToastStore()

const users = ref<Account[]>([])
const loading = ref(true)
const error = ref('')
const searchQuery = ref('')
const togglingId = ref<number | null>(null)

// 고객 추가 모달
const showCreateModal = ref(false)
const createForm = ref({ username: '', password: '', name: '', user_type: 'LEARNER' as 'LEARNER' | 'PARENT', phone: '', email: '' })
const createLoading = ref(false)
const createError = ref('')

function getProfiles(user: Account): UserProfile[] {
  return user.profiles ?? (user.profile ? [user.profile] : [])
}

function getMainProfile(user: Account): UserProfile | undefined {
  const profiles = getProfiles(user)
  return profiles.find(p => p.user_type === 'PARENT') ?? profiles[0]
}

function getProfileName(user: Account): string {
  const p = getMainProfile(user)
  return p?.decrypted_name ?? p?.name ?? user.username
}

const filteredUsers = computed(() => {
  if (!searchQuery.value.trim()) return users.value
  const q = searchQuery.value.toLowerCase()
  return users.value.filter((u) => {
    const profiles = getProfiles(u)
    const nameMatch = profiles.some(p =>
      (p.decrypted_name ?? p.name ?? '').toLowerCase().includes(q)
    )
    return u.username.toLowerCase().includes(q) || nameMatch
  })
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
  if (!confirm(`"${getProfileName(user)}" 사용자를 정말 삭제하시겠습니까?`)) return
  try {
    await api.delete(`/admin/users/${user.account_id}`)
    users.value = users.value.filter((u) => u.account_id !== user.account_id)
  } catch (e: any) {
    toast.error(e.response?.data?.message || '삭제에 실패했습니다.')
  }
}

function openCreateModal() {
  createForm.value = { username: '', password: '', name: '', user_type: 'LEARNER', phone: '', email: '' }
  createError.value = ''
  showCreateModal.value = true
}

async function handleCreate() {
  createLoading.value = true
  createError.value = ''
  try {
    const payload: any = {
      username: createForm.value.username,
      password: createForm.value.password,
      name: createForm.value.name,
      user_type: createForm.value.user_type,
    }
    if (createForm.value.phone) payload.phone = createForm.value.phone
    if (createForm.value.email) payload.email = createForm.value.email
    await adminApi.createUser(payload)
    toast.success('사용자가 생성되었습니다.')
    showCreateModal.value = false
    await fetchUsers()
  } catch (e: any) {
    createError.value = e.response?.data?.message || '생성에 실패했습니다.'
  } finally {
    createLoading.value = false
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
      <!-- 검색 바 + 버튼 -->
      <div class="flex items-center justify-between gap-3 mb-4 flex-wrap">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="이름 또는 아이디로 검색..."
          class="flex-1 min-w-[200px] max-w-[400px] bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] px-4 py-3 text-[15px] focus:border-[#4CAF50] focus:outline-none transition-colors"
        />
        <div class="flex gap-2">
          <button
            @click="openCreateModal"
            class="flex items-center gap-1.5 px-4 py-2.5 rounded-[10px] text-[13px] font-medium bg-[#4CAF50] text-white hover:bg-[#388E3C] transition-colors"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
            고객 추가
          </button>
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
        <button @click="fetchUsers" class="bg-[#4CAF50] hover:bg-[#388E3C] text-white rounded-[12px] px-5 py-2.5 text-[14px] font-medium transition-all">다시 시도</button>
      </div>

      <!-- 빈 상태 -->
      <div v-else-if="filteredUsers.length === 0" class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] p-8 text-center">
        <p class="text-[#888]">{{ searchQuery ? '검색 결과가 없습니다.' : '등록된 사용자가 없습니다.' }}</p>
      </div>

      <!-- 테이블 -->
      <div v-else class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-[14px]">
            <thead>
              <tr class="border-b border-[#E8E8E8] bg-[#FAFAFA]">
                <th class="px-5 py-3 font-semibold text-[#555]">이름</th>
                <th class="px-5 py-3 font-semibold text-[#555]">아이디</th>
                <th class="px-5 py-3 font-semibold text-[#555]">프로필</th>
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
                  {{ getProfileName(user) }}
                </td>
                <td class="px-5 py-3.5 text-[#555]">{{ user.username }}</td>
                <td class="px-5 py-3.5">
                  <div class="flex items-center gap-1.5 flex-wrap">
                    <template v-for="p in getProfiles(user)" :key="p.profile_id">
                      <span
                        class="px-2 py-0.5 rounded-full text-[11px] font-medium"
                        :class="p.user_type === 'PARENT' ? 'bg-purple-50 text-purple-600' : 'bg-blue-50 text-blue-600'"
                      >
                        {{ p.user_type === 'PARENT' ? '보호자' : (p.decrypted_name ?? p.name ?? '학습자') }}
                      </span>
                    </template>
                  </div>
                </td>
                <td class="px-5 py-3.5 text-[#888]">{{ formatDate(user.last_login_at) }}</td>
                <td class="px-5 py-3.5">
                  <button
                    @click.stop="toggleActive(user)"
                    :disabled="togglingId === user.account_id"
                    class="px-3 py-1 rounded-full text-[12px] font-medium transition-colors"
                    :class="user.is_active ? 'bg-green-50 text-green-600 hover:bg-green-100' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'"
                  >
                    {{ togglingId === user.account_id ? '처리중...' : user.is_active ? '활성' : '비활성' }}
                  </button>
                </td>
                <td class="px-5 py-3.5">
                  <button
                    @click.stop="deleteUser(user)"
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

    <!-- 고객 추가 모달 -->
    <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div class="w-full max-w-md bg-white rounded-[16px] p-6 mx-4 space-y-4">
        <h3 class="text-[16px] font-bold text-[#333]">고객 추가</h3>
        <div v-if="createError" class="bg-[#FFE5E5] rounded-[8px] p-2.5 text-[12px] text-[#FF4444]">{{ createError }}</div>
        <form @submit.prevent="handleCreate" class="space-y-3">
          <div>
            <label class="block text-[13px] font-medium text-[#555] mb-1">아이디 *</label>
            <input v-model="createForm.username" type="text" required class="w-full border border-[#E8E8E8] rounded-[10px] px-3 py-2.5 text-[14px] outline-none focus:border-[#4CAF50]" placeholder="로그인에 사용할 아이디" />
          </div>
          <div>
            <label class="block text-[13px] font-medium text-[#555] mb-1">비밀번호 *</label>
            <input v-model="createForm.password" type="text" required minlength="6" class="w-full border border-[#E8E8E8] rounded-[10px] px-3 py-2.5 text-[14px] outline-none focus:border-[#4CAF50]" placeholder="6자 이상" />
          </div>
          <div>
            <label class="block text-[13px] font-medium text-[#555] mb-1">이름 *</label>
            <input v-model="createForm.name" type="text" required class="w-full border border-[#E8E8E8] rounded-[10px] px-3 py-2.5 text-[14px] outline-none focus:border-[#4CAF50]" />
          </div>
          <div>
            <label class="block text-[13px] font-medium text-[#555] mb-1">유형 *</label>
            <div class="flex gap-3">
              <label class="flex items-center gap-2 text-[14px] cursor-pointer">
                <input type="radio" v-model="createForm.user_type" value="LEARNER" class="accent-[#4CAF50]" />
                학습자
              </label>
              <label class="flex items-center gap-2 text-[14px] cursor-pointer">
                <input type="radio" v-model="createForm.user_type" value="PARENT" class="accent-[#4CAF50]" />
                보호자
              </label>
            </div>
          </div>
          <div>
            <label class="block text-[13px] font-medium text-[#555] mb-1">연락처</label>
            <input v-model="createForm.phone" type="tel" class="w-full border border-[#E8E8E8] rounded-[10px] px-3 py-2.5 text-[14px] outline-none focus:border-[#4CAF50]" />
          </div>
          <div>
            <label class="block text-[13px] font-medium text-[#555] mb-1">이메일</label>
            <input v-model="createForm.email" type="email" class="w-full border border-[#E8E8E8] rounded-[10px] px-3 py-2.5 text-[14px] outline-none focus:border-[#4CAF50]" />
          </div>
          <div class="flex gap-3 pt-2">
            <button type="button" class="flex-1 py-2.5 rounded-[10px] text-[13px] font-medium bg-[#F0F0F0] text-[#666]" @click="showCreateModal = false">취소</button>
            <button type="submit" :disabled="createLoading" class="flex-1 py-2.5 rounded-[10px] text-[13px] font-medium bg-[#4CAF50] text-white disabled:opacity-50">
              {{ createLoading ? '생성 중...' : '생성' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
