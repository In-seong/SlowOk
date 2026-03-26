import api from './index'
import type { Account, AdminPermission, ApiResponse, Challenge, ChallengeQuestion, ScreeningQuestion, FileUploadResponse, LearningContent, LearningCategory, Institution, Subscription, RewardCard, LearningReport } from '@shared/types'

export const adminApi = {
  // 파일 업로드
  uploadFile(file: File, type: 'video' | 'image' | 'audio' | 'thumbnail') {
    const formData = new FormData()
    formData.append('file', file)
    formData.append('type', type)
    return api.post<ApiResponse<FileUploadResponse>>('/admin/upload', formData)
  },
  deleteFile(path: string) {
    return api.post<ApiResponse<null>>('/admin/upload/delete', { path })
  },

  // 학습 콘텐츠 관리
  getContents() {
    return api.get<ApiResponse<LearningContent[]>>('/admin/learning-contents')
  },
  getContent(id: number) {
    return api.get<ApiResponse<LearningContent>>(`/admin/learning-contents/${id}`)
  },
  createContent(data: Partial<LearningContent>) {
    return api.post<ApiResponse<LearningContent>>('/admin/learning-contents', data)
  },
  updateContent(id: number, data: Partial<LearningContent>) {
    return api.put<ApiResponse<LearningContent>>(`/admin/learning-contents/${id}`, data)
  },
  deleteContent(id: number) {
    return api.delete<ApiResponse<null>>(`/admin/learning-contents/${id}`)
  },

  // 학습 카테고리
  getCategories() {
    return api.get<ApiResponse<LearningCategory[]>>('/admin/learning-categories')
  },
  createCategory(data: Partial<LearningCategory>) {
    return api.post<ApiResponse<LearningCategory>>('/admin/learning-categories', data)
  },
  updateCategory(id: number, data: Partial<LearningCategory>) {
    return api.put<ApiResponse<LearningCategory>>(`/admin/learning-categories/${id}`, data)
  },
  deleteCategory(id: number) {
    return api.delete<ApiResponse<null>>(`/admin/learning-categories/${id}`)
  },

  // 챌린지 관리
  getChallenges() {
    return api.get<ApiResponse<Challenge[]>>('/admin/challenges')
  },
  getChallenge(id: number) {
    return api.get<ApiResponse<Challenge>>(`/admin/challenges/${id}`)
  },
  createChallenge(data: Partial<Challenge>) {
    return api.post<ApiResponse<Challenge>>('/admin/challenges', data)
  },
  updateChallenge(id: number, data: Partial<Challenge>) {
    return api.put<ApiResponse<Challenge>>(`/admin/challenges/${id}`, data)
  },
  deleteChallenge(id: number) {
    return api.delete<ApiResponse<null>>(`/admin/challenges/${id}`)
  },

  // 진단 문항 관리
  getScreeningQuestions(testId: number) {
    return api.get<ApiResponse<ScreeningQuestion[]>>(`/admin/screening-tests/${testId}/questions`)
  },
  createScreeningQuestion(data: Partial<ScreeningQuestion>) {
    return api.post<ApiResponse<ScreeningQuestion>>('/admin/screening-questions', data)
  },
  updateScreeningQuestion(id: number, data: Partial<ScreeningQuestion>) {
    return api.put<ApiResponse<ScreeningQuestion>>(`/admin/screening-questions/${id}`, data)
  },
  deleteScreeningQuestion(id: number) {
    return api.delete<ApiResponse<null>>(`/admin/screening-questions/${id}`)
  },

  // 챌린지 문항 관리
  getChallengeQuestions(challengeId: number) {
    return api.get<ApiResponse<ChallengeQuestion[]>>(`/admin/challenges/${challengeId}/questions`)
  },
  createQuestion(data: Partial<ChallengeQuestion>) {
    return api.post<ApiResponse<ChallengeQuestion>>('/admin/challenge-questions', data)
  },
  updateQuestion(id: number, data: Partial<ChallengeQuestion>) {
    return api.put<ApiResponse<ChallengeQuestion>>(`/admin/challenge-questions/${id}`, data)
  },
  deleteQuestion(id: number) {
    return api.delete<ApiResponse<null>>(`/admin/challenge-questions/${id}`)
  },

  // 기관 관리
  getInstitutions() {
    return api.get<ApiResponse<Institution[]>>('/admin/institutions')
  },
  createInstitution(data: Partial<Institution>) {
    return api.post<ApiResponse<Institution>>('/admin/institutions', data)
  },
  updateInstitution(id: number, data: Partial<Institution>) {
    return api.put<ApiResponse<Institution>>(`/admin/institutions/${id}`, data)
  },
  deleteInstitution(id: number) {
    return api.delete<ApiResponse<null>>(`/admin/institutions/${id}`)
  },

  // 구독 관리
  getSubscriptions() {
    return api.get<ApiResponse<Subscription[]>>('/admin/subscriptions')
  },
  createSubscription(data: Partial<Subscription>) {
    return api.post<ApiResponse<Subscription>>('/admin/subscriptions', data)
  },
  updateSubscription(id: number, data: Partial<Subscription>) {
    return api.put<ApiResponse<Subscription>>(`/admin/subscriptions/${id}`, data)
  },
  deleteSubscription(id: number) {
    return api.delete<ApiResponse<null>>(`/admin/subscriptions/${id}`)
  },

  // 학습 리포트 조회
  getReports() {
    return api.get<ApiResponse<LearningReport[]>>('/admin/reports')
  },

  // 보상카드 관리
  getRewardCards() {
    return api.get<ApiResponse<RewardCard[]>>('/admin/reward-cards')
  },
  createRewardCard(data: Partial<RewardCard>) {
    return api.post<ApiResponse<RewardCard>>('/admin/reward-cards', data)
  },
  updateRewardCard(id: number, data: Partial<RewardCard>) {
    return api.put<ApiResponse<RewardCard>>(`/admin/reward-cards/${id}`, data)
  },
  deleteRewardCard(id: number) {
    return api.delete<ApiResponse<null>>(`/admin/reward-cards/${id}`)
  },

  // 사용자(고객) 생성
  createUser(data: { username: string; password: string; name: string; user_type: 'LEARNER' | 'PARENT'; phone?: string; email?: string }) {
    return api.post<ApiResponse<Account>>('/admin/users', data)
  },

  // MASTER 전용 - 관리자 관리
  getAdmins() {
    return api.get<ApiResponse<Account[]>>('/admin/master/admins')
  },
  createAdmin(data: { username: string; password: string; role: 'ADMIN' | 'TEST'; name: string; phone?: string; email?: string; institution_id?: number | null }) {
    return api.post<ApiResponse<Account>>('/admin/master/admins', data)
  },
  updateAdmin(id: number, data: Partial<{ role: 'ADMIN' | 'TEST'; is_active: boolean; name: string; phone: string; email: string; password: string; institution_id: number | null }>) {
    return api.put<ApiResponse<Account>>(`/admin/master/admins/${id}`, data)
  },
  deleteAdmin(id: number) {
    return api.delete<ApiResponse<null>>(`/admin/master/admins/${id}`)
  },

  // AI 콘텐츠 생성
  getAiUsage() {
    return api.get<ApiResponse<{ today: { requests: number; success: number; tokens: number; limit: number }; month: { requests: number; success: number; tokens: number } }>>('/admin/ai-content/usage')
  },
  generateAiContent(prompt: string) {
    return api.post<ApiResponse<Record<string, unknown>>>('/admin/ai-content/generate', { prompt }, { timeout: 120000 })
  },
  saveAiContent(data: Record<string, unknown>) {
    return api.post<ApiResponse<{ package_id: number; package_name: string; counts: { learning_contents: number; challenges: number; screening_tests: number } }>>('/admin/ai-content/save', data)
  },

  // MASTER 전용 - 권한 관리
  getPermissions() {
    return api.get<ApiResponse<AdminPermission[]>>('/admin/master/permissions')
  },
  getAdminPermissions(id: number) {
    return api.get<ApiResponse<{ account_id: number; permission_ids: number[] }>>(`/admin/master/permissions/${id}`)
  },
  updateAdminPermissions(id: number, permissionIds: number[]) {
    return api.put<ApiResponse<null>>(`/admin/master/permissions/${id}`, { permission_ids: permissionIds })
  },
}
