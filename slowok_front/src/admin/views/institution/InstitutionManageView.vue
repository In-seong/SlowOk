<template>
  <div class="p-6">
    <!-- 헤더 -->
    <div class="flex items-center justify-between mb-4">
      <p class="text-[14px] text-[#888]">기관 정보를 관리합니다.</p>
      <button
        @click="openCreateModal()"
        class="bg-[#4CAF50] text-white px-4 py-2 rounded-[12px] text-[13px] font-medium hover:bg-[#388E3C] transition-colors"
      >
        + 기관 추가
      </button>
    </div>

    <!-- 로딩 -->
    <div v-if="loading" class="flex justify-center items-center py-20">
      <div class="w-8 h-8 border-3 border-[#4CAF50] border-t-transparent rounded-full animate-spin"></div>
    </div>

    <!-- 기관 목록 -->
    <div v-else>
      <div v-if="institutions.length === 0" class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] p-10 text-center">
        <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-[#F5F5F5] flex items-center justify-center">
          <svg class="w-6 h-6 text-[#BDBDBD]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z" /></svg>
        </div>
        <p class="text-[14px] text-[#888]">등록된 기관이 없습니다.</p>
        <p class="text-[12px] text-[#AAA] mt-1">기관을 추가하여 관리하세요.</p>
      </div>

      <div v-else class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] overflow-hidden">
        <table class="w-full">
          <thead>
            <tr class="bg-[#F8F8F8] border-b border-[#E8E8E8]">
              <th class="text-left px-5 py-3 text-[12px] font-semibold text-[#888] uppercase">ID</th>
              <th class="text-left px-5 py-3 text-[12px] font-semibold text-[#888] uppercase">기관명</th>
              <th class="text-left px-5 py-3 text-[12px] font-semibold text-[#888] uppercase">유형</th>
              <th class="text-left px-5 py-3 text-[12px] font-semibold text-[#888] uppercase">연락처</th>
              <th class="text-left px-5 py-3 text-[12px] font-semibold text-[#888] uppercase">초대코드</th>
              <th class="text-right px-5 py-3 text-[12px] font-semibold text-[#888] uppercase">관리</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="inst in institutions"
              :key="inst.institution_id"
              class="border-b border-[#F5F5F5] hover:bg-[#FAFAFA] transition-colors"
            >
              <td class="px-5 py-4 text-[13px] text-[#888]">{{ inst.institution_id }}</td>
              <td class="px-5 py-4 text-[14px] font-medium text-[#333]">{{ inst.name }}</td>
              <td class="px-5 py-4 text-[13px] text-[#888]">{{ inst.type || '-' }}</td>
              <td class="px-5 py-4 text-[13px] text-[#888]">{{ formatContact(inst.contact_info) }}</td>
              <td class="px-5 py-4 text-[13px] text-[#888] font-mono">{{ inst.invite_code || '-' }}</td>
              <td class="px-5 py-4 text-right">
                <button
                  @click="openEditModal(inst)"
                  class="text-[#4CAF50] hover:text-[#388E3C] text-[13px] font-medium mr-3 transition-colors"
                >
                  수정
                </button>
                <button
                  @click="handleDelete(inst)"
                  class="text-[#F44336] hover:text-red-700 text-[13px] font-medium transition-colors"
                >
                  삭제
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- 추가/수정 모달 -->
    <Teleport to="body">
      <div
        v-if="showModal"
        class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
        @click.self="closeModal()"
      >
        <div class="bg-white rounded-[16px] p-6 w-full max-w-[440px]">
          <h2 class="text-[16px] font-bold text-[#333] mb-5">
            {{ editingId ? '기관 수정' : '기관 추가' }}
          </h2>

          <div v-if="modalError" class="bg-red-50 text-[#F44336] text-[13px] px-4 py-2.5 rounded-[10px] mb-4">
            {{ modalError }}
          </div>

          <!-- 기관명 -->
          <div class="mb-4">
            <label class="block text-[13px] font-medium text-[#555] mb-1">기관명 <span class="text-[#F44336]">*</span></label>
            <input
              v-model="form.name"
              type="text"
              placeholder="예: 서울특별시교육청"
              class="w-full px-3 py-2.5 border border-[#E8E8E8] rounded-[12px] text-[14px] focus:outline-none focus:border-[#4CAF50] transition-colors"
            />
          </div>

          <!-- 유형 -->
          <div class="mb-4">
            <label class="block text-[13px] font-medium text-[#555] mb-1">유형</label>
            <input
              v-model="form.type"
              type="text"
              placeholder="예: 교육청, 학교, 복지관"
              class="w-full px-3 py-2.5 border border-[#E8E8E8] rounded-[12px] text-[14px] focus:outline-none focus:border-[#4CAF50] transition-colors"
            />
          </div>

          <!-- 전화번호 -->
          <div class="mb-4">
            <label class="block text-[13px] font-medium text-[#555] mb-1">전화번호</label>
            <input
              v-model="contactPhone"
              type="text"
              placeholder="02-1234-5678"
              class="w-full px-3 py-2.5 border border-[#E8E8E8] rounded-[12px] text-[14px] focus:outline-none focus:border-[#4CAF50] transition-colors"
            />
          </div>

          <!-- 이메일 -->
          <div class="mb-4">
            <label class="block text-[13px] font-medium text-[#555] mb-1">이메일</label>
            <input
              v-model="contactEmail"
              type="text"
              placeholder="info@example.com"
              class="w-full px-3 py-2.5 border border-[#E8E8E8] rounded-[12px] text-[14px] focus:outline-none focus:border-[#4CAF50] transition-colors"
            />
          </div>

          <!-- 주소 -->
          <div class="mb-4">
            <label class="block text-[13px] font-medium text-[#555] mb-1">주소</label>
            <input
              v-model="contactAddress"
              type="text"
              placeholder="서울시 강남구 ..."
              class="w-full px-3 py-2.5 border border-[#E8E8E8] rounded-[12px] text-[14px] focus:outline-none focus:border-[#4CAF50] transition-colors"
            />
          </div>

          <!-- 초대코드 -->
          <div class="mb-6">
            <label class="block text-[13px] font-medium text-[#555] mb-1">초대코드</label>
            <div class="flex gap-2">
              <input
                v-model="inviteCode"
                type="text"
                placeholder="초대코드"
                maxlength="20"
                class="flex-1 px-3 py-2.5 border border-[#E8E8E8] rounded-[12px] text-[14px] focus:outline-none focus:border-[#4CAF50] transition-colors"
              />
              <button
                type="button"
                @click="generateInviteCode()"
                class="px-3 py-2.5 border border-[#4CAF50] text-[#4CAF50] rounded-[12px] text-[13px] font-medium hover:bg-[#E8F5E9] transition-colors whitespace-nowrap"
              >
                자동생성
              </button>
            </div>
            <p class="text-[11px] text-[#999] mt-1">학부모 가입 시 사용하는 초대코드</p>
          </div>

          <!-- 버튼 -->
          <div class="flex gap-3">
            <button
              @click="closeModal()"
              class="flex-1 py-2.5 border border-[#E8E8E8] rounded-[12px] text-[14px] font-medium text-[#888] hover:bg-[#F5F5F5] transition-colors"
            >
              취소
            </button>
            <button
              @click="handleSubmit()"
              :disabled="saving"
              class="flex-1 py-2.5 bg-[#4CAF50] text-white rounded-[12px] text-[14px] font-medium hover:bg-[#388E3C] transition-colors disabled:opacity-50"
            >
              {{ saving ? '저장 중...' : (editingId ? '수정' : '추가') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { adminApi } from '@shared/api/adminApi'
import type { Institution } from '@shared/types'
import { useToastStore } from '@shared/stores/toastStore'

const toast = useToastStore()

const institutions = ref<Institution[]>([])
const loading = ref(false)
const showModal = ref(false)
const editingId = ref<number | null>(null)
const saving = ref(false)
const modalError = ref('')

const form = ref({ name: '', type: '' })
const contactPhone = ref('')
const contactEmail = ref('')
const contactAddress = ref('')
const inviteCode = ref('')

function generateInviteCode(): void {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'
  let code = ''
  for (let i = 0; i < 6; i++) {
    const idx = Math.floor(Math.random() * chars.length)
    const ch = chars[idx]
    if (ch) code += ch
  }
  inviteCode.value = code
}

function formatContact(info: Record<string, any> | null): string {
  if (!info) return '-'
  const parts: string[] = []
  if (info.phone) parts.push(info.phone as string)
  if (info.email) parts.push(info.email as string)
  return parts.length > 0 ? parts.join(' / ') : '-'
}

async function fetchInstitutions() {
  loading.value = true
  try {
    const res = await adminApi.getInstitutions()
    if (res.data.success) institutions.value = res.data.data
  } catch (e: any) {
    toast.error(e.response?.data?.message || '데이터를 불러오지 못했습니다.')
  } finally {
    loading.value = false
  }
}

function openCreateModal() {
  editingId.value = null
  form.value = { name: '', type: '' }
  contactPhone.value = ''
  contactEmail.value = ''
  contactAddress.value = ''
  inviteCode.value = ''
  modalError.value = ''
  showModal.value = true
}

function openEditModal(inst: Institution) {
  editingId.value = inst.institution_id
  form.value = { name: inst.name, type: inst.type || '' }
  contactPhone.value = (inst.contact_info?.phone as string) || ''
  contactEmail.value = (inst.contact_info?.email as string) || ''
  contactAddress.value = (inst.contact_info?.address as string) || ''
  inviteCode.value = inst.invite_code || ''
  modalError.value = ''
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  editingId.value = null
}

async function handleSubmit() {
  if (!form.value.name.trim()) {
    modalError.value = '기관명을 입력해주세요.'
    return
  }

  saving.value = true
  modalError.value = ''

  const contactInfo: Record<string, string> = {}
  if (contactPhone.value.trim()) contactInfo.phone = contactPhone.value.trim()
  if (contactEmail.value.trim()) contactInfo.email = contactEmail.value.trim()
  if (contactAddress.value.trim()) contactInfo.address = contactAddress.value.trim()

  const payload: Record<string, any> = {
    name: form.value.name.trim(),
    type: form.value.type.trim() || null,
    contact_info: Object.keys(contactInfo).length > 0 ? contactInfo : null,
    invite_code: inviteCode.value.trim() || null,
  }

  try {
    if (editingId.value) {
      await adminApi.updateInstitution(editingId.value, payload)
    } else {
      await adminApi.createInstitution(payload)
    }
    closeModal()
    await fetchInstitutions()
  } catch (e: any) {
    modalError.value = e.response?.data?.message || '저장에 실패했습니다.'
  } finally {
    saving.value = false
  }
}

async function handleDelete(inst: Institution) {
  if (!confirm(`"${inst.name}" 기관을 삭제하시겠습니까?`)) return

  try {
    await adminApi.deleteInstitution(inst.institution_id)
    await fetchInstitutions()
  } catch (e: any) {
    toast.error(e.response?.data?.message || '삭제에 실패했습니다.')
  }
}

onMounted(fetchInstitutions)
</script>
