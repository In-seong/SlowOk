//
//  MainView.swift
//  SlowOK
//
//  Created by scoop dev on 3/1/26.
//

import SwiftUI
import WebKit
import Network
import UserNotifications

struct MainView: View {
    @State var webView: WKWebView?
    @State var webViewError: Bool = false
    @State var shouldReload: Bool = false
    @State var isWebViewLoaded: Bool = false
    @State var networkState: NWPath.Status = .unsatisfied
    @State var showNetworkAlert: Bool = false

    #if USER
    let WebURL = "https://slowokuser.revuplan.com"
    #elseif ADMIN
    let WebURL = "https://slowokadmin.revuplan.com"
    #else
    let WebURL = "https://slowokuser.revuplan.com"
    #endif

    var body: some View {
        ZStack {
            Color.white.ignoresSafeArea()

            WebViewContainer(
                url: URL(string: WebURL),
                webView: $webView,
                isError: $webViewError,
                shouldReload: $shouldReload,
                onMessage: { action, body in
                    handleBridgeMessage(action: action, body: body)
                },
                onLoadComplete: {
                    print("[WebView] 로드 완료")
                    isWebViewLoaded = true
                    executeJavaScriptSequentially()
                }
            )
            .onChange(of: networkState) { _ in
                if networkState != .satisfied {
                    showNetworkAlert = true
                } else {
                    showNetworkAlert = false
                }
            }
            .onReceive(NotificationCenter.default.publisher(for: Notification.Name("FCMTokenReceived"))) { notification in
                if let token = notification.userInfo?["token"] as? String {
                    if isWebViewLoaded {
                        sendFCMTokenToWebView(token: token)
                    }
                }
            }

            // 웹뷰 로드 에러 오버레이
            if webViewError {
                VStack(spacing: 16) {
                    Image(systemName: "exclamationmark.triangle")
                        .resizable()
                        .scaledToFit()
                        .frame(width: 50)
                        .foregroundColor(.orange)
                    Text("페이지를 불러올 수 없습니다")
                        .font(.system(size: 16, weight: .semibold))
                    Text(WebURL)
                        .font(.system(size: 12))
                        .foregroundColor(.gray)
                    Button("다시 시도") {
                        webViewError = false
                        shouldReload = true
                    }
                    .padding(.horizontal, 24)
                    .padding(.vertical, 10)
                    .background(Color(red: 0.3, green: 0.69, blue: 0.31))
                    .foregroundColor(.white)
                    .cornerRadius(10)
                }
                .padding(24)
                .background(Color.white)
                .cornerRadius(16)
                .shadow(radius: 10)
                .padding(.horizontal, 40)
            }

            // 네트워크 오류 오버레이
            if showNetworkAlert {
                networkAlertOverlay
            }
        }
        .onAppear {
            startNetworkMonitoring()
        }
    }
}

// MARK: - Network Alert UI
extension MainView {
    var networkAlertOverlay: some View {
        ZStack {
            Color.black.opacity(0.5)
                .ignoresSafeArea()

            VStack(spacing: 16) {
                Image(systemName: "wifi.slash")
                    .resizable()
                    .scaledToFit()
                    .frame(width: 50)
                    .foregroundColor(.gray)

                Text("네트워크에 접속할 수 없습니다.\n네트워크 연결 상태를 확인해주세요.")
                    .font(.system(size: 14))
                    .multilineTextAlignment(.center)
                    .lineSpacing(6)
                    .foregroundColor(.black)
            }
            .padding(24)
            .background(Color.white)
            .cornerRadius(12)
            .padding(.horizontal, 40)
        }
    }
}

// MARK: - JS Bridge Message Handler
extension MainView {
    private func handleBridgeMessage(action: String, body: [String: Any]) {
        switch action {
        case "closeApp":
            UIApplication.shared.perform(#selector(NSXPCConnection.suspend))
            DispatchQueue.main.asyncAfter(deadline: .now() + 0.5) {
                exit(0)
            }

        case "sendFCMToken":
            if let savedToken = UserDefaults.standard.string(forKey: "fcmToken") {
                sendFCMTokenToWebView(token: savedToken)
            }

        case "sendDeviceType":
            sendTypeToWebView()

        case "getNotificationPermission":
            let callbackName = body["callback"] as? String ?? "__handleNotificationPermission__"
            sendNotificationPermission(callbackName: callbackName)

        case "requestNotificationPermission":
            if let url = URL(string: UIApplication.openSettingsURLString) {
                UIApplication.shared.open(url)
            }

        case "openExternalUrl":
            if let urlString = body["url"] as? String, let url = URL(string: urlString) {
                UIApplication.shared.open(url)
            }

        case "haptic":
            let style = body["style"] as? String ?? "error"
            switch style {
            case "success":
                UINotificationFeedbackGenerator().notificationOccurred(.success)
            case "warning":
                UINotificationFeedbackGenerator().notificationOccurred(.warning)
            case "light":
                UIImpactFeedbackGenerator(style: .light).impactOccurred()
            case "medium":
                UIImpactFeedbackGenerator(style: .medium).impactOccurred()
            case "heavy":
                UIImpactFeedbackGenerator(style: .heavy).impactOccurred()
            default:
                UINotificationFeedbackGenerator().notificationOccurred(.error)
            }

        case "debugLog":
            let step = body["step"] as? String ?? "?"
            let msg = body["message"] as? String ?? ""
            let data = body["data"] as? String ?? ""
            print("[DebugLog] [Step \(step)] \(msg) \(data)")

        case "consoleLog":
            print("[JS LOG] \(body["message"] as? String ?? "")")
        case "consoleError":
            print("[JS ERROR] \(body["message"] as? String ?? "")")
        case "consoleWarn":
            print("[JS WARN] \(body["message"] as? String ?? "")")

        default:
            print("[WebView] Unknown action: \(action)")
            break
        }
    }
}

// MARK: - JavaScript Execution
extension MainView {
    /// JavaScript 함수들을 순차적으로 실행
    private func executeJavaScriptSequentially() {
        // 1. sendTypeToWebView 실행
        executeWithCompletion(
            name: "sendTypeToWebView",
            action: { completion in
                sendTypeToWebView(completion: completion)
            },
            onComplete: {
                // 2. FCM 토큰 전송
                self.executeWithCompletion(
                    name: "sendFCMToken",
                    action: { completion in
                        if let savedToken = UserDefaults.standard.string(forKey: "fcmToken") {
                            self.sendFCMTokenToWebView(token: savedToken, completion: completion)
                        } else {
                            completion()
                        }
                    },
                    onComplete: {
                        print("[WebView] 모든 초기화 JavaScript 실행 완료")
                    }
                )
            }
        )
    }

