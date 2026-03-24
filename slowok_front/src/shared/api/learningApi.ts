import api from './index'
import type { ApiResponse, LearningContent, LearningProgress } from '@shared/types'

export const learningApi = {
  getContents(categoryId?: number) {
    const params = categoryId ? { category_id: categoryId } : {}
    return api.get<ApiResponse<LearningContent[]>>('/user/learning-contents', { params })
  },
  updateProgress(contentId: number, data: { status: string; score?: number }) {
    return api.post<ApiResponse<LearningProgress>>('/user/learning-progress', { content_id: contentId, ...data })
  },
}
