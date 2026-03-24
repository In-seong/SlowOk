import { deviceTokenApi } from '@shared/api/deviceTokenApi'

/** 안드로이드 웹뷰 환경인지 확인 */
export function isAndroidWebView(): boolean {
  return typeof window.AndroidBridge !== 'undefined'
}

/**
 * FCM 토큰 브릿지 초기화
 * - AndroidBridge에서 토큰을 받아 서버에 등록
 * - 로그인 후 호출
 */
export function initFcmBridge(): void {
  if (!isAndroidWebView()) return

  // 앱에서 토큰을 전달받는 콜백 등록
  window.__handleFCMToken__ = async (json: string) => {
    try {
      const parsed: { fcm_token?: string } = JSON.parse(json)
      const token = parsed.fcm_token
      if (!token) return

      // 서버에 토큰 등록
      await deviceTokenApi.register(token, 'android')

      // 로컬에 저장 (로그아웃 시 삭제용)
      localStorage.setItem('fcmToken', token)
    } catch {
      // FCM 토큰 등록 실패는 무시 (앱 사용에 영향 없음)
    }
  }

  // 앱에 토큰 요청
  window.AndroidBridge?.sendFCMToken()
}

/**
 * FCM 토큰 서버에서 삭제 (로그아웃 시 호출)
 */
export async function removeFcmToken(): Promise<void> {
  const token = localStorage.getItem('fcmToken')
  if (!token) return

  try {
    await deviceTokenApi.remove(token)
  } catch {
    // 삭제 실패는 무시
  } finally {
    localStorage.removeItem('fcmToken')
  }
}
