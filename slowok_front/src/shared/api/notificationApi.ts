import api from './index'
import type { ApiResponse, Notification } from '@shared/types'

export const notificationApi = {
  getNotifications() {
    return api.get<ApiResponse<Notification[]>>('/user/notifications')
  },
  markAsRead(id: number) {
    return api.patch<ApiResponse<Notification>>(`/user/notifications/${id}/read`)
  },
  markAllAsRead() {
    return api.patch<ApiResponse<null>>('/user/notifications/read-all')
  },
}
