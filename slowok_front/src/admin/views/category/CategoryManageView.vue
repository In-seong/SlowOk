<template>
  <div class="p-6">
    <!-- 헤더 -->
    <div class="flex items-center justify-between mb-4">
      <p class="text-[14px] text-[#888]">학습 카테고리를 관리합니다.</p>
      <button
        @click="openCreateModal()"
        class="bg-[#4CAF50] text-white px-4 py-2 rounded-[12px] text-[13px] font-medium hover:bg-[#388E3C] transition-colors"
      >
        + 카테고리 추가
      </button>
    </div>

    <!-- 로딩 -->
    <div v-if="loading" class="flex justify-center items-center py-20">
      <div class="w-8 h-8 border-3 border-[#4CAF50] border-t-transparent rounded-full animate-spin"></div>
    </div>

    <!-- 카테고리 목록 -->
    <div v-else>
      <div v-if="categories.length === 0" class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] p-10 text-center">
        <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-[#F5F5F5] flex items-center justify-center">
          <svg class="w-6 h-6 text-[#BDBDBD]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" /></svg>
        </div>
        <p class="text-[14px] text-[#888]">등록된 카테고리가 없습니다.</p>
        <p class="text-[12px] text-[#AAA] mt-1">카테고리를 추가하여 학습 콘텐츠를 분류하세요.</p>
      </div>

      <div v-else class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] overflow-hidden">
        <table class="w-full">
          <thead>
            <tr class="bg-[#F8F8F8] border-b border-[#E8E8E8]">
              <th class="text-left px-5 py-3 text-[12px] font-semibold text-[#888] uppercase">아이콘</th>
              <th class="text-left px-5 py-3 text-[12px] font-semibold text-[#888] uppercase">카테고리명</th>
              <th class="text-left px-5 py-3 text-[12px] font-semibold text-[#888] uppercase">설명</th>
              <th class="text-right px-5 py-3 text-[12px] font-semibold text-[#888] uppercase">관리</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="cat in categories"
              :key="cat.category_id"
              class="border-b border-[#F5F5F5] hover:bg-[#FAFAFA] transition-colors"
            >
              <td class="px-5 py-4 text-[24px]">{{ cat.icon || '📁' }}</td>
              <td class="px-5 py-4 text-[14px] font-medium text-[#333]">{{ cat.name }}</td>
              <td class="px-5 py-4 text-[13px] text-[#888]">{{ cat.description || '-' }}</td>
              <td class="px-5 py-4 text-right">
                <button
                  @click="openEditModal(cat)"
                  class="text-[#4CAF50] hover:text-[#388E3C] text-[13px] font-medium mr-3 transition-colors"
                >
                  수정
                </button>
                <button
                  @click="handleDelete(cat)"
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
    <div
      v-if="showModal"
      class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
      @click.self="closeModal()"
    >
      <div class="bg-white rounded-[16px] p-6 w-full max-w-[440px]">
        <h2 class="text-[16px] font-bold text-[#333] mb-5">
          {{ editingId ? '카테고리 수정' : '카테고리 추가' }}
        </h2>

        <div v-if="modalError" class="bg-red-50 text-[#F44336] text-[13px] px-4 py-2.5 rounded-[10px] mb-4">
          {{ modalError }}
        </div>

        <!-- 이름 -->
        <div class="mb-4">
          <label class="block text-[13px] font-medium text-[#555] mb-1">카테고리명 <span class="text-[#F44336]">*</span></label>
          <input
            v-model="form.name"
            type="text"
            placeholder="예: 언어, 수리, 인지"
            class="w-full px-3 py-2.5 border border-[#E8E8E8] rounded-[12px] text-[14px] focus:outline-none focus:border-[#4CAF50] transition-colors"
          />
        </div>

        <!-- 아이콘 -->
        <div class="mb-4">
          <label class="block text-[13px] font-medium text-[#555] mb-1">아이콘 (이모지)</label>
          <div class="flex items-center gap-3">
            <input
              v-model="form.icon"
              type="text"
              placeholder="📝"
              maxlength="4"
              class="w-20 px-3 py-2.5 border border-[#E8E8E8] rounded-[12px] text-[20px] text-center focus:outline-none focus:border-[#4CAF50] transition-colors"
            />
            <div class="flex gap-2">
              <button
                v-for="emoji in quickEmojis"
                :key="emoji"
                type="button"
                @click="form.icon = emoji"
                class="w-9 h-9 flex items-center justify-center rounded-[8px] text-[18px] hover:bg-[#F5F5F5] transition-colors"
                :class="form.icon === emoji ? 'bg-[#E8F5E9] ring-1 ring-[#4CAF50]' : ''"
              >
                {{ emoji }}
              </button>
            </div>
          </div>
        </div>

        <!-- 설명 -->
        <div class="mb-6">
          <label class="block text-[13px] font-medium text-[#555] mb-1">설명</label>
          <textarea
            v-model="form.description"
            rows="2"
            placeholder="카테고리에 대한 간단한 설명"
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
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { adminApi } from '@shared/api/adminApi'
import type { LearningCategory } from '@shared/types'
import { useToastStore } from '@shared/stores/toastStore'

const toast = useToastStore()

const categories = ref<LearningCategory[]>([])
const loading = ref(false)
const showModal = ref(false)
const editingId = ref<number | null>(null)
const saving = ref(false)
const modalError = ref('')

const form = ref({
  name: '',
  icon: '',
  description: '',
})

const quickEmojis = ['📝', '🔢', '🧠', '🤝', '🏠', '🏃', '🎨', '🎵', '📚', '🔬']

async function fetchCategories() {
  loading.value = true
  try {
    const res = await adminApi.getCategories()
    if (res.data.success) categories.value = res.data.data
  } catch (e: any) {
    toast.error(e.response?.data?.message || '데이터를 불러오지 못했습니다.')
  } finally {
    loading.value = false
  }
}

function openCreateModal() {
  editingId.value = null
  form.value = { name: '', icon: '', description: '' }
  modalError.value = ''
  showModal.value = true
}

function openEditModal(cat: LearningCategory) {
  editingId.value = cat.category_id
  form.value = {
    name: cat.name,
    icon: cat.icon || '',
    description: cat.description || '',
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
    modalError.value = '카테고리명을 입력해주세요.'
    return
  }

  saving.value = true
  modalError.value = ''
  try {
    if (editingId.value) {
      await adminApi.updateCategory(editingId.value, form.value)
    } else {
      await adminApi.createCategory(form.value)
    }
    closeModal()
    await fetchCategories()
  } catch (e: any) {
    modalError.value = e.response?.data?.message || '저장에 실패했습니다.'
  } finally {
    saving.value = false
  }
}

async function handleDelete(cat: LearningCategory) {
  if (!confirm(`"${cat.name}" 카테고리를 삭제하시겠습니까?\n해당 카테고리에 속한 콘텐츠가 있으면 삭제할 수 없습니다.`)) return

  try {
    await adminApi.deleteCategory(cat.category_id)
    await fetchCategories()
  } catch (e: any) {
    toast.error(e.response?.data?.message || '삭제에 실패했습니다.')
  }
}

onMounted(fetchCategories)
</script>
