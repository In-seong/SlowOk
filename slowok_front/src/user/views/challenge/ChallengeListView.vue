<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import type { Challenge } from '@shared/types'
import BackHeader from '@shared/components/layout/BackHeader.vue'
import BottomNav from '@shared/components/layout/BottomNav.vue'
import CardSection from '@shared/components/ui/CardSection.vue'
import StatusBadge from '@shared/components/ui/StatusBadge.vue'
// import SectionTitle from '@shared/components/ui/SectionTitle.vue' // [미사용] 보상카드 비활성화
import { useChallengeStore } from '../../stores/challengeStore'

const router = useRouter()
const challengeStore = useChallengeStore()

const activeCategory = ref('전체')

onMounted(async () => {
  await challengeStore.fetchChallenges()
})

/** Build unique category list from fetched challenges */
const categories = computed(() => {
  const names = challengeStore.challenges
    .map((c: Challenge) => c.category?.name)
    .filter((n): n is string => !!n)
  return ['전체', ...Array.from(new Set(names))]
})

/** Category-filtered challenges */
const filteredChallenges = computed(() =>
  activeCategory.value === '전체'
    ? challengeStore.challenges
    : challengeStore.challenges.filter((c: Challenge) => c.category?.name === activeCategory.value)
)

/** Today's challenge: first challenge or null */
const todayChallenge = computed(() => challengeStore.challenges[0] ?? null)

function getCategoryIconBg(category: string | undefined): string {
  switch (category) {
    case '퀴즈': return 'bg-[#FFF3E0]'
    case '단어': return 'bg-[#E3F2FD]'
    case '감정': return 'bg-[#FCE4EC]'
    case '패턴': return 'bg-[#F3E5F5]'
    case '사회': return 'bg-[#FFF8E1]'
    default: return 'bg-[#F0F0F0]'
  }
}

function getCategoryIconColor(category: string | undefined): string {
  switch (category) {
    case '퀴즈': return 'text-[#FF9800]'
    case '단어': return 'text-[#2196F3]'
    case '감정': return 'text-[#E91E63]'
    case '패턴': return 'text-[#9C27B0]'
    case '사회': return 'text-[#FFA000]'
    default: return 'text-[#888]'
  }
}

function getCategoryBadgeVariant(category: string | undefined): 'warning' | 'info' | 'danger' | 'primary' | 'default' {
  switch (category) {
    case '퀴즈': return 'warning'
    case '단어': return 'info'
    case '감정': return 'danger'
    case '패턴': return 'primary'
    case '사회': return 'warning'
    default: return 'default'
  }
}

// [미사용] 보상카드 비활성화
// function getRewardColor(rarity: string): string { ... }
</script>

