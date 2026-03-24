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
  __handleFCMToken__?: (json: string) => void
  __handleType__?: (json: string) => void
  __handleNotificationPermission__?: (json: string) => void
}
