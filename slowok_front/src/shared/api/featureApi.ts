import api from './index'
import type { ApiResponse, Feature, Plan, InstitutionPlanData, UserFeatureOverrideData } from '@shared/types'

// MASTER 전용 - 기능 관리
export const featureApi = {
  list() {
    return api.get<ApiResponse<Feature[]>>('/admin/master/features')
  },
  create(data: { feature_key: string; name: string; description?: string; category: string; sort_order?: number }) {
    return api.post<ApiResponse<Feature>>('/admin/master/features', data)
  },
  update(id: number, data: { name: string; description?: string; category: string; sort_order?: number }) {
    return api.put<ApiResponse<Feature>>(`/admin/master/features/${id}`, data)
  },
  destroy(id: number) {
    return api.delete<ApiResponse<null>>(`/admin/master/features/${id}`)
  },
}

// MASTER 전용 - 플랜 관리
export const planApi = {
  list() {
    return api.get<ApiResponse<Plan[]>>('/admin/master/plans')
  },
  create(data: { name: string; description?: string; price?: number; sort_order?: number; feature_ids: number[] }) {
    return api.post<ApiResponse<Plan>>('/admin/master/plans', data)
  },
  show(id: number) {
    return api.get<ApiResponse<Plan>>(`/admin/master/plans/${id}`)
  },
  update(id: number, data: { name: string; description?: string; price?: number; sort_order?: number; feature_ids: number[] }) {
    return api.put<ApiResponse<Plan>>(`/admin/master/plans/${id}`, data)
  },
  destroy(id: number) {
    return api.delete<ApiResponse<null>>(`/admin/master/plans/${id}`)
  },
}

// MASTER 전용 - 기관 플랜 배정
export const institutionPlanApi = {
  list() {
    return api.get<ApiResponse<InstitutionPlanData[]>>('/admin/master/institution-plans')
  },
  assign(data: { institution_id: number; plan_id: number; expires_at?: string }) {
    return api.post<ApiResponse<InstitutionPlanData>>('/admin/master/institution-plans', data)
  },
  remove(id: number) {
    return api.delete<ApiResponse<null>>(`/admin/master/institution-plans/${id}`)
  },
}

// ADMIN - 사용자 기능 override
export const userFeatureApi = {
  list(profileId: number) {
    return api.get<ApiResponse<{ overrides: UserFeatureOverrideData[]; institution_features: string[] }>>(`/admin/users/${profileId}/features`)
  },
  set(profileId: number, data: { feature_key: string; enabled: boolean }) {
    return api.post<ApiResponse<UserFeatureOverrideData>>(`/admin/users/${profileId}/features`, data)
  },
  remove(profileId: number, overrideId: number) {
    return api.delete<ApiResponse<null>>(`/admin/users/${profileId}/features/${overrideId}`)
  },
}

// USER - 내 기능 목록 조회
export const myFeaturesApi = {
  list() {
    return api.get<ApiResponse<string[]>>('/user/features')
  },
}
