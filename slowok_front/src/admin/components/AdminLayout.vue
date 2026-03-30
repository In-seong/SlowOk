<script setup lang="ts">
import { ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAdminAuthStore } from '../stores/adminAuthStore'

const route = useRoute()
const router = useRouter()
const authStore = useAdminAuthStore()
const sidebarOpen = ref(false)

// 모바일에서 메뉴 클릭 시 사이드바 자동 닫기
watch(() => route.path, () => {
  sidebarOpen.value = false
})

interface MenuItem {
  label: string
  path: string
  name: string
  icon: string
  masterOnly?: boolean
  permissionKey?: string
}

interface MenuGroup {
  label: string
  key: string
  items: MenuItem[]
}

type SidebarEntry =
  | { type: 'item'; item: MenuItem }
  | { type: 'group'; group: MenuGroup }

const sidebarStructure: SidebarEntry[] = [
  { type: 'item', item:
    { label: '대시보드', path: '/', name: 'dashboard', permissionKey: 'dashboard_view',
      icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1' },
  },
  { type: 'group', group: {
    label: '콘텐츠',
    key: 'content',
    items: [
      { label: '카테고리 관리', path: '/categories', name: 'categories', permissionKey: 'category_manage',
        icon: 'M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z' },
      { label: '콘텐츠 관리', path: '/content', name: 'content', permissionKey: 'content_manage',
        icon: 'M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25' },
      { label: '챌린지 관리', path: '/challenges', name: 'challenges', permissionKey: 'challenge_manage',
        icon: 'M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 01-.982-3.172M9.497 14.25a7.454 7.454 0 00.981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 007.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M18.75 4.236c.982.143 1.954.317 2.916.52A6.003 6.003 0 0016.27 9.728M18.75 4.236V4.5c0 2.108-.966 3.99-2.48 5.228m0 0a6.023 6.023 0 01-2.77.672c-.99 0-1.932-.223-2.77-.672' },
      // [미사용] 보상카드 부여 로직 미구현
      // { label: '보상카드 관리', path: '/reward-cards', name: 'reward-cards', permissionKey: 'reward_manage',
      //   icon: 'M21 11.25v8.25a1.5 1.5 0 01-1.5 1.5H5.25a1.5 1.5 0 01-1.5-1.5v-8.25M12 4.875A2.625 2.625 0 109.375 7.5H12m0-2.625V7.5m0-2.625A2.625 2.625 0 1114.625 7.5H12m0 0V21m-8.625-9.75h18c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125h-18c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z' },
      // [미사용] 사용자 앱에서 패키지 기능 미사용
      // { label: '패키지 관리', path: '/content-packages', name: 'content-packages', permissionKey: 'package_manage',
      //   icon: 'M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z' },
      { label: 'AI 콘텐츠 생성', path: '/ai-content', name: 'ai-content', permissionKey: 'content_manage',
        icon: 'M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 00-2.455 2.456z' },
    ],
  }},
  { type: 'group', group: {
    label: '진단',
    key: 'screening',
    items: [
      { label: '진단 관리', path: '/screening', name: 'screening', permissionKey: 'screening_manage',
        icon: 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15a2.25 2.25 0 012.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z' },
      { label: '진단 결과', path: '/screening-results', name: 'screening-results', permissionKey: 'screening_result_view',
        icon: 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z' },
      { label: '추천 규칙', path: '/recommendation-rules', name: 'recommendation-rules', permissionKey: 'recommendation_manage',
        icon: 'M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 00-2.455 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z' },
    ],
  }},
  { type: 'group', group: {
    label: '운영',
    key: 'operation',
    items: [
      { label: '사용자 관리', path: '/users', name: 'users', permissionKey: 'user_manage',
        icon: 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z' },
      { label: '콘텐츠 할당', path: '/content-assignments', name: 'content-assignments', permissionKey: 'content_assign',
        icon: 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15a2.25 2.25 0 012.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z' },
      { label: '기관 관리', path: '/institutions', name: 'institutions', permissionKey: 'institution_manage',
        icon: 'M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z' },
      // [미사용] 사용자 앱에서 구독 기능 미사용
      // { label: '구독 관리', path: '/subscriptions', name: 'subscriptions', permissionKey: 'subscription_manage',
      //   icon: 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z' },
      { label: '학습 리포트', path: '/reports', name: 'reports', permissionKey: 'report_view',
        icon: 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z' },
      { label: '음성 녹음 관리', path: '/voice-recordings', name: 'voice-recordings', permissionKey: 'user_manage',
        icon: 'M12 18.75a6 6 0 006-6v-1.5m-6 7.5a6 6 0 01-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 01-3-3V4.5a3 3 0 116 0v8.25a3 3 0 01-3 3z' },
    ],
  }},
  { type: 'group', group: {
    label: '시스템',
    key: 'system',
    items: [
      { label: '플랜/기능 관리', path: '/plan-manage', name: 'plan-manage', masterOnly: true,
        icon: 'M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z M6 6h.008v.008H6V6z' },
      { label: '관리자 관리', path: '/admin-management', name: 'admin-management', masterOnly: true,
        icon: 'M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 010-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28z M15 12a3 3 0 11-6 0 3 3 0 016 0z' },
      { label: '사용 가이드', path: '/how-to-use', name: 'how-to-use',
        icon: 'M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z' },
    ],
  }},
]

// 그룹 열림/닫힘 상태
const expandedGroups = ref<Set<string>>(new Set(['content', 'screening', 'operation', 'system']))

function toggleGroup(key: string) {
  if (expandedGroups.value.has(key)) {
    expandedGroups.value.delete(key)
  } else {
    expandedGroups.value.add(key)
  }
  expandedGroups.value = new Set(expandedGroups.value)
}

function isItemVisible(item: MenuItem): boolean {
  if (item.masterOnly && !authStore.isMaster) return false
  if (item.permissionKey && !authStore.hasPermission(item.permissionKey)) return false
  return true
}

// 그룹 내에 표시할 항목이 하나라도 있는지
function isGroupVisible(group: MenuGroup): boolean {
  return group.items.some((item) => isItemVisible(item))
}

// 그룹 내에 현재 활성 경로가 있는지
function isGroupActive(group: MenuGroup): boolean {
  return group.items.some((item) => isItemVisible(item) && isActive(item))
}

// 현재 경로가 포함된 그룹 자동 펼침
watch(() => route.path, () => {
  for (const entry of sidebarStructure) {
    if (entry.type === 'group' && isGroupActive(entry.group)) {
      expandedGroups.value.add(entry.group.key)
      expandedGroups.value = new Set(expandedGroups.value)
    }
  }
}, { immediate: true })

function isActive(item: MenuItem): boolean {
  if (item.name === 'dashboard') return route.path === '/'
  return route.path === item.path || route.path.startsWith(item.path + '/')
}

const activeClasses = 'bg-[#E8F5E9] text-[#4CAF50] font-semibold'
const inactiveClasses = 'text-[#555] hover:bg-[#F5F5F5] hover:text-[#333]'

function handleInstitutionChange(e: Event) {
  const target = e.target as HTMLSelectElement
  const value = target.value
  authStore.selectInstitution(value ? Number(value) : null)
  // 페이지 새로고침하여 기관 변경 반영
  window.location.reload()
}

async function handleLogout() {
  await authStore.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="flex min-h-screen">
    <!-- Mobile top bar -->
    <header class="lg:hidden fixed top-0 left-0 right-0 h-14 bg-white border-b border-[#E8E8E8] flex items-center px-4 z-30">
      <button
        @click="sidebarOpen = true"
        class="w-9 h-9 flex items-center justify-center rounded-[8px] hover:bg-[#F5F5F5] transition-colors"
      >
        <svg class="w-5 h-5 text-[#555]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
        </svg>
      </button>
      <h1 class="text-[15px] font-bold text-[#4CAF50] ml-3">느려도 괜찮아</h1>
    </header>

    <!-- Overlay (mobile) -->
    <Transition name="fade">
      <div
        v-if="sidebarOpen"
        class="lg:hidden fixed inset-0 bg-black/40 z-30"
        @click="sidebarOpen = false"
      />
    </Transition>

    <!-- Sidebar -->
    <aside
      class="fixed top-0 left-0 w-60 h-screen bg-white border-r border-[#E8E8E8] flex flex-col z-40 transition-transform duration-200"
      :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
    >
      <!-- App title -->
      <div class="px-5 py-5 border-b border-[#E8E8E8] flex items-center justify-between">
        <div>
          <h1 class="text-lg font-bold text-[#4CAF50]">느려도 괜찮아</h1>
          <p class="text-[12px] text-[#888] mt-0.5">관리자</p>
        </div>
        <!-- Mobile close -->
        <button
          @click="sidebarOpen = false"
          class="lg:hidden w-8 h-8 flex items-center justify-center rounded-[8px] hover:bg-[#F5F5F5] transition-colors"
        >
          <svg class="w-5 h-5 text-[#888]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <!-- 기관 선택 (MASTER) / 기관 표시 (ADMIN) -->
      <div class="px-3 py-3 border-b border-[#E8E8E8]">
        <template v-if="authStore.isMaster">
          <label class="block text-[11px] font-semibold uppercase tracking-wider text-[#999] px-2 mb-1.5">기관 선택</label>
          <select
            :value="authStore.selectedInstitutionId ?? ''"
            @change="handleInstitutionChange"
            class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[8px] px-3 py-2 text-[13px] text-[#333] focus:border-[#4CAF50] focus:outline-none"
          >
            <option value="">전체 기관</option>
            <option
              v-for="inst in authStore.institutions"
              :key="inst.institution_id"
              :value="inst.institution_id"
            >
              {{ inst.name }}
            </option>
          </select>
        </template>
        <template v-else>
          <div class="flex items-center gap-2 px-2 py-1">
            <svg class="w-4 h-4 text-[#4CAF50] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z" />
            </svg>
            <span class="text-[13px] font-medium text-[#333]">{{ authStore.currentInstitutionName }}</span>
          </div>
        </template>
      </div>

      <!-- Navigation -->
      <nav class="flex-1 py-3 px-3 overflow-y-auto">
        <template v-for="entry in sidebarStructure" :key="entry.type === 'item' ? entry.item.name : entry.group.key">
          <!-- 단독 메뉴 -->
          <template v-if="entry.type === 'item' && isItemVisible(entry.item)">
            <router-link
              :to="entry.item.path"
              class="flex items-center gap-3 px-3 py-2.5 rounded-[10px] text-[14px] transition-colors mb-0.5"
              :class="isActive(entry.item) ? activeClasses : inactiveClasses"
            >
              <svg class="w-[18px] h-[18px] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" :d="entry.item.icon" />
              </svg>
              <span>{{ entry.item.label }}</span>
            </router-link>
          </template>

          <!-- 그룹 메뉴 -->
          <template v-if="entry.type === 'group' && isGroupVisible(entry.group)">
            <div class="mt-4 mb-1">
              <!-- 그룹 헤더 -->
              <button
                @click="toggleGroup(entry.group.key)"
                class="flex items-center justify-between w-full px-3 py-1.5 text-[11px] font-semibold uppercase tracking-wider text-[#999] hover:text-[#666] transition-colors"
              >
                <span>{{ entry.group.label }}</span>
                <svg
                  class="w-3.5 h-3.5 transition-transform duration-200"
                  :class="expandedGroups.has(entry.group.key) ? 'rotate-180' : ''"
                  fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                </svg>
              </button>

              <!-- 그룹 아이템 -->
              <div
                class="overflow-hidden transition-all duration-200"
                :class="expandedGroups.has(entry.group.key) ? 'max-h-[500px] opacity-100' : 'max-h-0 opacity-0'"
              >
                <template v-for="item in entry.group.items" :key="item.name">
                  <router-link
                    v-if="isItemVisible(item)"
                    :to="item.path"
                    class="flex items-center gap-3 px-3 py-2 rounded-[10px] text-[13px] transition-colors mb-0.5 ml-1"
                    :class="isActive(item) ? activeClasses : inactiveClasses"
                  >
                    <svg class="w-[16px] h-[16px] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                      <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                    </svg>
                    <span>{{ item.label }}</span>
                  </router-link>
                </template>
              </div>
            </div>
          </template>
        </template>
      </nav>

      <!-- Logout -->
      <div class="px-3 py-3 border-t border-[#E8E8E8]">
        <button
          @click="handleLogout"
          class="flex items-center gap-3 w-full px-3 py-2.5 rounded-[10px] text-[14px] text-[#888] hover:bg-red-50 hover:text-red-500 transition-colors"
        >
          <svg class="w-[18px] h-[18px] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
          </svg>
          <span>로그아웃</span>
        </button>
      </div>
    </aside>

    <!-- Main content -->
    <main class="flex-1 min-h-screen bg-[#F5F5F5] pt-14 lg:pt-0 lg:ml-60">
      <slot />
    </main>
  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
