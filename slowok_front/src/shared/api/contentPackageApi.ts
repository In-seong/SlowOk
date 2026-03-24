import api from './index'
import type { ApiResponse, ContentPackage } from '@shared/types'

export const contentPackageApi = {
  getPackages() {
    return api.get<ApiResponse<ContentPackage[]>>('/admin/content-packages')
  },

  getPackage(id: number) {
    return api.get<ApiResponse<ContentPackage>>(`/admin/content-packages/${id}`)
  },

  createPackage(data: {
    name: string
    description?: string
    items: { assignable_type: string; assignable_id: number }[]
  }) {
    return api.post<ApiResponse<ContentPackage>>('/admin/content-packages', data)
  },

  updatePackage(id: number, data: {
    name: string
    description?: string
    items: { assignable_type: string; assignable_id: number }[]
  }) {
    return api.put<ApiResponse<ContentPackage>>(`/admin/content-packages/${id}`, data)
  },

  deletePackage(id: number) {
    return api.delete<ApiResponse<null>>(`/admin/content-packages/${id}`)
  },

  assignPackage(id: number, data: {
    profile_ids: number[]
    due_date?: string
    note?: string
  }) {
    return api.post<ApiResponse<null>>(`/admin/content-packages/${id}/assign`, data)
  },
}
