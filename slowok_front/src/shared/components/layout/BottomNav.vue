<script setup lang="ts">
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()

interface NavItem {
  label: string
  routeName: string
  matchNames: string[]
  emoji: string
  emojiInactive: string
}

const navItems: NavItem[] = [
  { label: '홈', routeName: 'home', matchNames: ['home'], emoji: '🏠', emojiInactive: '🏡' },
  { label: '진단', routeName: 'screening-list', matchNames: ['screening-list', 'screening-results', 'screening-test'], emoji: '📋', emojiInactive: '📝' },
  { label: 'MY', routeName: 'mypage', matchNames: ['mypage', 'notifications', 'reports'], emoji: '🐢', emojiInactive: '🐢' },
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
    class="fixed bottom-0 left-1/2 -translate-x-1/2 w-full max-w-[402px] bg-white/95 backdrop-blur-sm border-t border-[#E8E8E8] flex items-center justify-around z-50 shadow-[0_-2px_12px_rgba(0,0,0,0.06)]"
    :style="{ paddingBottom: 'env(safe-area-inset-bottom)' }"
  >
    <button
      v-for="item in navItems"
      :key="item.label"
      class="flex flex-col items-center justify-center py-2 flex-1 gap-0.5 relative active:scale-95 transition-transform"
      @click="navigateTo(item)"
    >
      <!-- 활성 배경 알약 -->
      <div
        v-if="isActive(item)"
        class="absolute top-1 left-1/2 -translate-x-1/2 w-14 h-9 rounded-full bg-[#E8F5E9]"
      />

      <!-- 이모지 아이콘 -->
      <span
        class="relative text-[24px] leading-none transition-transform duration-200"
        :class="isActive(item) ? 'scale-115 -translate-y-0.5' : 'scale-100 grayscale-[0.4] opacity-60'"
      >
        {{ isActive(item) ? item.emoji : item.emojiInactive }}
      </span>

      <span
        class="relative text-[10px] font-bold transition-colors duration-200"
        :class="isActive(item) ? 'text-[#2E7D32]' : 'text-[#B0B0B0]'"
      >
        {{ item.label }}
      </span>
    </button>
  </nav>
</template>
