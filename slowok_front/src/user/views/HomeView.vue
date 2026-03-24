<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/authStore'
import { useLearningStore } from '../stores/learningStore'
import { useChallengeStore } from '../stores/challengeStore'
import { useNotificationStore } from '../stores/notificationStore'
import AppHeader from '@shared/components/layout/AppHeader.vue'
import BottomNav from '@shared/components/layout/BottomNav.vue'
import CardSection from '@shared/components/ui/CardSection.vue'
import SectionTitle from '@shared/components/ui/SectionTitle.vue'
import StatusBadge from '@shared/components/ui/StatusBadge.vue'
import ProgressBar from '@shared/components/ui/ProgressBar.vue'
import ActionButton from '@shared/components/ui/ActionButton.vue'
import { useToastStore } from '@shared/stores/toastStore'

const router = useRouter()
const authStore = useAuthStore()
const learningStore = useLearningStore()
const challengeStore = useChallengeStore()
const notificationStore = useNotificationStore()
const toast = useToastStore()

const pageLoading = ref(true)

const currentProfileName = computed(() => {
  const p = authStore.activeProfile
  return p?.decrypted_name || p?.name || '사용자'
})
const hasMultipleProfiles = computed(() => authStore.profiles.length > 1)

onMounted(async () => {
  try {
    await Promise.all([
      authStore.fetchUser(),
      learningStore.fetchContents(),
      challengeStore.fetchChallenges(),
      challengeStore.fetchRewardCards(),
      notificationStore.fetchNotifications(),
    ])
  } catch (e: any) {
    toast.error(e.response?.data?.message || '데이터를 불러오지 못했습니다.')
  } finally {
    pageLoading.value = false
  }
})

// Notification banner
const unreadNotifications = computed(() => notificationStore.unreadCount)

// Today's learning progress
const todayTotal = computed(() => learningStore.contents.length)
const todayCompleted = computed(() =>
  learningStore.contents.filter(c => c.progress?.status === 'COMPLETED').length
)
const todayProgress = computed(() =>
  todayTotal.value > 0 ? Math.round((todayCompleted.value / todayTotal.value) * 100) : 0
)

// Reward cards count
const rewardCardCount = computed(() => challengeStore.rewardCards.length)

// SVG circle math for progress ring
const circleRadius = 36
const circleCircumference = 2 * Math.PI * circleRadius
const progressOffset = computed(() => circleCircumference - (circleCircumference * todayProgress.value) / 100)

