<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { adminApi } from '@shared/api/adminApi'
import type { Institution } from '@shared/types'
import { useToastStore } from '@shared/stores/toastStore'
import BottomSheet from '../../components/BottomSheet.vue'

const router = useRouter()
const toast = useToastStore()

const institutions = ref<Institution[]>([])
const loading = ref(true)

const showSheet = ref(false)
const editingId = ref<number | null>(null)
const saving = ref(false)
const sheetError = ref('')

const form = ref({ name: '', type: '', phone: '', email: '', address: '', invite_code: '' })

function formatContact(info: Record<string, any> | null): string {
  if (!info) return '-'
  return info.phone || info.email || '-'
}

async function fetchData() {
  loading.value = true
  try {
    const res = await adminApi.getInstitutions()
    if (res.data.success) institutions.value = res.data.data
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    toast.error(err.response?.data?.message || '데이터를 불러오지 못했습니다.')
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editingId.value = null
  form.value = { name: '', type: '', phone: '', email: '', address: '', invite_code: '' }
  sheetError.value = ''
  showSheet.value = true
}

function openEdit(inst: Institution) {
  editingId.value = inst.institution_id
  const ci = inst.contact_info || {}
  form.value = {
    name: inst.name,
    type: inst.type || '',
    phone: ci.phone || '',
    email: ci.email || '',
    address: ci.address || inst.address || '',
    invite_code: inst.invite_code || '',
  }
  sheetError.value = ''
  showSheet.value = true
}

function generateInviteCode() {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'
  let code = ''
  for (let i = 0; i < 6; i++) code += chars[Math.floor(Math.random() * chars.length)]
  form.value.invite_code = code
}

async function handleSubmit() {
  if (!form.value.name.trim()) { sheetError.value = '기관명을 입력해주세요.'; return }
  saving.value = true
  sheetError.value = ''
  const payload = {
    name: form.value.name.trim(),
    type: form.value.type || null,
    contact_info: { phone: form.value.phone, email: form.value.email, address: form.value.address },
    address: form.value.address || null,
    invite_code: form.value.invite_code || null,
  }
  try {
    if (editingId.value) {
      await adminApi.updateInstitution(editingId.value, payload)
    } else {
      await adminApi.createInstitution(payload)
    }
    showSheet.value = false
    await fetchData()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    sheetError.value = err.response?.data?.message || '저장에 실패했습니다.'
  } finally {
    saving.value = false
  }
}

async function handleDelete(inst: Institution) {
  if (!confirm(`"${inst.name}" 기관을 삭제하시겠습니까?`)) return
  try {
    await adminApi.deleteInstitution(inst.institution_id)
    await fetchData()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    toast.error(err.response?.data?.message || '삭제에 실패했습니다.')
  }
}

onMounted(fetchData)
</script>

<template>
  <div class="min-h-screen bg-[#FAFAFA]">
    <header class="sticky top-0 z-40 bg-white border-b border-[#E8E8E8] h-[56px] flex items-center px-4">
      <button @click="router.back()" class="w-10 h-10 flex items-center justify-center">
        <svg class="w-5 h-5 text-[#333]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
      </button>
      <h1 class="flex-1 text-center text-[16px] font-bold text-[#333]">기관 관리</h1>
      <button @click="openCreate" class="w-10 h-10 flex items-center justify-center text-[#4CAF50]">
        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      </button>
    </header>

    <div v-if="loading" class="flex justify-center py-20">
      <div class="w-8 h-8 border-3 border-[#4CAF50] border-t-transparent rounded-full animate-spin" />
    </div>

    <div v-else class="px-4 py-4 space-y-3">
      <div v-if="institutions.length === 0" class="bg-white rounded-[16px] shadow-sm border border-[#E8E8E8] p-8 text-center">
        <p class="text-[14px] text-[#888]">등록된 기관이 없습니다.</p>
      </div>

      <div v-for="inst in institutions" :key="inst.institution_id" class="bg-white rounded-[16px] shadow-sm border border-[#E8E8E8] p-4">
        <p class="text-[15px] font-semibold text-[#333]">{{ inst.name }}</p>
        <div class="flex items-center gap-2 mt-1.5">
          <span v-if="inst.type" class="text-[12px] text-[#888]">{{ inst.type }}</span>
          <span v-if="inst.invite_code" class="text-[11px] font-mono px-2 py-0.5 bg-[#F0F0F0] rounded-[6px] text-[#555]">{{ inst.invite_code }}</span>
        </div>
        <p v-if="inst.contact_info" class="text-[12px] text-[#888] mt-1">{{ formatContact(inst.contact_info) }}</p>
        <div class="flex justify-end gap-3 mt-3">
          <button @click="openEdit(inst)" class="text-[13px] font-medium text-[#4CAF50] active:opacity-70">수정</button>
          <button @click="handleDelete(inst)" class="text-[13px] font-medium text-[#FF4444] active:opacity-70">삭제</button>
        </div>
      </div>
    </div>

    <BottomSheet v-model="showSheet" :title="editingId ? '기관 수정' : '기관 추가'" max-height="90vh">
      <div class="space-y-4 pb-4">
        <div v-if="sheetError" class="bg-red-50 text-[#FF4444] text-[13px] px-4 py-2.5 rounded-[10px]">{{ sheetError }}</div>

        <div>
          <label class="block text-[13px] font-medium text-[#555] mb-1">기관명 <span class="text-[#FF4444]">*</span></label>
          <input v-model="form.name" type="text" placeholder="예: 서울특별시교육청" class="w-full px-4 py-3.5 border border-[#E8E8E8] rounded-[12px] text-[15px] focus:outline-none focus:border-[#4CAF50]" />
        </div>
        <div>
          <label class="block text-[13px] font-medium text-[#555] mb-1">유형</label>
          <input v-model="form.type" type="text" placeholder="예: 교육청, 학교, 복지관" class="w-full px-4 py-3.5 border border-[#E8E8E8] rounded-[12px] text-[15px] focus:outline-none focus:border-[#4CAF50]" />
        </div>
        <div>
          <label class="block text-[13px] font-medium text-[#555] mb-1">전화번호</label>
          <input v-model="form.phone" type="text" placeholder="02-1234-5678" class="w-full px-4 py-3.5 border border-[#E8E8E8] rounded-[12px] text-[15px] focus:outline-none focus:border-[#4CAF50]" />
        </div>
        <div>
          <label class="block text-[13px] font-medium text-[#555] mb-1">이메일</label>
          <input v-model="form.email" type="text" placeholder="info@example.com" class="w-full px-4 py-3.5 border border-[#E8E8E8] rounded-[12px] text-[15px] focus:outline-none focus:border-[#4CAF50]" />
        </div>
        <div>
          <label class="block text-[13px] font-medium text-[#555] mb-1">주소</label>
          <input v-model="form.address" type="text" placeholder="서울시 강남구 ..." class="w-full px-4 py-3.5 border border-[#E8E8E8] rounded-[12px] text-[15px] focus:outline-none focus:border-[#4CAF50]" />
        </div>
        <div>
          <label class="block text-[13px] font-medium text-[#555] mb-1">초대코드</label>
          <div class="flex gap-2">
            <input v-model="form.invite_code" type="text" placeholder="초대코드" maxlength="20" class="flex-1 px-4 py-3.5 border border-[#E8E8E8] rounded-[12px] text-[15px] focus:outline-none focus:border-[#4CAF50]" />
            <button @click="generateInviteCode" class="px-4 py-3.5 border border-[#4CAF50] text-[#4CAF50] rounded-[12px] text-[13px] font-medium active:bg-[#E8F5E9] whitespace-nowrap">자동생성</button>
          </div>
        </div>

        <button @click="handleSubmit" :disabled="saving" class="w-full py-3.5 bg-[#4CAF50] text-white rounded-[12px] text-[15px] font-semibold disabled:opacity-50 active:bg-[#388E3C]">
          {{ saving ? '저장 중...' : (editingId ? '수정' : '추가') }}
        </button>
      </div>
    </BottomSheet>
  </div>
</template>
