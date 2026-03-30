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
  console.log('[FCM] initFcmBridge called, isAndroid:', isAndroidWebView(), 'isIOS:', isIOSWebView())

  // 앱에서 토큰을 전달받는 콜백 등록 (Android/iOS 공통)
  window.__handleFCMToken__ = async (json: string) => {
    try {
      console.log('[FCM] __handleFCMToken__ received:', json)
      // iOS는 토큰 문자열 직접 전달, Android는 JSON
      let token: string | undefined
      try {
        const parsed = JSON.parse(json)
        token = parsed.fcm_token ?? parsed
      } catch {
        token = json // iOS: 토큰 문자열 직접
      }
      if (!token || typeof token !== 'string') return

      const deviceType = isAndroidWebView() ? 'android' : isIOSWebView() ? 'ios' : 'web'
      await deviceTokenApi.register(token, deviceType)
      localStorage.setItem('fcmToken', token)
      console.log('[FCM] Token registered:', token.substring(0, 20) + '...')
    } catch (e) {
      console.error('[FCM] Token registration failed:', e)
    }
  }

  // 앱에 토큰 요청
  if (isAndroidWebView()) {
    window.AndroidBridge?.sendFCMToken()
  } else if (isIOSWebView()) {
    window.webkit?.messageHandlers?.iOSBridge?.postMessage({ action: 'sendFCMToken' })
  }
}

/** iOS 웹뷰 환경인지 확인 */
export function isIOSWebView(): boolean {
  return !!window.webkit?.messageHandlers?.iOSBridge
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
