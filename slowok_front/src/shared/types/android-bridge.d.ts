/** Android WebView JavaScript Bridge */
interface AndroidBridge {
  sendFCMToken(): void
  sendDeviceType(): void
  getNotificationPermission(): void
  requestNotificationPermission(): void
  closeApp(): void
}

interface Window {
  AndroidBridge?: AndroidBridge
  webkit?: {
    messageHandlers?: {
      iOSBridge?: {
        postMessage(message: Record<string, unknown>): void
      }
    }
  }
  __handleFCMToken__?: (json: string) => void
  __handleType__?: (json: string) => void
  __handleNotificationPermission__?: (json: string) => void
}
