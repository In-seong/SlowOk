<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/authStore'
import { useChallengeStore } from '../stores/challengeStore'
import { useNotificationStore } from '../stores/notificationStore'
import AppHeader from '@shared/components/layout/AppHeader.vue'
import BottomNav from '@shared/components/layout/BottomNav.vue'
import LevelMapPath from '@user/components/home/LevelMapPath.vue'
import { useToastStore } from '@shared/stores/toastStore'

// Parent dashboard imports (lazy)
import CardSection from '@shared/components/ui/CardSection.vue'
import SectionTitle from '@shared/components/ui/SectionTitle.vue'
import StatusBadge from '@shared/components/ui/StatusBadge.vue'
import ProgressBar from '@shared/components/ui/ProgressBar.vue'
// ActionButton available if needed for parent dashboard

const router = useRouter()
const authStore = useAuthStore()
const challengeStore = useChallengeStore()
const notificationStore = useNotificationStore()
const toast = useToastStore()

const pageLoading = ref(true)

const currentProfileName = computed(() => {
  const p = authStore.activeProfile
  return p?.decrypted_name || p?.name || '사용자'
})
const hasMultipleProfiles = computed(() => authStore.profiles.length > 1)
const isLearner = computed(() => !authStore.isParent)

onMounted(async () => {
  try {
    // fetchUser 먼저 완료해야 isParent 판단 가능
    await authStore.fetchUser()

    const promises: Promise<any>[] = [
      challengeStore.fetchChallenges(),
      challengeStore.fetchRewardCards(),
      notificationStore.fetchNotifications(),
    ]
    // Parent needs learning data for dashboard
    if (authStore.isParent) {
      const { useLearningStore } = await import('../stores/learningStore')
      const learningStore = useLearningStore()
      promises.push(learningStore.fetchContents())
    }
    await Promise.all(promises)
  } catch (e: any) {
    toast.error(e.response?.data?.message || '데이터를 불러오지 못했습니다.')
  } finally {
    pageLoading.value = false
  }
})

// Notification banner
const unreadNotifications = computed(() => notificationStore.unreadCount)

// === LEARNER: Level Map ===
const totalChallenges = computed(() => challengeStore.challenges.length)
const completedChallenges = computed(() => challengeStore.completedCount)
const progressPercent = computed(() =>
  totalChallenges.value > 0 ? Math.round((completedChallenges.value / totalChallenges.value) * 100) : 0
)

function onPlayChallenge(challengeId: number) {
  router.push({ name: 'challenge-play', params: { id: challengeId } })
}

