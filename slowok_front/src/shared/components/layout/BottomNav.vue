<script setup lang="ts">
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()

interface NavItem {
  label: string
  routeName: string
  matchNames: string[]
}

const navItems: NavItem[] = [
  { label: '홈', routeName: 'home', matchNames: ['home'] },
  { label: '진단', routeName: 'screening-list', matchNames: ['screening-list', 'screening-results', 'screening-test'] },
  { label: '학습', routeName: 'learning-list', matchNames: ['learning-list', 'learning-content'] },
  { label: '챌린지', routeName: 'challenge-list', matchNames: ['challenge-list', 'challenge-play'] },
  { label: 'MY', routeName: 'mypage', matchNames: ['mypage', 'notifications', 'reports'] },
]

function isActive(item: NavItem): boolean {
  return item.matchNames.includes(route.name as string)
}

function navigateTo(item: NavItem): void {
  router.push({ name: item.routeName })
}
</script>

<template>
  <nav
    class="fixed bottom-0 left-1/2 -translate-x-1/2 w-full max-w-[402px] h-[60px] bg-white border-t border-[#E0E0E0] flex items-center justify-between z-50"
    :style="{ paddingBottom: 'env(safe-area-inset-bottom)' }"
  >
    <button
      v-for="item in navItems"
      :key="item.label"
      class="flex flex-col items-center justify-center flex-1 gap-0.5 pt-1"
      @click="navigateTo(item)"
    >
      <div class="w-6 h-6 flex items-center justify-center">
        <!-- Home -->
        <svg v-if="item.routeName === 'home'" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" :stroke="isActive(item) ? '#4CAF50' : '#B0B0B0'" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1h-2z" />
        </svg>
        <!-- Screening -->
        <svg v-else-if="item.routeName === 'screening-list'" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" :stroke="isActive(item) ? '#4CAF50' : '#B0B0B0'" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
        </svg>
        <!-- Learning -->
        <svg v-else-if="item.routeName === 'learning-list'" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" :stroke="isActive(item) ? '#4CAF50' : '#B0B0B0'" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
        </svg>
        <!-- Challenge -->
        <svg v-else-if="item.routeName === 'challenge-list'" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" :stroke="isActive(item) ? '#4CAF50' : '#B0B0B0'" d="M6 9H4a2 2 0 01-2-2V5a2 2 0 012-2h2M18 9h2a2 2 0 002-2V5a2 2 0 00-2-2h-2M6 3h12v6a6 6 0 01-12 0V3zM12 15v3M8 21h8M10 18h4" />
        </svg>
        <!-- MyPage -->
        <svg v-else-if="item.routeName === 'mypage'" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" :stroke="isActive(item) ? '#4CAF50' : '#B0B0B0'" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
        </svg>
      </div>
      <span
        class="text-[10px] font-medium"
        :class="isActive(item) ? 'text-[#4CAF50]' : 'text-[#B0B0B0]'"
      >
        {{ item.label }}
      </span>
    </button>
  </nav>
</template>
