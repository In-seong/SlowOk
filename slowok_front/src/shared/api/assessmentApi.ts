import api from './index'
import type { ApiResponse, Assessment } from '@shared/types'

export const assessmentApi = {
  getAssessments() {
    return api.get<ApiResponse<Assessment[]>>('/user/assessments')
  },
}
