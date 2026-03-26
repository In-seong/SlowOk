import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { challengeApi } from '@shared/api/challengeApi'
import type { Challenge, RewardCard } from '@shared/types'

export const useChallengeStore = defineStore('challenge', () => {
  const challenges = ref<Challenge[]>([])
  const currentChallenge = ref<Challenge | null>(null)
  const rewardCards = ref<RewardCard[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  const completedCount = computed(() =>
    challenges.value.filter(c => c.latest_attempt?.is_passed).length
  )

  const challengesByCategory = computed(() => {
    const map = new Map<string, Challenge[]>()
    for (const c of challenges.value) {
      const catName = c.category?.name ?? '기타'
      const list = map.get(catName)
      if (list) {
        list.push(c)
      } else {
        map.set(catName, [c])
      }
    }
    return map
  })

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

  return { challenges, currentChallenge, rewardCards, loading, error, completedCount, challengesByCategory, fetchChallenges, fetchChallenge, submitAttempt, fetchRewardCards }
})
