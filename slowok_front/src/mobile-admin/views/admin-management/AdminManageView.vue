<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { adminApi } from '@shared/api/adminApi'
import type { Account, Institution, AdminPermission } from '@shared/types'
import { useToastStore } from '@shared/stores/toastStore'
import BottomSheet from '../../components/BottomSheet.vue'

const router = useRouter()
const toast = useToastStore()

const admins = ref<Account[]>([])
const institutions = ref<Institution[]>([])
const loading = ref(true)
const searchQuery = ref('')

const showForm = ref(false)
const editingAdmin = ref<Account | null>(null)
const saving = ref(false)
const formError = ref('')
const form = ref({ username: '', password: '', role: 'ADMIN' as 'ADMIN' | 'TEST', name: '', phone: '', email: '', institution_id: null as number | null })

// 권한 편집
const showPermSheet = ref(false)
const permAdmin = ref<Account | null>(null)
const allPermissions = ref<AdminPermission[]>([])
const selectedPermIds = ref<Set<number>>(new Set())
const permSaving = ref(false)

const filteredAdmins = computed(() => {
  if (!searchQuery.value.trim()) return admins.value
  const q = searchQuery.value.toLowerCase()
  return admins.value.filter(a => a.username.toLowerCase().includes(q) || (a.profile?.name ?? '').toLowerCase().includes(q))
})

