import api from './index'
import type { ApiResponse, RecommendationRule } from '@shared/types'

export const recommendationRuleApi = {
  getRules() {
    return api.get<ApiResponse<RecommendationRule[]>>('/admin/recommendation-rules')
  },

  createRule(data: {
    category_id: number
    score_min: number
    score_max: number
    package_id: number
  }) {
    return api.post<ApiResponse<RecommendationRule>>('/admin/recommendation-rules', data)
  },

  updateRule(id: number, data: {
    category_id: number
    score_min: number
    score_max: number
    package_id: number
  }) {
    return api.put<ApiResponse<RecommendationRule>>(`/admin/recommendation-rules/${id}`, data)
  },

  deleteRule(id: number) {
    return api.delete<ApiResponse<null>>(`/admin/recommendation-rules/${id}`)
  },
}
