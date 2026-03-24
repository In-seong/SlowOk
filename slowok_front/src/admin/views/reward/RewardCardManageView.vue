<template>
  <div class="p-6">
    <!-- 헤더 -->
    <div class="flex items-center justify-between mb-4">
      <p class="text-[14px] text-[#888]">보상 카드를 관리합니다.</p>
      <button
        @click="openCreateModal()"
        class="bg-[#4CAF50] text-white px-4 py-2 rounded-[12px] text-[13px] font-medium hover:bg-[#388E3C] transition-colors"
      >
        + 카드 추가
      </button>
    </div>

    <!-- 로딩 -->
    <div v-if="loading" class="flex justify-center items-center py-20">
      <div class="w-8 h-8 border-3 border-[#4CAF50] border-t-transparent rounded-full animate-spin"></div>
    </div>

    <!-- 카드 목록 -->
    <div v-else>
      <div v-if="cards.length === 0" class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] p-10 text-center">
        <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-[#F5F5F5] flex items-center justify-center">
          <svg class="w-6 h-6 text-[#BDBDBD]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 11.25v8.25a1.5 1.5 0 01-1.5 1.5H5.25a1.5 1.5 0 01-1.5-1.5v-8.25M12 4.875A2.625 2.625 0 109.375 7.5H12m0-2.625V7.5m0-2.625A2.625 2.625 0 1114.625 7.5H12m0 0V21m-8.625-9.75h18c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125h-18c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" /></svg>
        </div>
        <p class="text-[14px] text-[#888]">등록된 보상 카드가 없습니다.</p>
        <p class="text-[12px] text-[#AAA] mt-1">보상 카드를 추가하여 학습 동기를 부여하세요.</p>
      </div>

      <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div
          v-for="card in cards"
          :key="card.card_id"
          class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] overflow-hidden"
        >
          <!-- 카드 이미지 -->
          <div class="h-40 bg-[#F5F5F5] flex items-center justify-center">
            <img
              v-if="card.image_url"
              :src="card.image_url"
              :alt="card.name"
              class="h-full w-full object-cover"
            />
            <svg v-else class="w-12 h-12 text-[#BDBDBD]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0022.5 18.75V5.25A2.25 2.25 0 0020.25 3H3.75A2.25 2.25 0 001.5 5.25v13.5A2.25 2.25 0 003.75 21zM8.25 8.25a1.125 1.125 0 100-2.25 1.125 1.125 0 000 2.25z" /></svg>
          </div>

          <!-- 카드 정보 -->
          <div class="p-4">
            <div class="flex items-center justify-between mb-1">
              <h3 class="text-[14px] font-bold text-[#333]">{{ card.name }}</h3>
              <span
                class="px-2 py-0.5 rounded-full text-[11px] font-semibold"
                :class="rarityClass(card.rarity)"
              >
                {{ card.rarity || '일반' }}
              </span>
            </div>
            <p class="text-[12px] text-[#888] mb-3 line-clamp-2">{{ card.description || '-' }}</p>
            <div class="flex gap-2">
              <button
                @click="openEditModal(card)"
                class="flex-1 py-1.5 text-[12px] font-medium text-[#4CAF50] border border-[#4CAF50] rounded-[8px] hover:bg-[#E8F5E9] transition-colors"
              >
                수정
              </button>
              <button
                @click="handleDelete(card)"
                class="flex-1 py-1.5 text-[12px] font-medium text-[#F44336] border border-[#F44336] rounded-[8px] hover:bg-[#FFEBEE] transition-colors"
              >
                삭제
              </button>
            </div>
          </div>
        </div>
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
            {{ editingId ? '카드 수정' : '카드 추가' }}
          </h2>

          <div v-if="modalError" class="bg-red-50 text-[#F44336] text-[13px] px-4 py-2.5 rounded-[10px] mb-4">
            {{ modalError }}
          </div>

          <!-- 카드명 -->
          <div class="mb-4">
            <label class="block text-[13px] font-medium text-[#555] mb-1">카드명 <span class="text-[#F44336]">*</span></label>
            <input
              v-model="form.name"
              type="text"
              placeholder="예: 골드 스타"
              class="w-full px-3 py-2.5 border border-[#E8E8E8] rounded-[12px] text-[14px] focus:outline-none focus:border-[#4CAF50] transition-colors"
            />
          </div>

          <!-- 희귀도 -->
          <div class="mb-4">
            <label class="block text-[13px] font-medium text-[#555] mb-1">희귀도</label>
            <select
              v-model="form.rarity"
              class="w-full px-3 py-2.5 border border-[#E8E8E8] rounded-[12px] text-[14px] focus:outline-none focus:border-[#4CAF50] transition-colors bg-white"
            >
              <option value="">일반</option>
              <option value="COMMON">COMMON</option>
              <option value="RARE">RARE</option>
              <option value="EPIC">EPIC</option>
              <option value="LEGENDARY">LEGENDARY</option>
            </select>
          </div>

          <!-- 이미지 URL -->
          <div class="mb-4">
            <label class="block text-[13px] font-medium text-[#555] mb-1">이미지 URL</label>
            <input
              v-model="form.image_url"
              type="text"
              placeholder="https://..."
              class="w-full px-3 py-2.5 border border-[#E8E8E8] rounded-[12px] text-[14px] focus:outline-none focus:border-[#4CAF50] transition-colors"
            />
          </div>

          <!-- 설명 -->
          <div class="mb-6">
            <label class="block text-[13px] font-medium text-[#555] mb-1">설명</label>
            <textarea
              v-model="form.description"
              rows="2"
              placeholder="카드에 대한 설명"
              class="w-full px-3 py-2.5 border border-[#E8E8E8] rounded-[12px] text-[14px] focus:outline-none focus:border-[#4CAF50] transition-colors resize-none"
            ></textarea>
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
import type { RewardCard } from '@shared/types'
import { useToastStore } from '@shared/stores/toastStore'

