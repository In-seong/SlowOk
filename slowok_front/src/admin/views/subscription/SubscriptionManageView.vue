<template>
  <div class="p-6">
    <!-- 헤더 -->
    <div class="flex items-center justify-between mb-4">
      <p class="text-[14px] text-[#888]">구독 정보를 관리합니다.</p>
    </div>

    <!-- 필터 -->
    <div class="flex gap-2 mb-4">
      <button
        v-for="f in statusFilters"
        :key="f.value"
        @click="activeFilter = f.value"
        class="px-3 py-1.5 rounded-full text-[12px] font-medium transition-colors"
        :class="activeFilter === f.value
          ? 'bg-[#4CAF50] text-white'
          : 'bg-white text-[#888] hover:bg-[#F5F5F5] border border-[#E8E8E8]'"
      >
        {{ f.label }}
      </button>
    </div>

    <!-- 로딩 -->
    <div v-if="loading" class="flex justify-center items-center py-20">
      <div class="w-8 h-8 border-3 border-[#4CAF50] border-t-transparent rounded-full animate-spin"></div>
    </div>

    <!-- 구독 목록 -->
    <div v-else>
      <div v-if="filteredSubscriptions.length === 0" class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] p-10 text-center">
        <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-[#F5F5F5] flex items-center justify-center">
          <svg class="w-6 h-6 text-[#BDBDBD]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" /></svg>
        </div>
        <p class="text-[14px] text-[#888]">구독 정보가 없습니다.</p>
      </div>

      <div v-else class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] overflow-hidden">
        <table class="w-full">
          <thead>
            <tr class="bg-[#F8F8F8] border-b border-[#E8E8E8]">
              <th class="text-left px-5 py-3 text-[12px] font-semibold text-[#888] uppercase">ID</th>
              <th class="text-left px-5 py-3 text-[12px] font-semibold text-[#888] uppercase">사용자</th>
              <th class="text-left px-5 py-3 text-[12px] font-semibold text-[#888] uppercase">플랜</th>
              <th class="text-left px-5 py-3 text-[12px] font-semibold text-[#888] uppercase">상태</th>
              <th class="text-left px-5 py-3 text-[12px] font-semibold text-[#888] uppercase">시작일</th>
              <th class="text-left px-5 py-3 text-[12px] font-semibold text-[#888] uppercase">만료일</th>
              <th class="text-right px-5 py-3 text-[12px] font-semibold text-[#888] uppercase">관리</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="sub in filteredSubscriptions"
              :key="sub.subscription_id"
              class="border-b border-[#F5F5F5] hover:bg-[#FAFAFA] transition-colors"
            >
              <td class="px-5 py-4 text-[13px] text-[#888]">{{ sub.subscription_id }}</td>
              <td class="px-5 py-4 text-[14px] font-medium text-[#333]">{{ sub.account?.username || '-' }}</td>
              <td class="px-5 py-4 text-[13px] text-[#555]">{{ sub.plan_type }}</td>
              <td class="px-5 py-4">
                <span
                  class="inline-block px-2.5 py-1 rounded-full text-[11px] font-semibold"
                  :class="statusClass(sub.status)"
                >
                  {{ statusLabel(sub.status) }}
                </span>
              </td>
              <td class="px-5 py-4 text-[13px] text-[#888]">{{ formatDate(sub.started_at) }}</td>
              <td class="px-5 py-4 text-[13px] text-[#888]">{{ sub.expires_at ? formatDate(sub.expires_at) : '-' }}</td>
              <td class="px-5 py-4 text-right">
                <button
                  @click="openEditModal(sub)"
                  class="text-[#4CAF50] hover:text-[#388E3C] text-[13px] font-medium mr-3 transition-colors"
                >
                  수정
                </button>
                <button
                  @click="handleDelete(sub)"
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

    <!-- 수정 모달 -->
    <Teleport to="body">
      <div
        v-if="showModal"
        class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
        @click.self="closeModal()"
      >
        <div class="bg-white rounded-[16px] p-6 w-full max-w-[440px]">
          <h2 class="text-[16px] font-bold text-[#333] mb-5">구독 수정</h2>

          <div v-if="modalError" class="bg-red-50 text-[#F44336] text-[13px] px-4 py-2.5 rounded-[10px] mb-4">
            {{ modalError }}
          </div>

          <!-- 플랜 -->
          <div class="mb-4">
            <label class="block text-[13px] font-medium text-[#555] mb-1">플랜</label>
            <input
              v-model="editForm.plan_type"
              type="text"
              placeholder="예: BASIC, PREMIUM"
              class="w-full px-3 py-2.5 border border-[#E8E8E8] rounded-[12px] text-[14px] focus:outline-none focus:border-[#4CAF50] transition-colors"
            />
          </div>

          <!-- 상태 -->
          <div class="mb-4">
            <label class="block text-[13px] font-medium text-[#555] mb-1">상태</label>
            <select
              v-model="editForm.status"
              class="w-full px-3 py-2.5 border border-[#E8E8E8] rounded-[12px] text-[14px] focus:outline-none focus:border-[#4CAF50] transition-colors bg-white"
            >
              <option value="ACTIVE">활성</option>
              <option value="EXPIRED">만료</option>
              <option value="CANCELLED">취소</option>
            </select>
          </div>

          <!-- 만료일 -->
          <div class="mb-6">
            <label class="block text-[13px] font-medium text-[#555] mb-1">만료일</label>
            <input
              v-model="editForm.expires_at"
              type="date"
              class="w-full px-3 py-2.5 border border-[#E8E8E8] rounded-[12px] text-[14px] focus:outline-none focus:border-[#4CAF50] transition-colors"
            />
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
              {{ saving ? '저장 중...' : '수정' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { adminApi } from '@shared/api/adminApi'
import type { Subscription } from '@shared/types'
import { useToastStore } from '@shared/stores/toastStore'

const toast = useToastStore()

const subscriptions = ref<Subscription[]>([])
const loading = ref(false)
const showModal = ref(false)
const editingId = ref<number | null>(null)
const saving = ref(false)
const modalError = ref('')
const activeFilter = ref('ALL')

const editForm = ref({ plan_type: '', status: 'ACTIVE' as string, expires_at: '' })

const statusFilters = [
  { label: '전체', value: 'ALL' },
  { label: '활성', value: 'ACTIVE' },
  { label: '만료', value: 'EXPIRED' },
  { label: '취소', value: 'CANCELLED' },
]

const filteredSubscriptions = computed(() => {
  if (activeFilter.value === 'ALL') return subscriptions.value
  return subscriptions.value.filter(s => s.status === activeFilter.value)
})

function statusClass(status: string): string {
  switch (status) {
    case 'ACTIVE': return 'bg-[#E8F5E9] text-[#4CAF50]'
    case 'EXPIRED': return 'bg-[#FFF3E0] text-[#FF9800]'
    case 'CANCELLED': return 'bg-[#FFEBEE] text-[#F44336]'
    default: return 'bg-[#F5F5F5] text-[#888]'
  }
}

function statusLabel(status: string): string {
  switch (status) {
    case 'ACTIVE': return '활성'
    case 'EXPIRED': return '만료'
    case 'CANCELLED': return '취소'
    default: return status
  }
}

function formatDate(dateStr: string): string {
  if (!dateStr) return '-'
  const d = new Date(dateStr)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

async function fetchSubscriptions() {
  loading.value = true
  try {
    const res = await adminApi.getSubscriptions()
    if (res.data.success) subscriptions.value = res.data.data
  } catch (e: any) {
    toast.error(e.response?.data?.message || '데이터를 불러오지 못했습니다.')
  } finally {
    loading.value = false
  }
}

function openEditModal(sub: Subscription) {
  editingId.value = sub.subscription_id
  editForm.value = {
    plan_type: sub.plan_type,
    status: sub.status,
    expires_at: sub.expires_at ? sub.expires_at.split('T')[0] || '' : '',
  }
  modalError.value = ''
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  editingId.value = null
}

async function handleSubmit() {
  if (!editingId.value) return

  saving.value = true
  modalError.value = ''
  try {
    await adminApi.updateSubscription(editingId.value, {
      plan_type: editForm.value.plan_type,
      status: editForm.value.status as Subscription['status'],
      expires_at: editForm.value.expires_at || null,
    })
    closeModal()
    await fetchSubscriptions()
  } catch (e: any) {
    modalError.value = e.response?.data?.message || '저장에 실패했습니다.'
  } finally {
    saving.value = false
  }
}

async function handleDelete(sub: Subscription) {
  if (!confirm(`구독 #${sub.subscription_id}을 삭제하시겠습니까?`)) return

  try {
    await adminApi.deleteSubscription(sub.subscription_id)
    await fetchSubscriptions()
  } catch (e: any) {
    toast.error(e.response?.data?.message || '삭제에 실패했습니다.')
  }
}

onMounted(fetchSubscriptions)
</script>
