<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useAdminAuthStore } from '@admin/stores/adminAuthStore'

const router = useRouter()
const authStore = useAdminAuthStore()

interface MenuItem {
  icon: string
  label: string
  routeName: string
  permissionKey?: string
  masterOnly?: boolean
  external?: string
}

interface MenuSection {
  title: string
  masterOnly?: boolean
  items: MenuItem[]
}

const sections: MenuSection[] = [
  {
    title: '콘텐츠',
    items: [
      { icon: '📁', label: '카테고리 관리', routeName: 'categories', permissionKey: 'category_manage' },
      { icon: '🏆', label: '챌린지 관리', routeName: 'challenges', permissionKey: 'challenge_manage' },
      { icon: '🤖', label: 'AI 콘텐츠 생성', routeName: 'ai-content', permissionKey: 'content_manage' },
    ],
  },
  {
    title: '진단',
    items: [
      { icon: '📋', label: '진단 관리', routeName: 'screening', permissionKey: 'screening_manage' },
      { icon: '📊', label: '진단 결과', routeName: 'screening-results', permissionKey: 'screening_result_view' },
    ],
  },
  {
    title: '운영',
    items: [
      { icon: '📝', label: '콘텐츠 할당', routeName: 'content-assignments', permissionKey: 'content_assign' },
      { icon: '🔔', label: '푸시 알림', routeName: 'push' },
      { icon: '📈', label: '대시보드', routeName: 'dashboard' },
    ],
  },
  {
    title: '시스템',
    masterOnly: true,
    items: [
      { icon: '🏢', label: '기관 관리', routeName: 'institutions', masterOnly: true },
      { icon: '👤', label: '관리자 관리', routeName: 'admin-management', masterOnly: true },
      { icon: '⚙️', label: '플랜/기능 관리', routeName: 'plan-manage', masterOnly: true },
    ],
  },
  {
    title: '기타',
    items: [
      { icon: '❓', label: '사용 가이드', routeName: 'how-to-use' },
      { icon: '🖥', label: '데스크톱으로 이동', routeName: '', external: 'https://slowokadmin.revuplan.com' },
    ],
  },
]

function isVisible(section: MenuSection): boolean {
  if (section.masterOnly && !authStore.isMaster) return false
  return section.items.some(item => isItemVisible(item))
}

function isItemVisible(item: MenuItem): boolean {
  if (item.masterOnly && !authStore.isMaster) return false
  if (item.permissionKey && !authStore.hasPermission(item.permissionKey)) return false
  return true
}

function navigateTo(item: MenuItem) {
  if (item.external) {
    window.open(item.external, '_blank')
    return
  }
  router.push({ name: item.routeName })
}

async function handleLogout() {
  await authStore.logout()
  router.replace({ name: 'login' })
}
</script>

<template>
  <div class="px-4 py-4 space-y-5">
    <template v-for="section in sections" :key="section.title">
      <div v-if="isVisible(section)">
        <p class="text-[13px] font-semibold text-[#888] mb-2 px-1">
          {{ section.title }}
          <span v-if="section.masterOnly" class="text-[11px] text-[#FF9800]">(MASTER)</span>
        </p>
        <div class="bg-white rounded-[16px] shadow-sm overflow-hidden divide-y divide-[#F0F0F0]">
          <button
            v-for="item in section.items.filter(isItemVisible)"
            :key="item.label"
            class="w-full flex items-center justify-between px-5 h-[52px] active:bg-[#FAFAFA] transition-colors"
            @click="navigateTo(item)"
          >
            <div class="flex items-center gap-3">
              <span class="text-[18px]">{{ item.icon }}</span>
              <span class="text-[15px] text-[#333]">{{ item.label }}</span>
            </div>
            <svg v-if="!item.external" class="w-4 h-4 text-[#C8C8C8]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M9 18l6-6-6-6" />
            </svg>
            <svg v-else class="w-4 h-4 text-[#C8C8C8]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" />
              <polyline points="15 3 21 3 21 9" />
              <line x1="10" y1="14" x2="21" y2="3" />
            </svg>
          </button>
        </div>
      </div>
    </template>

    <!-- 로그아웃 -->
    <div class="bg-white rounded-[16px] shadow-sm overflow-hidden">
      <button
        class="w-full flex items-center gap-3 px-5 h-[52px] active:bg-[#FAFAFA] transition-colors"
        @click="handleLogout"
      >
        <span class="text-[18px]">🚪</span>
        <span class="text-[15px] text-[#FF4444]">로그아웃</span>
      </button>
    </div>
  </div>
</template>
