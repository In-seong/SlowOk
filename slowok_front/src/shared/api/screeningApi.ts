import api from './index'
import type { ApiResponse, ScreeningTest, ScreeningResult, ScreeningQuestion } from '@shared/types'

export const screeningApi = {
  getTests() {
    return api.get<ApiResponse<ScreeningTest[]>>('/user/screening-tests')
  },
  getQuestions(testId: number) {
    return api.get<ApiResponse<ScreeningQuestion[]>>(`/user/screening-tests/${testId}/questions`)
  },
  submitTest(testId: number, answers: Record<number, string>) {
    return api.post<ApiResponse<ScreeningResult>>(`/user/screening-tests/${testId}/submit`, { answers })
  },
  getResults() {
    return api.get<ApiResponse<ScreeningResult[]>>('/user/screening-results')
  },
}