// Quick menu items
const quickMenus = computed(() => {
  if (authStore.isParent) {
    return [
      { label: '자녀현황', routeName: 'parent-dashboard', bgColor: 'bg-[#2196F3]', iconPath: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z' },
      { label: '자녀추가', routeName: 'add-child', bgColor: 'bg-[#4CAF50]', iconPath: 'M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z' },
      { label: '프로필전환', routeName: 'profile-select', bgColor: 'bg-[#FF9800]', iconPath: 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4' },
      { label: '마이페이지', routeName: 'mypage', bgColor: 'bg-[#9C27B0]', iconPath: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' },
    ]
  }
  return [
    { label: '진단검사', routeName: 'screening-list', bgColor: 'bg-[#2196F3]', iconPath: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4' },
    { label: '학습하기', routeName: 'learning-list', bgColor: 'bg-[#4CAF50]', iconPath: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253' },
    { label: '챌린지', routeName: 'challenge-list', bgColor: 'bg-[#FF9800]', iconPath: 'M6 9H4a2 2 0 01-2-2V5a2 2 0 012-2h2M18 9h2a2 2 0 002-2V5a2 2 0 00-2-2h-2M6 3h12v6a6 6 0 01-12 0V3zM12 15v3M8 21h8M10 18h4' },
    { label: '평가보기', routeName: 'assessment-list', bgColor: 'bg-[#9C27B0]', iconPath: 'M3 3v18h18M7 16l4-8 4 4 4-6' },
  ]
})

// In-progress learning items from store
const inProgressLearning = computed(() =>
  learningStore.contents
    .filter(c => c.progress?.status === 'IN_PROGRESS')
    .slice(0, 3)
    .map(c => ({
      id: c.content_id,
      category: c.category?.name || '기타',
      categoryVariant: getCategoryVariant(c.category?.name),
      title: c.title,
      progress: c.progress?.score ?? 0,
    }))
)

function getCategoryVariant(name?: string): 'info' | 'success' | 'warning' | 'danger' {
  switch (name) {
    case '언어': return 'info'
    case '인지': return 'success'
    case '사회성': return 'warning'
    case '정서': return 'danger'
    default: return 'info'
  }
}

// Weekly challenges from store
const weeklyChallenges = computed(() =>
  challengeStore.challenges.slice(0, 3).map((c, index) => {
    const iconStyles = [
      { iconBg: 'bg-[#E8F5E9]', iconColor: 'text-[#4CAF50]' },
      { iconBg: 'bg-[#FFF3E0]', iconColor: 'text-[#FF9800]' },
      { iconBg: 'bg-[#E3F2FD]', iconColor: 'text-[#2196F3]' },
    ]
    const style = iconStyles[index % iconStyles.length]!
    return {
      id: c.challenge_id,
      title: c.title,
      description: c.category?.name ? `${c.category.name} 영역 챌린지` : '챌린지에 도전해보세요',
      daysLeft: 7 - new Date().getDay() || 7,
      iconBg: style.iconBg,
      iconColor: style.iconColor,
    }
  })
)
</script>

<template>
  <div class="min-h-screen bg-[#F5F5F5] flex justify-center">
    <div class="w-full max-w-[402px] min-h-screen relative bg-[#F5F5F5]">
      <!-- Header -->
      <AppHeader>
        <template #badge>
          <span
            v-if="unreadNotifications > 0"
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
            class="flex items-center justify-between bg-white rounded-[12px] shadow-[0_0_6px_rgba(0,0,0,0.06)] px-4 py-3"
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
              class="text-[12px] text-[#4CAF50] font-medium"
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

          <!-- 1. Notification Banner -->
          <button
            v-if="unreadNotifications > 0"
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

          <!-- 2. Learning Status Cards (2-column) -->
          <div class="flex gap-[14px] w-full" style="height: 192px;">
            <!-- Left Card: Today's Learning -->
            <div
              class="flex-1 flex flex-col items-center justify-between bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] p-4 cursor-pointer"
              @click="router.push({ name: 'learning-list' })"
            >
              <h3 class="text-[15px] font-medium text-[#4CAF50] self-start">오늘의 학습</h3>
              <div class="relative w-[88px] h-[88px]">
                <svg class="w-[88px] h-[88px] -rotate-90" viewBox="0 0 100 100">
                  <circle cx="50" cy="50" :r="circleRadius" fill="none" stroke="#E8F5E9" stroke-width="8" />
                  <circle
                    cx="50" cy="50" :r="circleRadius"
                    fill="none"
                    stroke="#4CAF50"
                    stroke-width="8"
                    stroke-linecap="round"
                    :stroke-dasharray="circleCircumference"
                    :stroke-dashoffset="progressOffset"
                    class="transition-all duration-700"
                  />
                </svg>
                <div class="absolute inset-0 flex flex-col items-center justify-center">
                  <span class="text-[18px] font-bold text-[#333]">{{ todayProgress }}%</span>
                </div>
              </div>
              <p class="text-[13px] font-semibold text-[#555]">{{ todayCompleted }}/{{ todayTotal }} 완료</p>
            </div>

            <!-- Right Card: Reward Cards -->
            <div
              class="flex-1 flex flex-col justify-between bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] p-4 cursor-pointer"
              @click="router.push({ name: 'mypage' })"
            >
              <div>
                <h3 class="text-[15px] font-medium text-[#4CAF50] mb-3">보상 카드</h3>
                <p class="text-[13px] font-normal text-[#555] leading-[1.6]">모은 카드를<br />확인해보세요</p>
              </div>
              <div class="flex items-end justify-between">
                <span
                  class="text-[28px] font-bold text-[#4CAF50]"
                  style="text-decoration: underline; text-decoration-color: #4CAF50; text-underline-offset: 6px; text-decoration-thickness: 2px;"
                >
                  {{ rewardCardCount }}장
                </span>
                <svg class="w-2 h-3.5 text-[#4CAF50]" viewBox="0 0 8 14" fill="none">
                  <path d="M1 1L7 7L1 13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
              </div>
            </div>
          </div>

          <!-- 3. Quick Menu -->
          <CardSection class="!p-5" style="height: 102px;">
            <div class="flex items-center justify-between h-full">
              <button
                v-for="menu in quickMenus"
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

          <!-- 4. In-progress Learning -->
          <section class="flex flex-col gap-3">
            <SectionTitle title="진행 중인 학습" actionLabel="더보기" actionTo="/learning" />
            <CardSection>
              <div v-if="inProgressLearning.length === 0" class="py-6 text-center">
                <p class="text-[13px] text-[#888]">진행 중인 학습이 없습니다</p>
              </div>
              <div
                v-for="(item, index) in inProgressLearning"
                :key="item.id"
                class="py-3 cursor-pointer"
                :class="index < inProgressLearning.length - 1 ? 'border-b border-[#F5F5F5]' : ''"
                @click="router.push({ name: 'learning-content', params: { id: item.id } })"
              >
                <div class="flex items-center gap-2 mb-2">
                  <StatusBadge :label="item.category" :variant="item.categoryVariant" />
                  <span class="text-[14px] font-semibold text-[#333]">{{ item.title }}</span>
                </div>
                <div class="flex items-center gap-3">
                  <div class="flex-1">
                    <ProgressBar :value="item.progress" variant="success" />
                  </div>
                  <span class="text-[12px] text-[#888] shrink-0">{{ item.progress }}%</span>
                </div>
              </div>
            </CardSection>
          </section>

          <!-- 5. Weekly Challenges -->
          <section class="flex flex-col gap-3">
            <SectionTitle title="이번 주 챌린지" actionLabel="더보기" actionTo="/challenges" />
            <CardSection>
              <div v-if="weeklyChallenges.length === 0" class="py-6 text-center">
                <p class="text-[13px] text-[#888]">등록된 챌린지가 없습니다</p>
              </div>
              <div
                v-for="(challenge, index) in weeklyChallenges"
                :key="challenge.id"
                class="flex items-center gap-3 py-3"
                :class="index < weeklyChallenges.length - 1 ? 'border-b border-[#F5F5F5]' : ''"
              >
                <div
                  class="w-11 h-11 rounded-full flex items-center justify-center shrink-0"
                  :class="challenge.iconBg"
                >
                  <svg
                    v-if="index === 0"
                    class="w-5 h-5"
                    :class="challenge.iconColor"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  >
                    <path d="M12 18v-5.25m0 0a6.01 6.01 0 001.5-.189m-1.5.189a6.01 6.01 0 01-1.5-.189m3.75 7.478a12.06 12.06 0 01-4.5 0m3.75 2.383a14.406 14.406 0 01-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 10-7.517 0c.85.493 1.509 1.333 1.509 2.316V18" />
                  </svg>
                  <svg
                    v-else
                    class="w-5 h-5"
                    :class="challenge.iconColor"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  >
                    <path d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                  </svg>
                </div>
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2 mb-0.5">
                    <h3 class="text-[14px] font-semibold text-[#333] truncate">{{ challenge.title }}</h3>
                    <span class="text-[11px] text-[#999] shrink-0">D-{{ challenge.daysLeft }}</span>
                  </div>
                  <p class="text-[12px] text-[#888] leading-relaxed truncate">{{ challenge.description }}</p>
                </div>
                <ActionButton
                  variant="outline"
                  @click.stop="router.push({ name: 'challenge-play', params: { id: challenge.id } })"
                >
                  도전하기
                </ActionButton>
              </div>
            </CardSection>
          </section>
        </template>
      </main>

      <!-- Bottom Navigation -->
      <BottomNav />
    </div>
  </div>
</template>