const toast = useToastStore()

const cards = ref<RewardCard[]>([])
const loading = ref(false)
const showModal = ref(false)
const editingId = ref<number | null>(null)
const saving = ref(false)
const modalError = ref('')

const form = ref({ name: '', description: '', image_url: '', rarity: '' })

function rarityClass(rarity: string): string {
  switch (rarity) {
    case 'LEGENDARY': return 'bg-[#FFF8E1] text-[#FF8F00]'
    case 'EPIC': return 'bg-[#F3E5F5] text-[#9C27B0]'
    case 'RARE': return 'bg-[#E3F2FD] text-[#2196F3]'
    default: return 'bg-[#F5F5F5] text-[#888]'
  }
}

async function fetchCards() {
  loading.value = true
  try {
    const res = await adminApi.getRewardCards()
    if (res.data.success) cards.value = res.data.data
  } catch (e: any) {
    toast.error(e.response?.data?.message || '데이터를 불러오지 못했습니다.')
  } finally {
    loading.value = false
  }
}

function openCreateModal() {
  editingId.value = null
  form.value = { name: '', description: '', image_url: '', rarity: '' }
  modalError.value = ''
  showModal.value = true
}

function openEditModal(card: RewardCard) {
  editingId.value = card.card_id
  form.value = {
    name: card.name,
    description: card.description || '',
    image_url: card.image_url || '',
    rarity: card.rarity || '',
  }
  modalError.value = ''
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  editingId.value = null
}

async function handleSubmit() {
  if (!form.value.name.trim()) {
    modalError.value = '카드명을 입력해주세요.'
    return
  }

  saving.value = true
  modalError.value = ''

  const payload: Partial<RewardCard> = {
    name: form.value.name.trim(),
    description: form.value.description.trim() || undefined,
    image_url: form.value.image_url.trim() || undefined,
    rarity: form.value.rarity || undefined,
  }

  try {
    if (editingId.value) {
      await adminApi.updateRewardCard(editingId.value, payload)
    } else {
      await adminApi.createRewardCard(payload)
    }
    closeModal()
    await fetchCards()
  } catch (e: any) {
    modalError.value = e.response?.data?.message || '저장에 실패했습니다.'
  } finally {
    saving.value = false
  }
}

async function handleDelete(card: RewardCard) {
  if (!confirm(`"${card.name}" 카드를 삭제하시겠습니까?`)) return

  try {
    await adminApi.deleteRewardCard(card.card_id)
    await fetchCards()
  } catch (e: any) {
    toast.error(e.response?.data?.message || '삭제에 실패했습니다.')
  }
}

onMounted(fetchCards)
</script>