    /// JavaScript 실행 완료를 보장하는 헬퍼 함수
    private func executeWithCompletion(name: String, action: @escaping (@escaping () -> Void) -> Void, onComplete: @escaping () -> Void) {
        print("[WebView] \(name) 실행 시작")
        action {
            print("[WebView] \(name) 완료")
            DispatchQueue.main.asyncAfter(deadline: .now() + 0.05) {
                onComplete()
            }
        }
    }

    // MARK: - 디바이스 타입 전송
    private func sendTypeToWebView(completion: (() -> Void)? = nil) {
        guard let webView = webView else {
            print("[TYPE] WebView가 아직 준비되지 않음")
            completion?()
            return
        }

        let appVersion = Bundle.main.infoDictionary?["CFBundleShortVersionString"] as? String ?? "unknown"
        let osVersion = UIDevice.current.systemVersion
        let modelName = UIDevice.current.model
        let deviceType = "\(modelName) (iOS \(osVersion))"

        let escapedDeviceType = deviceType
            .replacingOccurrences(of: "\\", with: "\\\\")
            .replacingOccurrences(of: "'", with: "\\'")
        let escapedAppVersion = appVersion
            .replacingOccurrences(of: "\\", with: "\\\\")
            .replacingOccurrences(of: "'", with: "\\'")

        let js = """
        if (window.__handleType__) {
            window.__handleType__({
               device_type: '\(escapedDeviceType)',
               app_version: '\(escapedAppVersion)',
               device: 1
            });
        }
        """

        webView.evaluateJavaScript(js) { _, error in
            if let error = error {
                print("[WebViewBridge] Error sending type: \(error)")
            } else {
                print("[WebViewBridge] type sent: (device_type: '\(escapedDeviceType)', app_version: '\(escapedAppVersion)', device: 1)")
            }
            completion?()
        }
    }

    // MARK: - FCM 토큰 전송
    private func sendFCMTokenToWebView(token: String, completion: (() -> Void)? = nil) {
        guard let webView = webView else {
            print("[FCM] WebView가 아직 준비되지 않음")
            completion?()
            return
        }

        let escapedToken = token
            .replacingOccurrences(of: "\\", with: "\\\\")
            .replacingOccurrences(of: "'", with: "\\'")

        let js = """
        if (window.__handleFCMToken__) {
            window.__handleFCMToken__('\(escapedToken)');
            console.log('[FCM] 토큰 수신 완료');
        } else {
            console.log('[FCM] 핸들러가 아직 준비되지 않음');
        }
        """

        webView.evaluateJavaScript(js) { result, error in
            if let error = error {
                print("[FCM] Error sending token: \(error.localizedDescription)")
            } else {
                print("[FCM] Token sent to WebView successfully")
            }
            completion?()
        }
    }

    // MARK: - 알림 권한 상태 전송
    private func sendNotificationPermission(callbackName: String) {
        UNUserNotificationCenter.current().getNotificationSettings { settings in
            let permission = settings.authorizationStatus == .denied ? "denied" : "granted"
            let js = """
            if (window.\(callbackName)) {
                window.\(callbackName)({
                    success: true,
                    data: JSON.stringify({
                        status: '\(permission)',
                        enabled: true
                    })
                });
            }
            """

            DispatchQueue.main.async {
                guard let webView = self.webView else { return }
                webView.evaluateJavaScript(js) { result, error in
                    if let error = error {
                        print("[Notification] Error sending permission: \(error.localizedDescription)")
                    }
                }
            }
        }
    }

    // MARK: - 네트워크 모니터링
    private func startNetworkMonitoring() {
        let monitor = NWPathMonitor()
        monitor.pathUpdateHandler = { path in
            DispatchQueue.main.async {
                let previousState = networkState
                networkState = path.status

                // 네트워크 복구 시 웹뷰 리로드
                if path.status == .satisfied && previousState == .unsatisfied {
                    shouldReload = true
                }
            }
        }
        monitor.start(queue: DispatchQueue.global())
    }
}
