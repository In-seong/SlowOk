<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useNotificationStore } from '../../stores/notificationStore'
import type { Notification } from '@shared/types'

const router = useRouter()
const notificationStore = useNotificationStore()

const pageLoading = ref(true)

onMounted(async () => {
  try {
    await notificationStore.fetchNotifications()
  } finally {
    pageLoading.value = false
  }
})

const notifications = computed(() => notificationStore.notifications)

function getTypeBadge(notification: Notification) {
  // Infer type from notification title keywords
  const title = notification.title || ''
  if (title.includes('학습') || title.includes('콘텐츠')) {
    if (title.includes('완료')) return { label: '학습완료', bg: 'bg-[#E8F5E9]', text: 'text-[#4CAF50]', type: 'learning' }
    return { label: '새콘텐츠', bg: 'bg-[#E3F2FD]', text: 'text-[#2196F3]', type: 'content' }
  }
  if (title.includes('챌린지') || title.includes('달성')) return { label: '챌린지달성', bg: 'bg-[#FFF3E0]', text: 'text-[#FF9800]', type: 'challenge' }
  if (title.includes('보상') || title.includes('카드')) return { label: '보상획득', bg: 'bg-[#FFF8E1]', text: 'text-[#FF8F00]', type: 'reward' }
  if (title.includes('평가')) return { label: '평가결과', bg: 'bg-[#F3E5F5]', text: 'text-[#9C27B0]', type: 'assessment' }
  return { label: '알림', bg: 'bg-[#F5F5F5]', text: 'text-[#888]', type: 'default' }
}

function getTimeAgo(dateStr?: string): string {
  if (!dateStr) return ''
  const now = new Date()
  const date = new Date(dateStr)
  const diffMs = now.getTime() - date.getTime()
  const diffMin = Math.floor(diffMs / 60000)
  const diffHours = Math.floor(diffMs / 3600000)
  const diffDays = Math.floor(diffMs / 86400000)

  if (diffMin < 1) return '방금 전'
  if (diffMin < 60) return `${diffMin}분 전`
  if (diffHours < 24) return `${diffHours}시간 전`
  if (diffDays < 7) return `${diffDays}일 전`
  const month = date.getMonth() + 1
  const day = date.getDate()
  return `${month}월 ${day}일`
}

const hasUnread = computed(() => notificationStore.unreadCount > 0)

async function handleMarkAsRead(id: number) {
  await notificationStore.markAsRead(id)
}

async function handleMarkAllAsRead() {
  await notificationStore.markAllAsRead()
}
</script>

