import { defineStore } from 'pinia'
import { ref } from 'vue'
import { assessmentApi } from '@shared/api/assessmentApi'
import type { Assessment } from '@shared/types'

export const useAssessmentStore = defineStore('assessment', () => {
  const assessments = ref<Assessment[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function fetchAssessments() {
    loading.value = true
    error.value = null
    try {
      const res = await assessmentApi.getAssessments()
      if (res.data.success) assessments.value = res.data.data
    } catch (e: any) {
      error.value = e.response?.data?.message || '평가 결과를 불러올 수 없습니다.'
    } finally {
      loading.value = false
    }
  }

  return { assessments, loading, error, fetchAssessments }
})
