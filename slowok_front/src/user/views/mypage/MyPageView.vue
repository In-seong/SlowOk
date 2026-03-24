<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/authStore'
import { useLearningStore } from '../../stores/learningStore'
import { useChallengeStore } from '../../stores/challengeStore'
import { useNotificationStore } from '../../stores/notificationStore'
import { useToastStore } from '@shared/stores/toastStore'
import BottomNav from '@shared/components/layout/BottomNav.vue'
import StatusBadge from '@shared/components/ui/StatusBadge.vue'

const router = useRouter()
const authStore = useAuthStore()
const learningStore = useLearningStore()
const challengeStore = useChallengeStore()
const notificationStore = useNotificationStore()
const toast = useToastStore()

const pageLoading = ref(true)
const showDeleteConfirm = ref(false)
const deletePassword = ref('')
const deleteError = ref('')

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

const userName = computed(() => {
  const p = authStore.activeProfile
  return p?.decrypted_name || p?.name || authStore.user?.profile?.name || '사용자'
})
const userType = computed(() => authStore.activeProfile?.user_type || authStore.user?.profile?.user_type || 'LEARNER')
const userTypeLabel = computed(() => userType.value === 'PARENT' ? '학부모' : '학습자')
const userPhone = computed(() => authStore.activeProfile?.decrypted_phone || authStore.activeProfile?.phone || '')
const userEmail = computed(() => authStore.activeProfile?.decrypted_email || authStore.activeProfile?.email || '')
const userInitial = computed(() => userName.value.charAt(0))
const hasMultipleProfiles = computed(() => authStore.profiles.length > 1)

const unreadCount = computed(() => notificationStore.unreadCount)

const completedLearningCount = computed(() =>
  learningStore.contents.filter(c => c.progress?.status === 'COMPLETED').length
)

const challengePassedCount = computed(() =>
  challengeStore.challenges.filter(c => c.latest_attempt?.is_passed).length
)

const rewardCardCount = computed(() => challengeStore.rewardCards.length)

const stats = computed(() => [
  { label: '완료한 학습', value: completedLearningCount.value, color: 'text-[#4CAF50]' },
  { label: '챌린지 달성', value: challengePassedCount.value, color: 'text-[#FF9800]' },
  { label: '보상 카드', value: rewardCardCount.value, color: 'text-[#2196F3]' },
])

interface MenuItem {
  label: string
  routeName: string
  badge?: boolean
}

interface MenuSection {
  title: string
  items: MenuItem[]
}

const menuSections = computed<MenuSection[]>(() => {
  const sections: MenuSection[] = []

  // 프로필 전환 (2개 이상일 때)
  if (hasMultipleProfiles.value) {
    sections.push({
      title: '프로필',
      items: [
        { label: '프로필 전환', routeName: 'profile-select' },
      ],
    })
  }

  // 학부모 전용 메뉴
  if (authStore.isParent) {
    const childItems: MenuItem[] = [
      { label: '자녀 학습현황', routeName: 'parent-dashboard' },
      { label: '자녀 추가', routeName: 'add-child' },
    ]
    sections.push({
      title: '자녀 관리',
      items: childItems,
    })
  }

  sections.push(
    {
      title: '학습 관리',
      items: [
        { label: '학습 리포트', routeName: 'reports' },
        { label: '평가 결과', routeName: 'assessment-list' },
        { label: '알림', routeName: 'notifications', badge: true },
      ],
    },
    {
      title: '내 정보',
      items: [
        { label: '프로필 수정', routeName: 'edit-profile' },
        { label: '비밀번호 변경', routeName: 'change-password' },
        { label: '구독 관리', routeName: '' },
      ],
    },
    {
      title: '기타',
      items: [
        { label: '사용 가이드', routeName: 'how-to-use' },
      ],
    },
  )

  return sections
})

const inviteCodeInput = ref('')
const inviteCodeLoading = ref(false)
const inviteCodeSuccess = ref('')
const institutionName = computed(() => authStore.user?.institution?.name || '')

async function handleJoinInstitution(): Promise<void> {
  if (!inviteCodeInput.value.trim()) return
  inviteCodeLoading.value = true
  inviteCodeSuccess.value = ''
  authStore.error = null
  const success = await authStore.joinInstitution(inviteCodeInput.value.trim())
  if (success) {
    inviteCodeSuccess.value = `기관에 연결되었습니다!`
    inviteCodeInput.value = ''
  }
  inviteCodeLoading.value = false
}

function navigateTo(routeName: string): void {
  if (routeName) {
    router.push({ name: routeName })
  } else {
    toast.warning('준비 중입니다.')
  }
}

