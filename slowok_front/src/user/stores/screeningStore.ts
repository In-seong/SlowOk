import { defineStore } from 'pinia'
import { ref } from 'vue'
import { screeningApi } from '@shared/api/screeningApi'
import type { ScreeningTest, ScreeningResult } from '@shared/types'

export const useScreeningStore = defineStore('screening', () => {
  const tests = ref<ScreeningTest[]>([])
  const results = ref<ScreeningResult[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function fetchTests() {
    loading.value = true
    error.value = null
    try {
      const res = await screeningApi.getTests()
      if (res.data.success) tests.value = res.data.data
    } catch (e: any) {
      error.value = e.response?.data?.message || '진단 검사 목록을 불러올 수 없습니다.'
    } finally {
      loading.value = false
    }
  }

  async function submitTest(testId: number, answers: Record<number, string>): Promise<ScreeningResult | null> {
    loading.value = true
    error.value = null
    try {
      const res = await screeningApi.submitTest(testId, answers)
      if (res.data.success) {
        results.value.unshift(res.data.data)
        return res.data.data
      }
      return null
    } catch (e: any) {
      error.value = e.response?.data?.message || '제출에 실패했습니다.'
      return null
    } finally {
      loading.value = false
    }
  }

  async function fetchResults() {
    loading.value = true
    error.value = null
    try {
      const res = await screeningApi.getResults()
      if (res.data.success) results.value = res.data.data
    } catch (e: any) {
      error.value = e.response?.data?.message || '결과를 불러올 수 없습니다.'
    } finally {
      loading.value = false
    }
  }

  return { tests, results, loading, error, fetchTests, submitTest, fetchResults }
})
