<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@shared/api'
import type { ApiResponse, Account } from '@shared/types'
import StatusBadge from '@shared/components/ui/StatusBadge.vue'

const router = useRouter()

const users = ref<Account[]>([])
const searchQuery = ref('')
const loading = ref(true)

async function fetchUsers() {
  loading.value = true
  try {
    const res = await api.get<ApiResponse<Account[]>>('/admin/users')
    if (res.data.success) {
      users.value = res.data.data
    }
  } catch {
    // ignore
  } finally {
    loading.value = false
  }
}

onMounted(fetchUsers)

const filteredUsers = computed(() => {
  if (!searchQuery.value.trim()) return users.value
  const q = searchQuery.value.toLowerCase()
  return users.value.filter(u =>
    u.username.toLowerCase().includes(q)
    || u.profile?.name?.toLowerCase().includes(q)
    || u.profile?.decrypted_name?.toLowerCase().includes(q)
  )
})

function getUserName(user: Account): string {
  return user.profile?.decrypted_name || user.profile?.name || user.username
}

function formatDate(dateStr: string | null): string {
  if (!dateStr) return '-'
  const d = new Date(dateStr)
  return `${String(d.getMonth() + 1).padStart(2, '0')}/${String(d.getDate()).padStart(2, '0')}`
}

function goToDetail(user: Account) {
  router.push({ name: 'user-detail', params: { id: user.account_id } })
}
</script>

<template>
  <div class="px-4 py-4">
    <!-- 검색바 -->
    <div class="sticky top-0 z-10 pb-3 bg-[#FAFAFA]">
      <div class="relative">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-[#B0B0B0]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="11" cy="11" r="8" />
          <line x1="21" y1="21" x2="16.65" y2="16.65" />
        </svg>
        <input
          v-model="searchQuery"
          type="text"
          placeholder="이름 또는 아이디 검색"
          class="w-full bg-white border border-[#E8E8E8] rounded-[12px] pl-10 pr-4 py-3 text-[15px] text-[#333] placeholder-[#B0B0B0] focus:border-[#4CAF50] focus:outline-none transition-colors"
        />
      </div>
    </div>

    <!-- 로딩 -->
    <div v-if="loading" class="text-center py-10">
      <div class="w-8 h-8 border-3 border-[#4CAF50] border-t-transparent rounded-full animate-spin mx-auto"></div>
      <p class="text-[13px] text-[#888] mt-3">불러오는 중...</p>
    </div>

    <!-- 리스트 -->
    <div v-else class="space-y-2.5">
      <button
        v-for="user in filteredUsers"
        :key="user.account_id"
        class="w-full bg-white rounded-[16px] shadow-sm px-5 py-4 text-left active:scale-[0.98] transition-transform"
        @click="goToDetail(user)"
      >
        <div class="flex items-center justify-between mb-1">
          <div class="flex items-center gap-2">
            <div
              class="w-2.5 h-2.5 rounded-full"
              :class="user.is_active ? 'bg-[#4CAF50]' : 'bg-[#DDD]'"
            />
            <span class="text-[15px] font-semibold text-[#333]">{{ getUserName(user) }}</span>
          </div>
          <StatusBadge
            :label="user.is_active ? '활성' : '비활성'"
            :variant="user.is_active ? 'success' : 'default'"
          />
        </div>
        <p class="text-[13px] text-[#888] ml-[18px]">{{ user.username }}</p>
        <p class="text-[13px] text-[#888] ml-[18px] mt-0.5">
          마지막 로그인: {{ formatDate(user.last_login_at) }}
        </p>
      </button>

      <p v-if="filteredUsers.length === 0 && !loading" class="text-center text-[13px] text-[#888] py-10">
        검색 결과가 없습니다.
      </p>
    </div>

    <!-- 하단 카운트 -->
    <p v-if="!loading" class="text-center text-[13px] text-[#888] mt-4">
      총 {{ filteredUsers.length }}명
    </p>
  </div>
</template>
