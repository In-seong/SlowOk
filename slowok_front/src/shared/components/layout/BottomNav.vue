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
    class="fixed bottom-0 left-1/2 -translate-x-1/2 w-full max-w-[402px] bg-white border-t border-[#E8E8E8] flex items-center justify-around z-50 shadow-[0_-2px_10px_rgba(0,0,0,0.05)]"
    :style="{ paddingBottom: 'env(safe-area-inset-bottom)' }"
  >
    <button
      v-for="item in navItems"
      :key="item.label"
      class="flex flex-col items-center justify-center py-2 px-6 gap-0.5 relative"
      @click="navigateTo(item)"
    >
      <!-- 활성 인디케이터 -->
      <div
        v-if="isActive(item)"
        class="absolute -top-px left-1/2 -translate-x-1/2 w-8 h-[3px] rounded-full bg-[#4CAF50]"
      />

      <div
        class="flex items-center justify-center transition-transform duration-200"
        :class="isActive(item) ? 'scale-110' : 'scale-100'"
      >
        <!-- Home -->
        <template v-if="item.routeName === 'home'">
          <!-- Active: filled -->
          <svg v-if="isActive(item)" class="w-6 h-6" viewBox="0 0 24 24" fill="#4CAF50">
            <path d="M11.47 3.84a.75.75 0 011.06 0l8.69 8.69a.75.75 0 01-.53 1.28h-1.44v7.44a.75.75 0 01-.75.75h-3.75a.75.75 0 01-.75-.75V16.5h-3v4.75a.75.75 0 01-.75.75H6.5a.75.75 0 01-.75-.75v-7.44H4.31a.75.75 0 01-.53-1.28l8.69-8.69z" />
          </svg>
          <!-- Inactive: outline -->
          <svg v-else class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="#B0B0B0" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1h-2z" />
          </svg>
        </template>

        <!-- Screening -->
        <template v-else-if="item.routeName === 'screening-list'">
          <!-- Active: filled -->
          <svg v-if="isActive(item)" class="w-6 h-6" viewBox="0 0 24 24" fill="#4CAF50">
            <path fill-rule="evenodd" d="M7.502 6h7.128A3.375 3.375 0 0118 9.375v9.375a3 3 0 003-3V6.108c0-1.505-1.125-2.811-2.664-2.94A48.972 48.972 0 0012 3c-2.227 0-4.406.148-6.336.432A3.003 3.003 0 003 6.108v9.375a3 3 0 003 3V9.375C6 7.512 7.512 6 9.375 6h-1.873z" clip-rule="evenodd" />
            <path fill-rule="evenodd" d="M6 18.375a3.375 3.375 0 003.375 3.375h7.5a3.375 3.375 0 003.375-3.375V9.375A3.375 3.375 0 0016.875 6h-7.5A3.375 3.375 0 006 9.375v9zm4.0605-5.8395a.75.75 0 011.06 0L12.75 14.164l2.6295-2.624a.75.75 0 111.0605 1.0605l-3.1598 3.155a.75.75 0 01-1.06 0l-2.22-2.2145a.75.75 0 010-1.0605z" clip-rule="evenodd" />
          </svg>
          <!-- Inactive: outline -->
          <svg v-else class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="#B0B0B0" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
          </svg>
        </template>

        <!-- MyPage -->
        <template v-else-if="item.routeName === 'mypage'">
          <!-- Active: filled -->
          <svg v-if="isActive(item)" class="w-6 h-6" viewBox="0 0 24 24" fill="#4CAF50">
            <path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0zM3.751 20.105a8.25 8.25 0 0116.498 0 .75.75 0 01-.437.695A18.683 18.683 0 0112 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 01-.437-.695z" clip-rule="evenodd" />
          </svg>
          <!-- Inactive: outline -->
          <svg v-else class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="#B0B0B0" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
          </svg>
        </template>
      </div>

      <span
        class="text-[10px] font-semibold transition-colors duration-200"
        :class="isActive(item) ? 'text-[#4CAF50]' : 'text-[#B0B0B0]'"
      >
        {{ item.label }}
      </span>
    </button>
  </nav>
</template>
