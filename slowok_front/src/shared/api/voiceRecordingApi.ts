import api from './index'
import type { ApiResponse, VoiceRecording, RecordingFeedback } from '@shared/types'

export const voiceRecordingApi = {
  getRecordings(params?: { assignable_type?: string; assignable_id?: number }) {
    return api.get<ApiResponse<VoiceRecording[]>>('/user/voice-recordings', { params })
  },
  uploadRecording(data: FormData) {
    return api.post<ApiResponse<VoiceRecording>>('/user/voice-recordings', data)
  },
  deleteRecording(id: number) {
    return api.delete<ApiResponse<null>>(`/user/voice-recordings/${id}`)
  },
  // 자녀 녹음 조회 (학부모)
  getChildRecordings(childProfileId: number) {
    return api.get<ApiResponse<VoiceRecording[]>>(`/user/children/${childProfileId}/voice-recordings`)
  },
  // 피드백 작성 (학부모)
  addFeedback(recordingId: number, comment: string) {
    return api.post<ApiResponse<RecordingFeedback>>(`/user/voice-recordings/${recordingId}/feedback`, { comment })
  },
  // 피드백 삭제
  deleteFeedback(feedbackId: number) {
    return api.delete<ApiResponse<null>>(`/user/voice-recording-feedback/${feedbackId}`)
  },
}

// 관리자용 음성 녹음 API
export const adminVoiceRecordingApi = {
  list(params?: { profile_id?: number; assignable_type?: string }) {
    return api.get<ApiResponse<VoiceRecording[]>>('/admin/voice-recordings', { params })
  },
  getFeedbacks(recordingId: number) {
    return api.get<ApiResponse<RecordingFeedback[]>>(`/admin/voice-recordings/${recordingId}/feedbacks`)
  },
  addFeedback(recordingId: number, comment: string) {
    return api.post<ApiResponse<RecordingFeedback>>(`/admin/voice-recordings/${recordingId}/feedback`, { comment })
  },
  deleteFeedback(feedbackId: number) {
    return api.delete<ApiResponse<null>>(`/admin/voice-recordings/feedback/${feedbackId}`)
  },
}
