import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { notificationApi } from '@shared/api/notificationApi'
import type { Notification } from '@shared/types'

export const useNotificationStore = defineStore('notification', () => {
  const notifications = ref<Notification[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  const unreadCount = computed(() => notifications.value.filter(n => !n.is_read).length)

  async function fetchNotifications() {
    loading.value = true
    error.value = null
    try {
      const res = await notificationApi.getNotifications()
      if (res.data.success) notifications.value = res.data.data
    } catch (e: any) {
      error.value = e.response?.data?.message || '알림을 불러올 수 없습니다.'
    } finally {
      loading.value = false
    }
  }

  async function markAsRead(id: number) {
    try {
      const res = await notificationApi.markAsRead(id)
      if (res.data.success) {
        const idx = notifications.value.findIndex(n => n.notification_id === id)
        if (idx !== -1 && notifications.value[idx]) notifications.value[idx].is_read = true
      }
    } catch { /* ignore */ }
  }

  async function markAllAsRead() {
    try {
      await notificationApi.markAllAsRead()
      for (const n of notifications.value) {
        n.is_read = true
      }
    } catch { /* ignore */ }
  }

  return { notifications, loading, error, unreadCount, fetchNotifications, markAsRead, markAllAsRead }
})
