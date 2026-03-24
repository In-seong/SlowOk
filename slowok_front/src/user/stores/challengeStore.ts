import { defineStore } from 'pinia'
import { ref } from 'vue'
import { challengeApi } from '@shared/api/challengeApi'
import type { Challenge, RewardCard } from '@shared/types'

export const useChallengeStore = defineStore('challenge', () => {
  const challenges = ref<Challenge[]>([])
  const currentChallenge = ref<Challenge | null>(null)
  const rewardCards = ref<RewardCard[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function fetchChallenges() {
    loading.value = true
    error.value = null
    try {
      const res = await challengeApi.getChallenges()
      if (res.data.success) challenges.value = res.data.data
    } catch (e: any) {
      error.value = e.response?.data?.message || '챌린지를 불러올 수 없습니다.'
    } finally {
      loading.value = false
    }
  }

  async function submitAttempt(challengeId: number, score: number, isPassed: boolean) {
    try {
      const res = await challengeApi.submitAttempt(challengeId, { score, is_passed: isPassed })
      return res.data.success ? res.data.data : null
    } catch {
      return null
    }
  }

  async function fetchChallenge(id: number) {
    loading.value = true
    error.value = null
    try {
      const res = await challengeApi.getChallenge(id)
      if (res.data.success) currentChallenge.value = res.data.data
    } catch (e: any) {
      error.value = e.response?.data?.message || '챌린지를 불러올 수 없습니다.'
    } finally {
      loading.value = false
    }
  }

  async function fetchRewardCards() {
    try {
      const res = await challengeApi.getRewardCards()
      if (res.data.success) rewardCards.value = res.data.data
    } catch { /* ignore */ }
  }

  return { challenges, currentChallenge, rewardCards, loading, error, fetchChallenges, fetchChallenge, submitAttempt, fetchRewardCards }
})
