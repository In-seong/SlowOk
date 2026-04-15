<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { adminApi } from '@shared/api/adminApi'
import type { LearningCategory } from '@shared/types'
import { useToastStore } from '@shared/stores/toastStore'
import BottomSheet from '../../components/BottomSheet.vue'

const router = useRouter()
const toast = useToastStore()

const categories = ref<LearningCategory[]>([])
const loading = ref(true)

const showSheet = ref(false)
const editingId = ref<number | null>(null)
const saving = ref(false)
const sheetError = ref('')
const form = ref({ name: '', icon: '', description: '' })

const quickEmojis = ['📝', '🔢', '🧠', '🤝', '🏠', '🏃', '🎨', '🎵', '📚', '🔬']

async function fetchCategories() {
  loading.value = true
  try {
    const res = await adminApi.getCategories()
    if (res.data.success) categories.value = res.data.data
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    toast.error(err.response?.data?.message || '데이터를 불러오지 못했습니다.')
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editingId.value = null
  form.value = { name: '', icon: '', description: '' }
  sheetError.value = ''
  showSheet.value = true
}

function openEdit(cat: LearningCategory) {
  editingId.value = cat.category_id
  form.value = { name: cat.name, icon: cat.icon || '', description: cat.description || '' }
  sheetError.value = ''
  showSheet.value = true
}

async function handleSubmit() {
  if (!form.value.name.trim()) {
    sheetError.value = '카테고리명을 입력해주세요.'
    return
  }
  saving.value = true
  sheetError.value = ''
  try {
    if (editingId.value) {
      await adminApi.updateCategory(editingId.value, form.value)
    } else {
      await adminApi.createCategory(form.value)
    }
    showSheet.value = false
    await fetchCategories()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    sheetError.value = err.response?.data?.message || '저장에 실패했습니다.'
  } finally {
    saving.value = false
  }
}

async function handleDelete(cat: LearningCategory) {
  if (!confirm(`"${cat.name}" 카테고리를 삭제하시겠습니까?`)) return
  try {
    await adminApi.deleteCategory(cat.category_id)
    await fetchCategories()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    toast.error(err.response?.data?.message || '삭제에 실패했습니다.')
  }
}

onMounted(fetchCategories)
</script>

<template>
  <div class="min-h-screen bg-[#FAFAFA]">
    <header class="sticky top-0 z-40 bg-white border-b border-[#E8E8E8] h-[56px] flex items-center px-4">
      <button @click="router.back()" class="w-10 h-10 flex items-center justify-center">
        <svg class="w-5 h-5 text-[#333]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
      </button>
      <h1 class="flex-1 text-center text-[16px] font-bold text-[#333]">카테고리 관리</h1>
      <button @click="openCreate" class="w-10 h-10 flex items-center justify-center text-[#4CAF50]">
        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      </button>
    </header>

    <div v-if="loading" class="flex justify-center py-20">
      <div class="w-8 h-8 border-3 border-[#4CAF50] border-t-transparent rounded-full animate-spin" />
    </div>

    <div v-else class="px-4 py-4 space-y-3">
      <div v-if="categories.length === 0" class="bg-white rounded-[16px] shadow-sm border border-[#E8E8E8] p-8 text-center">
        <p class="text-[14px] text-[#888]">등록된 카테고리가 없습니다.</p>
      </div>

      <div
        v-for="cat in categories"
        :key="cat.category_id"
        class="bg-white rounded-[16px] shadow-sm border border-[#E8E8E8] p-4"
      >
        <div class="flex items-start gap-3">
          <span class="text-[24px]">{{ cat.icon || '📁' }}</span>
          <div class="flex-1 min-w-0">
            <p class="text-[15px] font-semibold text-[#333]">{{ cat.name }}</p>
            <p v-if="cat.description" class="text-[13px] text-[#888] mt-0.5">{{ cat.description }}</p>
          </div>
        </div>
        <div class="flex justify-end gap-3 mt-3">
          <button @click="openEdit(cat)" class="text-[13px] font-medium text-[#4CAF50] active:opacity-70">수정</button>
          <button @click="handleDelete(cat)" class="text-[13px] font-medium text-[#FF4444] active:opacity-70">삭제</button>
        </div>
      </div>
    </div>

    <BottomSheet v-model="showSheet" :title="editingId ? '카테고리 수정' : '카테고리 추가'">
      <div class="space-y-4 pb-4">
        <div v-if="sheetError" class="bg-red-50 text-[#FF4444] text-[13px] px-4 py-2.5 rounded-[10px]">{{ sheetError }}</div>

        <div>
          <label class="block text-[13px] font-medium text-[#555] mb-1">카테고리명 <span class="text-[#FF4444]">*</span></label>
          <input v-model="form.name" type="text" placeholder="예: 언어, 수리, 인지" class="w-full px-4 py-3.5 border border-[#E8E8E8] rounded-[12px] text-[15px] focus:outline-none focus:border-[#4CAF50]" />
        </div>

        <div>
          <label class="block text-[13px] font-medium text-[#555] mb-1">아이콘</label>
          <div class="flex items-center gap-3">
            <input v-model="form.icon" type="text" placeholder="📝" maxlength="4" class="w-16 px-3 py-3.5 border border-[#E8E8E8] rounded-[12px] text-[20px] text-center focus:outline-none focus:border-[#4CAF50]" />
            <div class="flex flex-wrap gap-2">
              <button
                v-for="emoji in quickEmojis"
                :key="emoji"
                type="button"
                @click="form.icon = emoji"
                class="w-10 h-10 flex items-center justify-center rounded-[8px] text-[18px]"
                :class="form.icon === emoji ? 'bg-[#E8F5E9] ring-1 ring-[#4CAF50]' : 'bg-[#F5F5F5]'"
              >{{ emoji }}</button>
            </div>
          </div>
        </div>

        <div>
          <label class="block text-[13px] font-medium text-[#555] mb-1">설명</label>
          <textarea v-model="form.description" rows="2" placeholder="카테고리에 대한 간단한 설명" class="w-full px-4 py-3.5 border border-[#E8E8E8] rounded-[12px] text-[15px] focus:outline-none focus:border-[#4CAF50] resize-none" />
        </div>

        <button
          @click="handleSubmit"
          :disabled="saving"
          class="w-full py-3.5 bg-[#4CAF50] text-white rounded-[12px] text-[15px] font-semibold disabled:opacity-50 active:bg-[#388E3C]"
        >{{ saving ? '저장 중...' : (editingId ? '수정' : '추가') }}</button>
      </div>
    </BottomSheet>
  </div>
</template>
