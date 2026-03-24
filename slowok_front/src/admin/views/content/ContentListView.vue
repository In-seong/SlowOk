<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@shared/api'
import type { LearningContent, LearningCategory, ApiResponse } from '@shared/types'
import { useToastStore } from '@shared/stores/toastStore'

const router = useRouter()
const toast = useToastStore()

const contents = ref<LearningContent[]>([])
const categories = ref<LearningCategory[]>([])
const loading = ref(true)
const error = ref('')
const selectedCategory = ref<number | ''>('')

const filteredContents = computed(() => {
  if (selectedCategory.value === '') return contents.value
  return contents.value.filter((c) => c.category_id === selectedCategory.value)
})

async function fetchData() {
  loading.value = true
  error.value = ''
  try {
    const [contentsRes, categoriesRes] = await Promise.all([
      api.get<ApiResponse<LearningContent[]>>('/admin/learning-contents'),
      api.get<ApiResponse<LearningCategory[]>>('/admin/learning-categories'),
    ])
    if (contentsRes.data.success) contents.value = contentsRes.data.data
    if (categoriesRes.data.success) categories.value = categoriesRes.data.data
  } catch (e: any) {
    error.value = e.response?.data?.message || '데이터를 불러오지 못했습니다.'
  } finally {
    loading.value = false
  }
}

async function deleteContent(item: LearningContent) {
  if (!confirm(`"${item.title}" 콘텐츠를 정말 삭제하시겠습니까?\n이 작업은 되돌릴 수 없습니다.`)) return
  try {
    await api.delete(`/admin/learning-contents/${item.content_id}`)
    contents.value = contents.value.filter((c) => c.content_id !== item.content_id)
  } catch (e: any) {
    toast.error(e.response?.data?.message || '삭제에 실패했습니다.')
  }
}

function contentTypeLabel(type: string): string {
  const map: Record<string, string> = {
    VIDEO: '영상',
    QUIZ: '퀴즈',
    GAME: '게임',
    READING: '읽기',
  }
  return map[type] || type
}

function contentTypeColor(type: string): string {
  const map: Record<string, string> = {
    VIDEO: 'bg-blue-50 text-blue-600',
    QUIZ: 'bg-orange-50 text-orange-600',
    GAME: 'bg-purple-50 text-purple-600',
    READING: 'bg-green-50 text-green-600',
  }
  return map[type] || 'bg-gray-100 text-gray-600'
}

function difficultyStars(level: number): string {
  return '★'.repeat(level) + '☆'.repeat(5 - level)
}


onMounted(fetchData)
</script>

<template>
  <div class="p-6">
    <div class="max-w-[1200px] mx-auto">
      <!-- 상단 필터/버튼 -->
      <div class="flex items-center justify-between mb-4 gap-4">
        <select
          v-model="selectedCategory"
          class="bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] px-4 py-3 text-[15px] focus:border-[#4CAF50] focus:outline-none transition-colors"
        >
          <option value="">전체 카테고리</option>
          <option v-for="cat in categories" :key="cat.category_id" :value="cat.category_id">
            {{ cat.name }}
          </option>
        </select>
        <button
          @click="router.push({ name: 'content-create' })"
          class="bg-[#4CAF50] hover:bg-[#388E3C] text-white rounded-[12px] px-5 py-2.5 text-[14px] font-medium active:scale-[0.98] transition-all whitespace-nowrap"
        >
          + 콘텐츠 등록
        </button>
      </div>

      <!-- 로딩 -->
      <div v-if="loading" class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] p-8 text-center">
        <p class="text-[#888]">콘텐츠 목록을 불러오는 중...</p>
      </div>

      <!-- 에러 -->
      <div v-else-if="error" class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] p-8 text-center">
        <p class="text-red-500 mb-3">{{ error }}</p>
        <button
          @click="fetchData"
          class="bg-[#4CAF50] hover:bg-[#388E3C] text-white rounded-[12px] px-5 py-2.5 text-[14px] font-medium active:scale-[0.98] transition-all"
        >
          다시 시도
        </button>
      </div>

      <!-- 빈 상태 -->
      <div v-else-if="filteredContents.length === 0" class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] p-8 text-center">
        <p class="text-[#888]">
          {{ selectedCategory !== '' ? '해당 카테고리에 콘텐츠가 없습니다.' : '등록된 콘텐츠가 없습니다.' }}
        </p>
      </div>

      <!-- 테이블 -->
      <div v-else class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-[14px]">
            <thead>
              <tr class="border-b border-[#E8E8E8] bg-[#FAFAFA]">
                <th class="px-5 py-3 font-semibold text-[#555]">제목</th>
                <th class="px-5 py-3 font-semibold text-[#555]">카테고리</th>
                <th class="px-5 py-3 font-semibold text-[#555]">유형</th>
                <th class="px-5 py-3 font-semibold text-[#555]">난이도</th>
                <th class="px-5 py-3 font-semibold text-[#555]">액션</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="item in filteredContents"
                :key="item.content_id"
                class="border-b border-[#F0F0F0] hover:bg-[#FAFAFA] transition-colors"
              >
                <td class="px-5 py-3.5 text-[#333] font-medium">{{ item.title }}</td>
                <td class="px-5 py-3.5 text-[#555]">{{ item.category?.name || '-' }}</td>
                <td class="px-5 py-3.5">
                  <span
                    class="px-2 py-0.5 rounded-full text-[12px] font-medium"
                    :class="contentTypeColor(item.content_type)"
                  >
                    {{ contentTypeLabel(item.content_type) }}
                  </span>
                </td>
                <td class="px-5 py-3.5 text-[#888] text-[13px]">
                  {{ difficultyStars(item.difficulty_level) }}
                </td>
                <td class="px-5 py-3.5">
                  <div class="flex items-center gap-3">
                    <button
                      @click="router.push({ name: 'content-edit', params: { id: item.content_id } })"
                      class="text-[#4CAF50] hover:text-[#388E3C] text-[13px] font-medium transition-colors"
                    >
                      수정
                    </button>
                    <button
                      @click="deleteContent(item)"
                      class="text-red-500 hover:text-red-700 text-[13px] font-medium transition-colors"
                    >
                      삭제
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="px-5 py-3 border-t border-[#F0F0F0] text-[13px] text-[#888]">
          총 {{ filteredContents.length }}개
          <span v-if="selectedCategory !== ''"> (전체 {{ contents.length }}개 중)</span>
        </div>
      </div>
    </div>
  </div>
</template>
