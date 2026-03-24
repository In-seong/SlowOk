<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { adminApi } from '@shared/api/adminApi'
import type { Account, Institution } from '@shared/types'
import { useToastStore } from '@shared/stores/toastStore'

const router = useRouter()
const toast = useToastStore()

const admins = ref<Account[]>([])
const loading = ref(true)
const error = ref('')
const searchQuery = ref('')

const showModal = ref(false)
const editingAdmin = ref<Account | null>(null)
const saving = ref(false)

const institutions = ref<Institution[]>([])

const form = ref({
  username: '',
  password: '',
  role: 'ADMIN' as 'ADMIN' | 'TEST',
  name: '',
  phone: '',
  email: '',
  institution_id: null as number | null,
})

const filteredAdmins = computed(() => {
  if (!searchQuery.value.trim()) return admins.value
  const q = searchQuery.value.toLowerCase()
  return admins.value.filter(
    (a) =>
      a.username.toLowerCase().includes(q) ||
      (a.profile?.name ?? '').toLowerCase().includes(q),
  )
})

async function fetchAdmins() {
  loading.value = true
  error.value = ''
  try {
    const [adminsRes, instRes] = await Promise.all([
      adminApi.getAdmins(),
      adminApi.getInstitutions(),
    ])
    if (adminsRes.data.success) {
      admins.value = adminsRes.data.data
    }
    if (instRes.data.success) {
      institutions.value = instRes.data.data
    }
  } catch (e: any) {
    error.value = e.response?.data?.message || '관리자 목록을 불러오지 못했습니다.'
  } finally {
    loading.value = false
  }
}

function openCreateModal() {
  editingAdmin.value = null
  form.value = { username: '', password: '', role: 'ADMIN', name: '', phone: '', email: '', institution_id: null }
  showModal.value = true
}

function openEditModal(admin: Account) {
  editingAdmin.value = admin
  form.value = {
    username: admin.username,
    password: '',
    role: admin.role as 'ADMIN' | 'TEST',
    name: admin.profile?.name ?? '',
    phone: admin.profile?.phone ?? '',
    email: admin.profile?.email ?? '',
    institution_id: admin.institution_id,
  }
  showModal.value = true
}

async function handleSave() {
  saving.value = true
  try {
    if (editingAdmin.value) {
      const data: Record<string, any> = {
        role: form.value.role,
        name: form.value.name,
        phone: form.value.phone || null,
        email: form.value.email || null,
        institution_id: form.value.institution_id,
      }
      if (form.value.password) data.password = form.value.password
      await adminApi.updateAdmin(editingAdmin.value.account_id, data)
    } else {
      await adminApi.createAdmin({
        username: form.value.username,
        password: form.value.password,
        role: form.value.role,
        name: form.value.name,
        phone: form.value.phone || undefined,
        email: form.value.email || undefined,
        institution_id: form.value.institution_id,
      })
    }
    showModal.value = false
    await fetchAdmins()
  } catch (e: any) {
    toast.error(e.response?.data?.message || '저장에 실패했습니다.')
  } finally {
    saving.value = false
  }
}

async function toggleActive(admin: Account) {
  try {
    await adminApi.updateAdmin(admin.account_id, { is_active: !admin.is_active })
    admin.is_active = !admin.is_active
  } catch (e: any) {
    toast.error(e.response?.data?.message || '상태 변경에 실패했습니다.')
  }
}

async function handleDelete(admin: Account) {
  if (!confirm(`${admin.profile?.name ?? admin.username} 관리자를 비활성화하시겠습니까?`)) return
  try {
    await adminApi.deleteAdmin(admin.account_id)
    admin.is_active = false
  } catch (e: any) {
    toast.error(e.response?.data?.message || '비활성화에 실패했습니다.')
  }
}

onMounted(fetchAdmins)
</script>

