<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()

interface Tab {
  name: string
  label: string
  routeName: string
  matchNames: string[]
}

const tabs: Tab[] = [
  { name: 'home', label: '홈', routeName: 'home', matchNames: ['home'] },
  { name: 'users', label: '학습자', routeName: 'users', matchNames: ['users', 'user-detail'] },
  { name: 'progress', label: '현황', routeName: 'progress', matchNames: ['progress', 'screening-results'] },
  { name: 'more', label: '더보기', routeName: 'more', matchNames: ['more'] },
]

const activeTab = computed(() => {
  const currentName = route.name as string
  for (const tab of tabs) {
    if (tab.matchNames.includes(currentName)) return tab.name
  }
  return 'more'
})

function navigate(tab: Tab) {
  if (route.name !== tab.routeName) {
    router.push({ name: tab.routeName })
  }
}
</script>

<template>
  <nav class="fixed bottom-0 left-0 right-0 z-50 bg-white border-t border-[#E8E8E8]" style="padding-bottom: env(safe-area-inset-bottom)">
    <div class="max-w-[600px] mx-auto flex h-[56px]">
      <button
        v-for="tab in tabs"
        :key="tab.name"
        class="flex-1 flex flex-col items-center justify-center gap-0.5 active:scale-95 transition-transform"
        :class="activeTab === tab.name ? 'text-[#4CAF50]' : 'text-[#C8C8C8]'"
        @click="navigate(tab)"
      >
        <!-- 홈 아이콘 -->
        <svg v-if="tab.name === 'home'" class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
          <polyline points="9 22 9 12 15 12 15 22" />
        </svg>
        <!-- 학습자 아이콘 -->
        <svg v-else-if="tab.name === 'users'" class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
          <circle cx="9" cy="7" r="4" />
          <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
          <path d="M16 3.13a4 4 0 0 1 0 7.75" />
        </svg>
        <!-- 현황 아이콘 -->
        <svg v-else-if="tab.name === 'progress'" class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="18" y1="20" x2="18" y2="10" />
          <line x1="12" y1="20" x2="12" y2="4" />
          <line x1="6" y1="20" x2="6" y2="14" />
        </svg>
        <!-- 더보기 아이콘 -->
        <svg v-else class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="1" />
          <circle cx="12" cy="5" r="1" />
          <circle cx="12" cy="19" r="1" />
        </svg>
        <span class="text-[10px] font-bold">{{ tab.label }}</span>
      </button>
    </div>
  </nav>
</template>
