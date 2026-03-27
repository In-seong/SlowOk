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
    class="fixed bottom-0 left-1/2 -translate-x-1/2 w-full max-w-[402px] bg-white border-t border-[#E0E0E0] flex items-center justify-around z-50"
    :style="{ paddingBottom: 'env(safe-area-inset-bottom)' }"
  >
    <button
      v-for="item in navItems"
      :key="item.label"
      class="flex flex-col items-center justify-center py-2 flex-1 gap-0 active:scale-95 transition-transform"
      @click="navigateTo(item)"
    >
      <!-- 홈: 나무 아이콘 -->
      <template v-if="item.routeName === 'home'">
        <svg class="w-10 h-10" viewBox="0 0 32 32" fill="none">
          <!-- 나뭇잎 -->
          <ellipse cx="16" cy="10" rx="10" ry="9" :fill="isActive(item) ? '#4CAF50' : '#C8C8C8'" />
          <ellipse cx="16" cy="10" rx="7" ry="6" :fill="isActive(item) ? '#66BB6A' : '#D8D8D8'" />
          <!-- 줄기 -->
          <rect x="14.5" y="17" width="3" height="8" rx="1.5" :fill="isActive(item) ? '#795548' : '#BDBDBD'" />
          <!-- 작은 열매 -->
          <circle cx="12" cy="8" r="1.5" :fill="isActive(item) ? '#FF7043' : '#D0D0D0'" />
          <circle cx="19" cy="11" r="1.2" :fill="isActive(item) ? '#FFC107' : '#D0D0D0'" />
        </svg>
      </template>

      <!-- 진단: 돋보기+별 아이콘 -->
      <template v-else-if="item.routeName === 'screening-list'">
        <svg class="w-10 h-10" viewBox="0 0 32 32" fill="none">
          <!-- 돋보기 몸통 -->
          <circle cx="14" cy="14" r="8" :stroke="isActive(item) ? '#4CAF50' : '#C8C8C8'" stroke-width="2.5" fill="none" />
          <!-- 렌즈 안 반짝임 -->
          <circle cx="14" cy="14" r="5.5" :fill="isActive(item) ? '#E8F5E9' : '#F5F5F5'" />
          <!-- 체크마크 -->
          <path d="M11 14l2 2 4-4" :stroke="isActive(item) ? '#4CAF50' : '#C8C8C8'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
          <!-- 손잡이 -->
          <line x1="20" y1="20" x2="26" y2="26" :stroke="isActive(item) ? '#795548' : '#BDBDBD'" stroke-width="2.5" stroke-linecap="round" />
          <!-- 작은 별 -->
          <path d="M24 6l.8 1.6 1.8.3-1.3 1.2.3 1.8L24 9.8l-1.6.9.3-1.8L21.4 7.9l1.8-.3z" :fill="isActive(item) ? '#FFC107' : '#D8D8D8'" />
        </svg>
      </template>

      <!-- MY: 거북이 얼굴 -->
      <template v-else-if="item.routeName === 'mypage'">
        <svg class="w-10 h-10" viewBox="0 0 32 32" fill="none">
          <!-- 머리 -->
          <circle cx="16" cy="15" r="10" :fill="isActive(item) ? '#4CAF50' : '#C8C8C8'" />
          <circle cx="16" cy="15" r="7.5" :fill="isActive(item) ? '#66BB6A' : '#D8D8D8'" />
          <!-- 눈 -->
          <circle cx="12.5" cy="13" r="2" fill="white" />
          <circle cx="19.5" cy="13" r="2" fill="white" />
          <circle cx="13" cy="13.2" r="1" fill="#333" />
          <circle cx="20" cy="13.2" r="1" fill="#333" />
          <!-- 볼 터치 -->
          <ellipse cx="10.5" cy="17" rx="1.8" ry="1" :fill="isActive(item) ? '#FF8A80' : '#E0E0E0'" opacity="0.6" />
          <ellipse cx="21.5" cy="17" rx="1.8" ry="1" :fill="isActive(item) ? '#FF8A80' : '#E0E0E0'" opacity="0.6" />
          <!-- 입 -->
          <path d="M13.5 18.5q2.5 2 5 0" :stroke="isActive(item) ? '#2E7D32' : '#AAA'" stroke-width="1" stroke-linecap="round" fill="none" />
        </svg>
      </template>

      <span
        class="text-[10px] font-bold"
        :class="isActive(item) ? 'text-[#4CAF50]' : 'text-[#B0B0B0]'"
      >
        {{ item.label }}
      </span>
    </button>
  </nav>
</template>