<template>
  <div class="p-6">
    <div class="flex items-center justify-between mb-6">
      <h2 class="text-xl font-bold text-[#333]">관리자 관리</h2>
      <button
        @click="openCreateModal"
        class="px-4 py-2 bg-[#4CAF50] text-white rounded-[10px] text-[14px] font-medium hover:bg-[#43A047] transition-colors"
      >
        + 관리자 추가
      </button>
    </div>

    <input
      v-model="searchQuery"
      type="text"
      placeholder="이름 또는 아이디로 검색..."
      class="w-full max-w-[400px] bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] px-4 py-3 text-[15px] focus:border-[#4CAF50] focus:outline-none transition-colors mb-4"
    />

    <div v-if="loading" class="text-center py-10 text-[#888]">불러오는 중...</div>
    <div v-else-if="error" class="text-center py-10 text-red-500">{{ error }}</div>
    <div v-else-if="filteredAdmins.length === 0" class="text-center py-10 text-[#888]">관리자가 없습니다.</div>
    <div v-else class="bg-white rounded-[16px] border border-[#E8E8E8] overflow-hidden">
      <table class="w-full text-[14px]">
        <thead>
          <tr class="bg-[#F8F8F8] text-[#666]">
            <th class="text-left px-4 py-3 font-medium">아이디</th>
            <th class="text-left px-4 py-3 font-medium">이름</th>
            <th class="text-left px-4 py-3 font-medium">역할</th>
            <th class="text-left px-4 py-3 font-medium">소속 기관</th>
            <th class="text-center px-4 py-3 font-medium">상태</th>
            <th class="text-center px-4 py-3 font-medium">관리</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="admin in filteredAdmins"
            :key="admin.account_id"
            class="border-t border-[#F0F0F0] hover:bg-[#FAFAFA]"
          >
            <td class="px-4 py-3">{{ admin.username }}</td>
            <td class="px-4 py-3">{{ admin.profile?.name ?? '-' }}</td>
            <td class="px-4 py-3">
              <span
                class="px-2 py-0.5 rounded-full text-[12px] font-medium"
                :class="admin.role === 'ADMIN' ? 'bg-blue-50 text-blue-600' : 'bg-orange-50 text-orange-600'"
              >
                {{ admin.role }}
              </span>
            </td>
            <td class="px-4 py-3 text-[13px] text-[#555]">{{ admin.institution?.name ?? '미지정' }}</td>
            <td class="px-4 py-3 text-center">
              <button
                @click="toggleActive(admin)"
                class="px-2 py-0.5 rounded-full text-[12px] font-medium"
                :class="admin.is_active ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-500'"
              >
                {{ admin.is_active ? '활성' : '비활성' }}
              </button>
            </td>
            <td class="px-4 py-3 text-center">
              <button @click="openEditModal(admin)" class="text-[#4CAF50] hover:underline mr-2">수정</button>
              <button @click="router.push({ name: 'admin-permissions', params: { id: admin.account_id } })" class="text-blue-500 hover:underline mr-2">권한</button>
              <button @click="handleDelete(admin)" class="text-red-500 hover:underline">비활성화</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50" @click.self="showModal = false">
        <div class="bg-white rounded-[16px] p-6 w-full max-w-[480px] mx-4">
          <h3 class="text-lg font-bold text-[#333] mb-4">{{ editingAdmin ? '관리자 수정' : '관리자 추가' }}</h3>
          <form @submit.prevent="handleSave" class="space-y-3">
            <div v-if="!editingAdmin">
              <label class="block text-[13px] text-[#666] mb-1">아이디</label>
              <input v-model="form.username" required class="w-full border border-[#E8E8E8] rounded-[10px] px-3 py-2 text-[14px] focus:border-[#4CAF50] focus:outline-none" />
            </div>
            <div>
              <label class="block text-[13px] text-[#666] mb-1">{{ editingAdmin ? '비밀번호 (변경 시)' : '비밀번호' }}</label>
              <input v-model="form.password" type="password" :required="!editingAdmin" class="w-full border border-[#E8E8E8] rounded-[10px] px-3 py-2 text-[14px] focus:border-[#4CAF50] focus:outline-none" />
            </div>
            <div>
              <label class="block text-[13px] text-[#666] mb-1">역할</label>
              <select v-model="form.role" class="w-full border border-[#E8E8E8] rounded-[10px] px-3 py-2 text-[14px] focus:border-[#4CAF50] focus:outline-none">
                <option value="ADMIN">ADMIN</option>
                <option value="TEST">TEST</option>
              </select>
            </div>
            <div>
              <label class="block text-[13px] text-[#666] mb-1">소속 기관</label>
              <select v-model="form.institution_id" class="w-full border border-[#E8E8E8] rounded-[10px] px-3 py-2 text-[14px] focus:border-[#4CAF50] focus:outline-none">
                <option :value="null">미지정</option>
                <option v-for="inst in institutions" :key="inst.institution_id" :value="inst.institution_id">{{ inst.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-[13px] text-[#666] mb-1">이름</label>
              <input v-model="form.name" required class="w-full border border-[#E8E8E8] rounded-[10px] px-3 py-2 text-[14px] focus:border-[#4CAF50] focus:outline-none" />
            </div>
            <div>
              <label class="block text-[13px] text-[#666] mb-1">연락처</label>
              <input v-model="form.phone" class="w-full border border-[#E8E8E8] rounded-[10px] px-3 py-2 text-[14px] focus:border-[#4CAF50] focus:outline-none" />
            </div>
            <div>
              <label class="block text-[13px] text-[#666] mb-1">이메일</label>
              <input v-model="form.email" type="email" class="w-full border border-[#E8E8E8] rounded-[10px] px-3 py-2 text-[14px] focus:border-[#4CAF50] focus:outline-none" />
            </div>
            <div class="flex gap-2 pt-2">
              <button type="button" @click="showModal = false" class="flex-1 px-4 py-2.5 border border-[#E8E8E8] rounded-[10px] text-[14px] text-[#666] hover:bg-[#F5F5F5] transition-colors">취소</button>
              <button type="submit" :disabled="saving" class="flex-1 px-4 py-2.5 bg-[#4CAF50] text-white rounded-[10px] text-[14px] font-medium hover:bg-[#43A047] transition-colors disabled:opacity-50">
                {{ saving ? '저장 중...' : '저장' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </div>
</template>
