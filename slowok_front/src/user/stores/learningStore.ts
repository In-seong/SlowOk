import { defineStore } from 'pinia'
import { ref } from 'vue'
import { learningApi } from '@shared/api/learningApi'
import type { LearningContent } from '@shared/types'

export const useLearningStore = defineStore('learning', () => {
  const contents = ref<LearningContent[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function fetchContents(categoryId?: number) {
    loading.value = true
    error.value = null
    try {
      const res = await learningApi.getContents(categoryId)
      if (res.data.success) contents.value = res.data.data
    } catch (e: any) {
      error.value = e.response?.data?.message || '학습 콘텐츠를 불러올 수 없습니다.'
    } finally {
      loading.value = false
    }
  }

  async function updateProgress(contentId: number, status: string, score?: number) {
    try {
      const res = await learningApi.updateProgress(contentId, { status, score })
      if (res.data.success) {
        const idx = contents.value.findIndex(c => c.content_id === contentId)
        if (idx !== -1 && contents.value[idx]) contents.value[idx].progress = res.data.data
      }
      return res.data.success
    } catch {
      return false
    }
  }

  return { contents, loading, error, fetchContents, updateProgress }
})