async function fetchData() {
  loading.value = true
  try {
    const [aRes, iRes] = await Promise.all([adminApi.getAdmins(), adminApi.getInstitutions()])
    if (aRes.data.success) admins.value = aRes.data.data
    if (iRes.data.success) institutions.value = iRes.data.data
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    toast.error(err.response?.data?.message || '데이터를 불러오지 못했습니다.')
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editingAdmin.value = null
  form.value = { username: '', password: '', role: 'ADMIN', name: '', phone: '', email: '', institution_id: null }
  formError.value = ''
  showForm.value = true
}

function openEdit(admin: Account) {
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
  formError.value = ''
  showForm.value = true
}

async function handleSave() {
  if (!editingAdmin.value && (!form.value.username.trim() || !form.value.password)) {
    formError.value = '아이디와 비밀번호를 입력해주세요.'
    return
  }
  saving.value = true
  formError.value = ''
  try {
    if (editingAdmin.value) {
      const data: Record<string, unknown> = { role: form.value.role, name: form.value.name, phone: form.value.phone || null, email: form.value.email || null, institution_id: form.value.institution_id }
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
    showForm.value = false
    await fetchData()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    formError.value = err.response?.data?.message || '저장에 실패했습니다.'
  } finally {
    saving.value = false
  }
}

async function handleDelete(admin: Account) {
  if (!confirm(`"${admin.profile?.name ?? admin.username}" 관리자를 삭제하시겠습니까?`)) return
  try {
    await adminApi.deleteAdmin(admin.account_id)
    await fetchData()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    toast.error(err.response?.data?.message || '삭제에 실패했습니다.')
  }
}

async function openPermissions(admin: Account) {
  permAdmin.value = admin
  try {
    const [permsRes, adminPermsRes] = await Promise.all([
      adminApi.getPermissions(),
      adminApi.getAdminPermissions(admin.account_id),
    ])
    if (permsRes.data.success) allPermissions.value = permsRes.data.data
    if (adminPermsRes.data.success) selectedPermIds.value = new Set(adminPermsRes.data.data.permission_ids)
  } catch { /* ignore */ }
  showPermSheet.value = true
}

function togglePerm(id: number) {
  const s = new Set(selectedPermIds.value)
  if (s.has(id)) s.delete(id)
  else s.add(id)
  selectedPermIds.value = s
}

async function savePermissions() {
  if (!permAdmin.value) return
  permSaving.value = true
  try {
    await adminApi.updateAdminPermissions(permAdmin.value.account_id, Array.from(selectedPermIds.value))
    toast.success('권한이 저장되었습니다.')
    showPermSheet.value = false
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    toast.error(err.response?.data?.message || '권한 저장에 실패했습니다.')
  } finally {
    permSaving.value = false
  }
}

function getInstitutionName(id: number | null): string {
  if (!id) return '-'
  return institutions.value.find(i => i.institution_id === id)?.name || '-'
}

onMounted(fetchData)
</script>

<template>
  <div class="min-h-screen bg-[#FAFAFA]">
    <header class="sticky top-0 z-40 bg-white border-b border-[#E8E8E8] h-[56px] flex items-center px-4">
      <button @click="router.back()" class="w-10 h-10 flex items-center justify-center">
        <svg class="w-5 h-5 text-[#333]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
      </button>
      <h1 class="flex-1 text-center text-[16px] font-bold text-[#333]">관리자 관리</h1>
      <button @click="openCreate" class="w-10 h-10 flex items-center justify-center text-[#4CAF50]">
        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      </button>
    </header>

    <div class="sticky top-[56px] z-30 bg-[#FAFAFA] px-4 pt-3 pb-2">
      <input v-model="searchQuery" type="text" placeholder="이름/아이디 검색" class="w-full bg-white border border-[#E8E8E8] rounded-[12px] px-4 py-3 text-[14px] focus:outline-none focus:border-[#4CAF50]" />
    </div>

    <div v-if="loading" class="flex justify-center py-20">
      <div class="w-8 h-8 border-3 border-[#4CAF50] border-t-transparent rounded-full animate-spin" />
    </div>

    <div v-else class="px-4 pb-4 space-y-3">
      <div v-for="admin in filteredAdmins" :key="admin.account_id" class="bg-white rounded-[16px] shadow-sm border border-[#E8E8E8] p-4">
        <p class="text-[15px] font-semibold text-[#333]">{{ admin.profile?.name ?? admin.username }}</p>
        <div class="flex flex-wrap items-center gap-2 mt-1">
          <span class="text-[12px] text-[#888]">{{ admin.username }}</span>
          <span class="text-[11px] font-medium px-2 py-0.5 rounded-[6px]" :class="admin.role === 'MASTER' ? 'bg-purple-50 text-purple-600' : 'bg-blue-50 text-blue-600'">{{ admin.role }}</span>
          <span class="text-[11px] px-2 py-0.5 rounded-[6px]" :class="admin.is_active ? 'bg-green-50 text-green-600' : 'bg-gray-100 text-[#888]'">{{ admin.is_active ? '활성' : '비활성' }}</span>
        </div>
        <p class="text-[12px] text-[#888] mt-1">{{ getInstitutionName(admin.institution_id) }}</p>
        <div class="flex gap-2 mt-3 overflow-x-auto">
          <button @click="openPermissions(admin)" class="shrink-0 text-[12px] font-medium px-3 py-1.5 bg-[#F3E5F5] text-purple-600 rounded-[8px] active:opacity-70">권한</button>
          <button @click="openEdit(admin)" class="shrink-0 text-[12px] font-medium px-3 py-1.5 bg-[#E8F5E9] text-[#4CAF50] rounded-[8px] active:opacity-70">수정</button>
          <button @click="handleDelete(admin)" class="shrink-0 text-[12px] font-medium px-3 py-1.5 bg-[#FFF0F0] text-[#FF4444] rounded-[8px] active:opacity-70">삭제</button>
        </div>
      </div>
    </div>

    <!-- 생성/수정 폼 -->
    <BottomSheet v-model="showForm" :title="editingAdmin ? '관리자 수정' : '관리자 추가'" max-height="100vh">
      <div class="space-y-4 pb-4">
        <div v-if="formError" class="bg-red-50 text-[#FF4444] text-[13px] px-4 py-2.5 rounded-[10px]">{{ formError }}</div>
        <div v-if="!editingAdmin">
          <label class="block text-[13px] font-medium text-[#555] mb-1">아이디 <span class="text-[#FF4444]">*</span></label>
          <input v-model="form.username" type="text" class="w-full px-4 py-3.5 border border-[#E8E8E8] rounded-[12px] text-[15px] focus:outline-none focus:border-[#4CAF50]" />
        </div>
        <div>
          <label class="block text-[13px] font-medium text-[#555] mb-1">비밀번호 {{ editingAdmin ? '' : '*' }}</label>
          <input v-model="form.password" type="password" :placeholder="editingAdmin ? '변경 시 입력' : ''" class="w-full px-4 py-3.5 border border-[#E8E8E8] rounded-[12px] text-[15px] focus:outline-none focus:border-[#4CAF50]" />
        </div>
        <div>
          <label class="block text-[13px] font-medium text-[#555] mb-1">이름</label>
          <input v-model="form.name" type="text" class="w-full px-4 py-3.5 border border-[#E8E8E8] rounded-[12px] text-[15px] focus:outline-none focus:border-[#4CAF50]" />
        </div>
        <div>
          <label class="block text-[13px] font-medium text-[#555] mb-1">역할</label>
          <div class="flex gap-2">
            <button @click="form.role = 'ADMIN'" :class="form.role === 'ADMIN' ? 'bg-[#4CAF50] text-white' : 'bg-[#F0F0F0] text-[#666]'" class="flex-1 py-3 rounded-[12px] text-[14px] font-medium">ADMIN</button>
            <button @click="form.role = 'TEST'" :class="form.role === 'TEST' ? 'bg-[#4CAF50] text-white' : 'bg-[#F0F0F0] text-[#666]'" class="flex-1 py-3 rounded-[12px] text-[14px] font-medium">TEST</button>
          </div>
        </div>
        <div>
          <label class="block text-[13px] font-medium text-[#555] mb-1">소속 기관</label>
          <select v-model="form.institution_id" class="w-full px-4 py-3.5 border border-[#E8E8E8] rounded-[12px] text-[15px] bg-white focus:outline-none focus:border-[#4CAF50]">
            <option :value="null">없음</option>
            <option v-for="inst in institutions" :key="inst.institution_id" :value="inst.institution_id">{{ inst.name }}</option>
          </select>
        </div>

        <button @click="handleSave" :disabled="saving" class="w-full py-3.5 bg-[#4CAF50] text-white rounded-[12px] text-[15px] font-semibold disabled:opacity-50 active:bg-[#388E3C]">
          {{ saving ? '저장 중...' : (editingAdmin ? '수정' : '추가') }}
        </button>
      </div>
    </BottomSheet>

    <!-- 권한 편집 -->
    <BottomSheet v-model="showPermSheet" :title="`${permAdmin?.profile?.name ?? permAdmin?.username ?? ''} 권한`" max-height="85vh">
      <div class="space-y-2 pb-4">
        <div
          v-for="perm in allPermissions"
          :key="perm.permission_id"
          @click="togglePerm(perm.permission_id)"
          class="flex items-center gap-3 px-4 py-3 rounded-[12px] active:bg-[#F0F0F0]"
          :class="selectedPermIds.has(perm.permission_id) ? 'bg-[#E8F5E9]' : ''"
        >
          <input type="checkbox" :checked="selectedPermIds.has(perm.permission_id)" class="w-5 h-5 accent-[#4CAF50] pointer-events-none" />
          <div>
            <p class="text-[14px] text-[#333]">{{ perm.permission_name }}</p>
            <p v-if="perm.description" class="text-[11px] text-[#888]">{{ perm.description }}</p>
          </div>
        </div>
        <button @click="savePermissions" :disabled="permSaving" class="w-full py-3.5 bg-[#4CAF50] text-white rounded-[12px] text-[15px] font-semibold disabled:opacity-50 active:bg-[#388E3C] mt-3">
          {{ permSaving ? '저장 중...' : '권한 저장' }}
        </button>
      </div>
    </BottomSheet>
  </div>
</template>
