import api from './index'
import type { ApiResponse, LearningReport } from '@shared/types'

export const reportApi = {
  getReports() {
    return api.get<ApiResponse<LearningReport[]>>('/user/reports')
  },
}
