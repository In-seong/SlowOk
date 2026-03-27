<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/authStore'
import { useChallengeStore } from '../stores/challengeStore'
import AppHeader from '@shared/components/layout/AppHeader.vue'
import BottomNav from '@shared/components/layout/BottomNav.vue'
import LevelMapPath from '@user/components/home/LevelMapPath.vue'
import { useToastStore } from '@shared/stores/toastStore'

const router = useRouter()
const authStore = useAuthStore()
const challengeStore = useChallengeStore()
const toast = useToastStore()

const pageLoading = ref(true)

onMounted(async () => {
  try {
    await authStore.fetchUser()
    await Promise.all([
      challengeStore.fetchChallenges(),
      challengeStore.fetchRewardCards(),
    ])
  } catch (e: any) {
    toast.error(e.response?.data?.message || '데이터를 불러오지 못했습니다.')
  } finally {
    pageLoading.value = false
  }
})

// === Level Map ===
const totalChallenges = computed(() => challengeStore.challenges.length)
const completedChallenges = computed(() => challengeStore.completedCount)
const progressPercent = computed(() =>
  totalChallenges.value > 0 ? Math.round((completedChallenges.value / totalChallenges.value) * 100) : 0
)

const profileName = computed(() => {
  const p = authStore.activeProfile
  return p?.decrypted_name || p?.name || '학습자'
})

function onPlayChallenge(challengeId: number) {
  router.push({ name: 'challenge-play', params: { id: challengeId } })
}
</script>

<template>
  <div class="min-h-screen flex justify-center bg-gradient-to-b from-[#F1F8E9] to-[#C8E6C9]">
    <div class="w-full max-w-[402px] min-h-screen relative">
      <!-- Header -->
      <AppHeader>
        <template #title>{{ profileName }}님, 반가워요!</template>
      </AppHeader>

      <!-- Progress Bar (sticky) -->
      <div v-if="!pageLoading" class="sticky top-[66px] z-30 bg-white/80 backdrop-blur-sm px-5 py-2.5">
        <div class="flex items-center justify-between mb-1">
          <span class="text-[12px] font-semibold text-[#333]">학습 진행률</span>
          <span class="text-[12px] font-bold text-[#4CAF50]">{{ completedChallenges }}/{{ totalChallenges }}</span>
        </div>
        <div class="w-full h-[6px] rounded-full bg-[#E0E0E0] overflow-hidden">
          <div
            class="h-full rounded-full bg-[#4CAF50] transition-all duration-500"
            :style="{ width: progressPercent + '%' }"
          />
        </div>
      </div>

      <!-- Main Content -->
      <main class="px-5 pb-[80px] pt-2 space-y-[14px] overflow-y-auto">
        <!-- Loading State -->
        <div v-if="pageLoading" class="flex flex-col items-center justify-center py-20">
          <div class="w-8 h-8 border-3 border-[#4CAF50] border-t-transparent rounded-full animate-spin"></div>
          <p class="text-[13px] text-[#888] mt-3">불러오는 중...</p>
        </div>

        <template v-else>
          <!-- Institution Not Connected Banner -->
          <div
            v-if="!authStore.hasInstitution"
            class="bg-[#FFF3E0] rounded-[12px] p-4"
          >
            <div class="flex items-start gap-3">
              <svg class="w-5 h-5 text-[#FF9800] shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                <line x1="12" y1="9" x2="12" y2="13" />
                <line x1="12" y1="17" x2="12.01" y2="17" />
              </svg>
              <div class="flex-1">
                <p class="text-[13px] font-semibold text-[#E65100] mb-1">기관 연결이 필요합니다</p>
                <p class="text-[12px] text-[#BF360C] leading-relaxed">학습 콘텐츠와 진단검사를 이용하려면 기관 초대코드를 입력하여 기관에 연결해주세요.</p>
                <button
                  class="mt-2.5 text-[12px] font-semibold text-[#FF9800] underline underline-offset-2"
                  @click="router.push({ name: 'mypage' })"
                >
                  마이페이지에서 초대코드 입력하기 →
                </button>
              </div>
            </div>
          </div>

          <!-- Level Map -->
          <div v-if="challengeStore.challenges.length > 0" class="pb-4">
            <LevelMapPath
              :challenges="challengeStore.challenges"
              @play="onPlayChallenge"
            />
          </div>
          <div v-else class="py-12 text-center">
            <div class="w-16 h-16 bg-[#E8F5E9] rounded-full flex items-center justify-center mx-auto mb-3">
              <svg class="w-8 h-8 text-[#4CAF50]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M6 9H4a2 2 0 01-2-2V5a2 2 0 012-2h2M18 9h2a2 2 0 002-2V5a2 2 0 00-2-2h-2M6 3h12v6a6 6 0 01-12 0V3zM12 15v3M8 21h8M10 18h4" />
              </svg>
            </div>
            <p class="text-[14px] font-semibold text-[#333] mb-1">아직 챌린지가 없어요</p>
            <p class="text-[12px] text-[#888]">기관에서 챌린지를 배정하면 여기에 표시됩니다</p>
          </div>
        </template>
      </main>

      <!-- Bottom Navigation -->
      <BottomNav />
    </div>
  </div>
</template>