const childProfiles = computed(() =>
  authStore.profiles.filter(p => p.user_type === 'LEARNER' && p.parent_profile_id),
)

function navigateToEditChild(profileId: number): void {
  router.push({ name: 'edit-child-profile', params: { id: profileId } })
}

async function handleDeleteAccount(): Promise<void> {
  deleteError.value = ''
  if (!deletePassword.value.trim()) {
    deleteError.value = '비밀번호를 입력해주세요.'
    return
  }
  const success = await authStore.deleteAccount(deletePassword.value)
  if (success) {
    router.push({ name: 'login' })
  } else {
    deleteError.value = authStore.error || '회원 탈퇴에 실패했습니다.'
  }
}

async function handleLogout(): Promise<void> {
  await authStore.logout()
  localStorage.removeItem('activeProfileId')
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="min-h-screen bg-[#F5F5F5] flex justify-center">
    <div class="w-full max-w-[402px] min-h-screen relative bg-[#F5F5F5] flex flex-col">
      <!-- Main Content -->
      <main class="flex-1 px-5 pb-[80px] pt-5 space-y-4 overflow-y-auto">
        <!-- Loading State -->
        <div v-if="pageLoading" class="flex flex-col items-center justify-center py-20">
          <div class="w-8 h-8 border-3 border-[#4CAF50] border-t-transparent rounded-full animate-spin"></div>
          <p class="text-[13px] text-[#888] mt-3">불러오는 중...</p>
        </div>

        <template v-else>
          <!-- 1. Profile Section -->
          <div class="bg-[#E8F5E9] rounded-[16px] p-5">
            <div class="flex items-center gap-4">
              <div class="w-12 h-12 rounded-full bg-[#4CAF50] flex items-center justify-center shrink-0">
                <span class="text-[18px] font-bold text-white">{{ userInitial }}</span>
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-1">
                  <span class="text-[16px] font-bold text-[#333]">{{ userName }}</span>
                  <StatusBadge :label="userTypeLabel" variant="primary" />
                </div>
                <p v-if="userPhone" class="text-[13px] text-[#555]">{{ userPhone }}</p>
                <p v-if="userEmail" class="text-[12px] text-[#888] mt-0.5">{{ userEmail }}</p>
              </div>
            </div>
          </div>

          <!-- 1.5. Institution Connection -->
          <div v-if="!authStore.hasInstitution" class="bg-[#FFF3E0] rounded-[16px] p-5 space-y-3">
            <div class="flex items-center gap-2">
              <svg class="w-5 h-5 text-[#FF9800]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21" />
              </svg>
              <span class="text-[14px] font-bold text-[#E65100]">기관 연결</span>
            </div>
            <p class="text-[12px] text-[#BF360C] leading-relaxed">기관에서 발급한 초대코드를 입력하면 학습 콘텐츠, 진단검사 등을 이용할 수 있습니다.</p>

            <div v-if="inviteCodeSuccess" class="bg-[#E8F5E9] rounded-[8px] p-2.5 text-[12px] text-[#4CAF50] font-medium">
              {{ inviteCodeSuccess }}
            </div>
            <div v-if="authStore.error" class="bg-[#FFE5E5] rounded-[8px] p-2.5 text-[12px] text-[#FF4444]">
              {{ authStore.error }}
            </div>

            <div class="flex gap-2">
              <input
                v-model="inviteCodeInput"
                type="text"
                placeholder="초대코드 입력"
                maxlength="20"
                class="flex-1 bg-white border border-[#E8E8E8] rounded-[10px] px-3 py-2.5 text-[14px] text-[#333] outline-none focus:border-[#FF9800] transition-colors"
                @keyup.enter="handleJoinInstitution"
              />
              <button
                :disabled="!inviteCodeInput.trim() || inviteCodeLoading"
                class="bg-[#FF9800] text-white rounded-[10px] px-4 py-2.5 text-[13px] font-semibold hover:bg-[#F57C00] transition-colors disabled:opacity-50 whitespace-nowrap"
                @click="handleJoinInstitution"
              >
                {{ inviteCodeLoading ? '연결 중...' : '연결하기' }}
              </button>
            </div>
          </div>

          <div v-else class="bg-white rounded-[12px] shadow-[0_0_6px_rgba(0,0,0,0.06)] px-4 py-3 flex items-center gap-2">
            <svg class="w-4 h-4 text-[#4CAF50]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21" />
            </svg>
            <span class="text-[13px] text-[#555]">소속 기관:</span>
            <span class="text-[13px] font-semibold text-[#333]">{{ institutionName }}</span>
          </div>

          <!-- 2. Stats Grid (3-column) -->
          <div class="grid grid-cols-3 gap-3">
            <div
              v-for="stat in stats"
              :key="stat.label"
              class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] py-4 px-3 text-center"
            >
              <p class="text-[20px] font-bold" :class="stat.color">{{ stat.value }}</p>
              <p class="text-[11px] text-[#888] mt-1">{{ stat.label }}</p>
            </div>
          </div>

          <!-- 3. Menu Sections -->
          <div v-for="section in menuSections" :key="section.title" class="space-y-2">
            <h3 class="text-[12px] font-semibold text-[#888] uppercase tracking-wider px-1 pt-2">{{ section.title }}</h3>
            <div class="space-y-2">
              <button
                v-for="item in section.items"
                :key="item.label"
                class="w-full flex items-center justify-between bg-[#F8F8F8] rounded-[12px] px-4 py-3.5 active:bg-[#F0F0F0] transition-colors"
                @click="navigateTo(item.routeName)"
              >
                <span class="text-[14px] text-[#333]">{{ item.label }}</span>
                <div class="flex items-center gap-2">
                  <span
                    v-if="item.badge && unreadCount > 0"
                    class="min-w-[20px] h-5 rounded-full bg-[#F44336] text-white text-[10px] font-bold flex items-center justify-center px-1.5"
                  >
                    {{ unreadCount }}
                  </span>
                  <svg class="w-4 h-4 text-[#CCC]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 18l6-6-6-6" />
                  </svg>
                </div>
              </button>
            </div>
          </div>

          <!-- 4. Children Edit (PARENT only) -->
          <div v-if="authStore.isParent && childProfiles.length > 0" class="space-y-2">
            <h3 class="text-[12px] font-semibold text-[#888] uppercase tracking-wider px-1 pt-2">자녀 프로필 편집</h3>
            <div class="space-y-2">
              <button
                v-for="child in childProfiles"
                :key="child.profile_id"
                class="w-full flex items-center justify-between bg-[#F8F8F8] rounded-[12px] px-4 py-3.5 active:bg-[#F0F0F0] transition-colors"
                @click="navigateToEditChild(child.profile_id)"
              >
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 rounded-full bg-[#E3F2FD] flex items-center justify-center">
                    <span class="text-[13px] font-bold text-[#2196F3]">{{ (child.decrypted_name || child.name || '').charAt(0) }}</span>
                  </div>
                  <span class="text-[14px] text-[#333]">{{ child.decrypted_name || child.name }}</span>
                </div>
                <svg class="w-4 h-4 text-[#CCC]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7" /><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z" />
                </svg>
              </button>
            </div>
          </div>

          <!-- 5. Logout -->
          <button class="w-full mt-6 py-3 text-[14px] text-[#888] text-center" @click="handleLogout">
            로그아웃
          </button>

          <!-- 6. Delete Account -->
          <div class="mt-2 text-center">
            <button
              class="text-[12px] text-[#CCC] underline"
              @click="showDeleteConfirm = true"
            >
              회원 탈퇴
            </button>
          </div>

          <!-- Delete Confirm Modal -->
          <div v-if="showDeleteConfirm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-5">
            <div class="w-full max-w-[360px] bg-white rounded-[16px] p-6 space-y-4">
              <h3 class="text-[16px] font-bold text-[#333]">회원 탈퇴</h3>
              <p class="text-[13px] text-[#666] leading-relaxed">탈퇴 후 계정과 모든 데이터는 복구할 수 없습니다. 계속하시려면 비밀번호를 입력해주세요.</p>
              <div v-if="deleteError" class="bg-[#FFE5E5] rounded-[8px] p-2.5 text-[12px] text-[#FF4444]">{{ deleteError }}</div>
              <input
                v-model="deletePassword"
                type="password"
                placeholder="비밀번호 입력"
                class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] px-4 py-3 text-[14px] text-[#333] outline-none focus:border-[#FF4444] transition-colors"
              />
              <div class="flex gap-3">
                <button
                  class="flex-1 py-3 rounded-[12px] text-[14px] font-semibold bg-[#F0F0F0] text-[#666]"
                  @click="showDeleteConfirm = false; deletePassword = ''; deleteError = ''"
                >
                  취소
                </button>
                <button
                  class="flex-1 py-3 rounded-[12px] text-[14px] font-semibold bg-[#F44336] text-white disabled:opacity-50"
                  :disabled="authStore.loading"
                  @click="handleDeleteAccount"
                >
                  {{ authStore.loading ? '처리 중...' : '탈퇴하기' }}
                </button>
              </div>
            </div>
          </div>
        </template>
      </main>

      <!-- Bottom Navigation -->
      <BottomNav />
    </div>
  </div>
</template>
