import { defineStore } from 'pinia'
import { ref } from 'vue'
import { reportApi } from '@shared/api/reportApi'
import type { LearningReport } from '@shared/types'

export const useReportStore = defineStore('report', () => {
  const reports = ref<LearningReport[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function fetchReports() {
    loading.value = true
    error.value = null
    try {
      const res = await reportApi.getReports()
      if (res.data.success) reports.value = res.data.data
    } catch (e: any) {
      error.value = e.response?.data?.message || '리포트를 불러올 수 없습니다.'
    } finally {
      loading.value = false
    }
  }

  return { reports, loading, error, fetchReports }
})