<template>
  <div class="min-h-screen bg-[#F8F8F8] max-w-[402px] md:max-w-[600px] mx-auto">
    <BackHeader title="챌린지" />

    <main class="px-5 pb-[80px] pt-4">
      <!-- Loading state -->
      <div v-if="challengeStore.loading" class="flex flex-col items-center justify-center py-20">
        <div class="w-[40px] h-[40px] border-4 border-[#FF9800] border-t-transparent rounded-full animate-spin mb-4" />
        <p class="text-[13px] text-[#999]">챌린지를 불러오는 중...</p>
      </div>

      <!-- Error state -->
      <div v-else-if="challengeStore.error" class="text-center py-16">
        <div class="w-[56px] h-[56px] bg-[#FFEBEE] rounded-full flex items-center justify-center mx-auto mb-3">
          <svg class="w-[28px] h-[28px] text-[#F44336]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
          </svg>
        </div>
        <p class="text-[13px] text-[#F44336] mb-3">{{ challengeStore.error }}</p>
        <button
          @click="challengeStore.fetchChallenges()"
          class="text-[13px] text-[#FF9800] font-semibold"
        >
          다시 시도
        </button>
      </div>

      <!-- Main content -->
      <template v-else>
        <!-- Today's challenge banner -->
        <div
          v-if="todayChallenge"
          class="bg-gradient-to-r from-[#FF9800] to-[#FFB74D] rounded-[16px] p-5 text-white relative overflow-hidden mb-4"
        >
          <div class="relative z-10">
            <span class="bg-white/20 text-[11px] font-bold rounded-full px-3 py-1 inline-block mb-2">오늘의 챌린지</span>
            <h2 class="text-[17px] font-bold mb-1">{{ todayChallenge.title }}</h2>
            <p class="text-white/80 text-[13px] mb-4">{{ todayChallenge.category?.name ?? '챌린지' }}에 도전해보세요!</p>
            <button
              @click="router.push({ name: 'challenge-play', params: { id: todayChallenge.challenge_id } })"
              class="bg-white text-[#FF9800] font-semibold text-[13px] px-5 py-2.5 rounded-[12px] active:scale-[0.98] transition-all flex items-center gap-1.5"
            >
              도전하기
              <svg class="w-[14px] h-[14px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 5l7 7-7 7" />
              </svg>
            </button>
          </div>
          <!-- Trophy SVG -->
          <div class="absolute right-4 top-4">
            <svg class="w-[56px] h-[56px] text-white/15" viewBox="0 0 24 24" fill="currentColor">
              <path d="M5 3h14c.6 0 1 .4 1 1v2c0 3.3-2.2 6.1-5.2 6.9.6.5 1 1.3 1.2 2.1h2c.6 0 1 .4 1 1v2c0 .6-.4 1-1 1H6c-.6 0-1-.4-1-1v-2c0-.6.4-1 1-1h2c.2-.8.6-1.6 1.2-2.1C6.2 12.1 4 9.3 4 6V4c0-.6.4-1 1-1z" />
            </svg>
          </div>
          <!-- Decorative circles -->
          <div class="absolute -right-4 -top-4 w-[80px] h-[80px] bg-white/10 rounded-full" />
          <div class="absolute right-8 -bottom-4 w-[60px] h-[60px] bg-white/5 rounded-full" />
        </div>

        <!-- Category filter -->
        <div class="overflow-x-auto flex gap-2 pb-3 scrollbar-hide">
          <button
            v-for="cat in categories"
            :key="cat"
            @click="activeCategory = cat"
            :class="activeCategory === cat
              ? 'bg-[#4CAF50] text-white'
              : 'bg-[#F8F8F8] text-[#888]'"
            class="rounded-full px-4 py-2 text-[13px] font-medium whitespace-nowrap shrink-0 transition-colors active:scale-[0.98]"
          >
            {{ cat }}
          </button>
        </div>

        <!-- Challenge list -->
        <div class="space-y-3 mt-4">
          <CardSection
            v-for="challenge in filteredChallenges"
            :key="challenge.challenge_id"
          >
            <div
              class="cursor-pointer active:scale-[0.98] transition-transform"
              @click="router.push({ name: 'challenge-play', params: { id: challenge.challenge_id } })"
            >
              <div class="flex items-start gap-3">
                <!-- Category icon circle -->
                <div
                  :class="getCategoryIconBg(challenge.category?.name)"
                  class="w-[44px] h-[44px] rounded-full flex items-center justify-center shrink-0"
                >
                  <!-- Quiz -->
                  <svg v-if="challenge.category?.name === '퀴즈'" :class="getCategoryIconColor(challenge.category?.name)" class="w-[22px] h-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <!-- Word -->
                  <svg v-else-if="challenge.category?.name === '단어'" :class="getCategoryIconColor(challenge.category?.name)" class="w-[22px] h-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" />
                  </svg>
                  <!-- Emotion -->
                  <svg v-else-if="challenge.category?.name === '감정'" :class="getCategoryIconColor(challenge.category?.name)" class="w-[22px] h-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                  </svg>
                  <!-- Pattern -->
                  <svg v-else-if="challenge.category?.name === '패턴'" :class="getCategoryIconColor(challenge.category?.name)" class="w-[22px] h-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                  </svg>
                  <!-- Social (default) -->
                  <svg v-else :class="getCategoryIconColor(challenge.category?.name)" class="w-[22px] h-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                  </svg>
                </div>

                <!-- Info -->
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2 mb-1.5">
                    <StatusBadge :label="challenge.challenge_type" :variant="getCategoryBadgeVariant(challenge.category?.name)" />
                    <StatusBadge v-if="challenge.latest_attempt?.is_passed" label="통과" variant="success" />
                  </div>
                  <h3 class="text-[15px] font-semibold text-[#333]">{{ challenge.title }}</h3>
                  <p class="text-[13px] text-[#888] mt-0.5">{{ challenge.category?.name ?? '' }}</p>

                  <!-- Difficulty stars -->
                  <div class="flex items-center gap-2 mt-2">
                    <div class="flex items-center gap-0.5">
                      <template v-for="i in 3" :key="i">
                        <span class="text-[13px]" :class="i <= challenge.difficulty_level ? 'text-[#FFC107]' : 'text-[#E0E0E0]'">
                          {{ i <= challenge.difficulty_level ? '\u2605' : '\u2606' }}
                        </span>
                      </template>
                    </div>
                  </div>

                  <!-- Bottom: action button -->
                  <div class="flex items-center justify-end mt-3 pt-3 border-t border-[#F0F0F0]">
                    <button
                      class="rounded-[12px] px-3.5 py-2 text-[12px] font-semibold transition-all active:scale-[0.98]"
                      :class="challenge.latest_attempt?.is_passed
                        ? 'bg-[#F8F8F8] text-[#555]'
                        : 'bg-[#FF9800] text-white'"
                      @click.stop="router.push({ name: 'challenge-play', params: { id: challenge.challenge_id } })"
                    >
                      {{ challenge.latest_attempt?.is_passed ? '다시 도전' : '도전하기' }}
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </CardSection>
        </div>

        <!-- Empty state -->
        <div v-if="filteredChallenges.length === 0 && !challengeStore.loading" class="text-center py-16">
          <div class="w-[56px] h-[56px] bg-[#F0F0F0] rounded-full flex items-center justify-center mx-auto mb-3">
            <svg class="w-[28px] h-[28px] text-[#B0B0B0]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M6 9H4a2 2 0 01-2-2V5a2 2 0 012-2h2M18 9h2a2 2 0 002-2V5a2 2 0 00-2-2h-2M6 3h12v6a6 6 0 01-12 0V3zM12 15v3M8 21h8M10 18h4" />
            </svg>
          </div>
          <p class="text-[13px] text-[#999]">해당 카테고리에 챌린지가 없습니다</p>
        </div>

        <!-- [미사용] 보상카드 부여 로직 미구현 — 추후 완성 시 활성화 -->
      </template>
    </main>

    <BottomNav />
  </div>
</template>