<template>
  <div class="min-h-screen bg-[#F5F5F5] max-w-[402px] md:max-w-[600px] mx-auto">
    <!-- Back Header -->
    <header class="bg-white sticky top-0 z-10">
      <div class="flex items-center justify-between px-[20px] py-[16px]">
        <div class="flex items-center gap-[12px]">
          <button
            @click="router.back()"
            class="w-[32px] h-[32px] flex items-center justify-center rounded-full hover:bg-[#F5F5F5] active:scale-[0.98] transition-all"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="w-[20px] h-[20px] text-[#333]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
          </button>
          <h1 class="text-[18px] font-bold text-[#333]">알림</h1>
        </div>
        <button
          v-if="hasUnread"
          @click="handleMarkAllAsRead"
          class="text-[13px] text-[#4CAF50] font-semibold hover:text-[#388E3C] active:scale-[0.98] transition-all px-[8px] py-[4px] rounded-[8px]"
        >
          모두 읽음
        </button>
      </div>
    </header>

    <!-- Content -->
    <main class="px-[20px] pb-[80px] pt-[16px]">
      <!-- Loading State -->
      <div v-if="pageLoading" class="flex flex-col items-center justify-center py-20">
        <div class="w-8 h-8 border-3 border-[#4CAF50] border-t-transparent rounded-full animate-spin"></div>
        <p class="text-[13px] text-[#888] mt-3">불러오는 중...</p>
      </div>

      <!-- Error State -->
      <div v-else-if="notificationStore.error" class="flex flex-col items-center justify-center py-20">
        <p class="text-[14px] text-[#F44336] font-medium">{{ notificationStore.error }}</p>
        <button @click="notificationStore.fetchNotifications()" class="mt-3 text-[13px] text-[#4CAF50] font-semibold">다시 시도</button>
      </div>

      <!-- Empty State -->
      <div v-else-if="notifications.length === 0" class="flex flex-col items-center justify-center py-[96px]">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-[48px] h-[48px] text-[#E0E0E0] mb-[16px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
        </svg>
        <p class="text-[14px] text-[#888] font-medium">알림이 없습니다</p>
        <p class="text-[12px] text-[#B0B0B0] mt-[4px]">새로운 소식이 도착하면 알려드릴게요</p>
      </div>

      <!-- Notification List -->
      <div v-else class="space-y-[12px]">
        <button
          v-for="notification in notifications"
          :key="notification.notification_id"
          @click="handleMarkAsRead(notification.notification_id)"
          class="w-full text-left transition-all active:scale-[0.98]"
          :class="
            notification.is_read
              ? 'bg-[#F8F8F8] rounded-[12px] p-[16px]'
              : 'bg-[#E8F5E9] border border-[#C8E6C9] rounded-[12px] p-[16px]'
          "
        >
          <div class="flex items-start gap-[12px]">
            <!-- Type Icon Circle -->
            <div
              class="w-[40px] h-[40px] rounded-full flex items-center justify-center shrink-0 mt-[2px]"
              :class="getTypeBadge(notification).bg"
            >
              <!-- Learning - Book -->
              <svg v-if="getTypeBadge(notification).type === 'learning'" xmlns="http://www.w3.org/2000/svg" class="w-[20px] h-[20px]" :class="getTypeBadge(notification).text" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
              </svg>
              <!-- Challenge - Trophy -->
              <svg v-else-if="getTypeBadge(notification).type === 'challenge'" xmlns="http://www.w3.org/2000/svg" class="w-[20px] h-[20px]" :class="getTypeBadge(notification).text" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 01-.982-3.172M9.497 14.25a7.454 7.454 0 00.981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 007.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M18.75 4.236c.982.143 1.954.317 2.916.52A6.003 6.003 0 0016.27 9.728M18.75 4.236V4.5c0 2.108-.966 3.99-2.48 5.228m0 0a6.003 6.003 0 01-5.54 0" />
              </svg>
              <!-- Reward - Star -->
              <svg v-else-if="getTypeBadge(notification).type === 'reward'" xmlns="http://www.w3.org/2000/svg" class="w-[20px] h-[20px]" :class="getTypeBadge(notification).text" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" />
              </svg>
              <!-- Content - Plus circle -->
              <svg v-else-if="getTypeBadge(notification).type === 'content'" xmlns="http://www.w3.org/2000/svg" class="w-[20px] h-[20px]" :class="getTypeBadge(notification).text" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <!-- Assessment - Chart -->
              <svg v-else xmlns="http://www.w3.org/2000/svg" class="w-[20px] h-[20px]" :class="getTypeBadge(notification).text" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
              </svg>
            </div>

            <!-- Content -->
            <div class="flex-1 min-w-0">
              <!-- Top: Badge + Time -->
              <div class="flex items-center justify-between gap-[8px] mb-[4px]">
                <span
                  :class="[getTypeBadge(notification).bg, getTypeBadge(notification).text]"
                  class="inline-block rounded-full px-[8px] py-[2px] text-[11px] font-semibold"
                >
                  {{ getTypeBadge(notification).label }}
                </span>
                <span class="text-[11px] text-[#999] shrink-0">{{ getTimeAgo(notification.created_at) }}</span>
              </div>
              <!-- Title -->
              <h3 class="text-[14px] font-semibold text-[#333] mb-[2px]">{{ notification.title }}</h3>
              <!-- Message -->
              <p class="text-[12px] text-[#888] leading-relaxed line-clamp-2">{{ notification.message }}</p>
            </div>
          </div>
        </button>
      </div>
    </main>
  </div>
</template>
