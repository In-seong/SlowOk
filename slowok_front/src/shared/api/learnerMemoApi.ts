import api from '@shared/api'
import type { ApiResponse, LearnerMemo } from '@shared/types'

export const learnerMemoApi = {
  list(profileId: number) {
    return api.get<ApiResponse<LearnerMemo[]>>(`/admin/users/${profileId}/memos`)
  },

  create(profileId: number, data: { category: string; content: string; is_pinned?: boolean }) {
    return api.post<ApiResponse<LearnerMemo>>(`/admin/users/${profileId}/memos`, data)
  },

  update(memoId: number, data: { category?: string; content?: string; is_pinned?: boolean }) {
    return api.put<ApiResponse<LearnerMemo>>(`/admin/memos/${memoId}`, data)
  },

  remove(memoId: number) {
    return api.delete<ApiResponse<null>>(`/admin/memos/${memoId}`)
  },

  togglePin(memoId: number) {
    return api.patch<ApiResponse<LearnerMemo>>(`/admin/memos/${memoId}/pin`)
  },
}
