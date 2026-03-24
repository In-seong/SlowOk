import api from './index'
import type { ApiResponse, Challenge, ChallengeAttempt, RewardCard } from '@shared/types'

export const challengeApi = {
  getChallenges() {
    return api.get<ApiResponse<Challenge[]>>('/user/challenges')
  },
  getChallenge(id: number) {
    return api.get<ApiResponse<Challenge>>(`/user/challenges/${id}`)
  },
  submitAttempt(challengeId: number, data: { score: number; is_passed: boolean }) {
    return api.post<ApiResponse<ChallengeAttempt>>(`/user/challenges/${challengeId}/attempt`, data)
  },
  getRewardCards() {
    return api.get<ApiResponse<RewardCard[]>>('/user/reward-cards')
  },
}
