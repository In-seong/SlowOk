import api from './index'
import type { ApiResponse } from '@shared/types'

export const deviceTokenApi = {
  register(fcmToken: string, deviceType: 'android' | 'ios' | 'web') {
    return api.post<ApiResponse<null>>('/user/device-token', {
      fcm_token: fcmToken,
      device_type: deviceType,
    })
  },
  remove(fcmToken: string) {
    return api.post<ApiResponse<null>>('/user/device-token/remove', {
      fcm_token: fcmToken,
    })
  },
}
