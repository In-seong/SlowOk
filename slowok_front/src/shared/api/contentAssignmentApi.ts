import api from './index'
import type { ApiResponse, ContentAssignment } from '@shared/types'

export const contentAssignmentApi = {
  getAssignments(params?: { profile_id?: number; assignable_type?: string }) {
    return api.get<ApiResponse<ContentAssignment[]>>('/admin/content-assignments', { params })
  },

  createAssignment(data: {
    profile_id: number
    assignable_type: 'screening_test' | 'learning_content' | 'challenge'
    assignable_id: number
    due_date?: string
    note?: string
  }) {
    return api.post<ApiResponse<ContentAssignment>>('/admin/content-assignments', data)
  },

  bulkAssign(data: {
    profile_ids: number[]
    assignments: { assignable_type: string; assignable_id: number }[]
    due_date?: string
    note?: string
  }) {
    return api.post<ApiResponse<null>>('/admin/content-assignments/bulk', data)
  },

  reorder(orders: { assignment_id: number; sort_order: number }[]) {
    return api.post<ApiResponse<null>>('/admin/content-assignments/reorder', { orders })
  },

  deleteAssignment(id: number) {
    return api.delete<ApiResponse<null>>(`/admin/content-assignments/${id}`)
  },
}