// === PARENT: Dashboard (기존 로직 유지) ===
const quickMenusParent = [
  { label: '자녀현황', routeName: 'parent-dashboard', bgColor: 'bg-[#2196F3]', iconPath: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z' },
  { label: '자녀추가', routeName: 'add-child', bgColor: 'bg-[#4CAF50]', iconPath: 'M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z' },
  { label: '프로필전환', routeName: 'profile-select', bgColor: 'bg-[#FF9800]', iconPath: 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4' },
  { label: '마이페이지', routeName: 'mypage', bgColor: 'bg-[#9C27B0]', iconPath: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' },
]
</script>

<template>
  <div class="min-h-screen flex justify-center bg-white">
    <div class="w-full max-w-[402px] min-h-screen relative bg-white">
      <!-- Header -->
      <AppHeader>
        <template #badge>
          <span
            v-if="!isLearner && unreadNotifications > 0"
            class="absolute -top-0.5 -right-0.5 w-[18px] h-[18px] bg-[#F44336] rounded-full flex items-center justify-center text-[10px] font-bold text-white"
          >
            {{ unreadNotifications }}
          </span>
        </template>
      </AppHeader>

      <!-- Main Content -->
      <main class="px-5 pb-[80px] pt-[80px] space-y-[14px] overflow-y-auto" style="height: calc(100vh - 60px);">
        <!-- Loading State -->
        <div v-if="pageLoading" class="flex flex-col items-center justify-center py-20">
          <div class="w-8 h-8 border-3 border-[#4CAF50] border-t-transparent rounded-full animate-spin"></div>
          <p class="text-[13px] text-[#888] mt-3">불러오는 중...</p>
        </div>

        <template v-else>
          <!-- 0. Profile Banner -->
          <div
            v-if="hasMultipleProfiles"
            class="flex items-center justify-between bg-[#F8F8F8] rounded-[12px] px-4 py-3"
          >
            <div class="flex items-center gap-2">
              <div class="w-7 h-7 rounded-full bg-[#4CAF50] flex items-center justify-center">
                <span class="text-[12px] font-bold text-white">{{ currentProfileName.charAt(0) }}</span>
              </div>
              <span class="text-[13px] font-semibold text-[#333]">{{ currentProfileName }}</span>
              <span
                class="text-[10px] px-2 py-0.5 rounded-full"
                :class="authStore.isParent ? 'bg-[#E3F2FD] text-[#1976D2]' : 'bg-[#E8F5E9] text-[#4CAF50]'"
              >
                {{ authStore.isParent ? '학부모' : '학습자' }}
              </span>
            </div>
            <button
              class="text-[12px] font-medium text-[#4CAF50]"
              @click="router.push({ name: 'profile-select' })"
            >
              전환
            </button>
          </div>

          <!-- 0.5. Institution Not Connected Banner -->
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

          <!-- 1. Notification Banner (학부모만) -->
          <button
            v-if="!isLearner && unreadNotifications > 0"
            class="w-full bg-[#E8F5E9] rounded-[12px] p-3.5 flex items-center gap-3"
            @click="router.push({ name: 'notifications' })"
          >
            <svg class="w-5 h-5 text-[#4CAF50] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9" />
              <path d="M13.73 21a2 2 0 01-3.46 0" />
            </svg>
            <span class="flex-1 text-[13px] text-[#4CAF50] font-medium text-left">새로운 알림 {{ unreadNotifications }}건</span>
            <svg class="w-4 h-4 text-[#4CAF50] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M9 18l6-6-6-6" />
            </svg>
          </button>

          <!-- ========== LEARNER: Level Map ========== -->
          <template v-if="isLearner">
            <!-- Progress Summary Bar -->
            <div class="bg-[#F0F7F0] rounded-[16px] p-4">
              <div class="flex items-center justify-between mb-2">
                <span class="text-[13px] font-semibold text-[#333]">학습 진행률</span>
                <span class="text-[13px] font-bold text-[#4CAF50]">{{ completedChallenges }}/{{ totalChallenges }}</span>
              </div>
              <div class="w-full h-[8px] rounded-full bg-[#E0E0E0] overflow-hidden">
                <div
                  class="h-full rounded-full bg-[#4CAF50] transition-all duration-500"
                  :style="{ width: progressPercent + '%' }"
                />
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

          <!-- ========== PARENT: Dashboard (기존 유지) ========== -->
          <template v-else>
            <!-- Quick Menu -->
            <CardSection class="!p-5" style="height: 102px;">
              <div class="flex items-center justify-between h-full">
                <button
                  v-for="menu in quickMenusParent"
                  :key="menu.routeName"
                  class="flex-1 flex flex-col items-center justify-center gap-2"
                  @click="router.push({ name: menu.routeName })"
                >
                  <div
                    class="w-10 h-10 rounded-full flex items-center justify-center"
                    :class="menu.bgColor"
                  >
                    <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path :d="menu.iconPath" />
                    </svg>
                  </div>
                  <span class="text-[11px] font-normal text-[#333]">{{ menu.label }}</span>
                </button>
              </div>
            </CardSection>

            <!-- Weekly Challenges (Parent view) -->
            <section class="flex flex-col gap-3">
              <SectionTitle title="자녀 챌린지 현황" />
              <CardSection>
                <div v-if="challengeStore.challenges.length === 0" class="py-6 text-center">
                  <p class="text-[13px] text-[#888]">등록된 챌린지가 없습니다</p>
                </div>
                <div
                  v-for="(challenge, index) in challengeStore.challenges.slice(0, 5)"
                  :key="challenge.challenge_id"
                  class="flex items-center gap-3 py-3"
                  :class="index < Math.min(challengeStore.challenges.length, 5) - 1 ? 'border-b border-[#F5F5F5]' : ''"
                >
                  <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-0.5">
                      <h3 class="text-[14px] font-semibold text-[#333] truncate">{{ challenge.title }}</h3>
                      <StatusBadge
                        v-if="challenge.latest_attempt?.is_passed"
                        label="통과"
                        variant="success"
                      />
                    </div>
                    <p class="text-[12px] text-[#888]">{{ challenge.category?.name ?? '기타' }}</p>
                  </div>
                  <ProgressBar
                    v-if="challenge.latest_attempt"
                    :value="challenge.latest_attempt.score"
                    variant="success"
                    class="w-16"
                  />
                </div>
              </CardSection>
            </section>
          </template>
        </template>
      </main>

      <!-- Bottom Navigation -->
      <BottomNav />
    </div>
  </div>
</template>
