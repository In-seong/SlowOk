//
//  WebView.swift
//  SlowOK
//
//  Created by scoop dev on 3/1/26.
//

import SwiftUI
import WebKit

struct WebViewContainer: UIViewRepresentable {
    let url: URL?
    @Binding var webView: WKWebView?
    @Binding var isError: Bool
    @Binding var shouldReload: Bool
    var onMessage: ((String, [String: Any]) -> Void)?
    var onLoadComplete: (() -> Void)?

    func makeCoordinator() -> WebViewCoordinator {
        WebViewCoordinator(self)
    }

    func makeUIView(context: Context) -> WKWebView {

        // WebView 설정
        let config = WKWebViewConfiguration()
        config.allowsInlineMediaPlayback = true
        config.mediaTypesRequiringUserActionForPlayback = []

        // JS -> Native 브릿지 등록
        config.userContentController.add(context.coordinator, name: "iOSBridge")

        // JS console.log를 네이티브 콘솔로 출력
        let consoleScript = WKUserScript(
            source: """
            (function() {
                var origLog = console.log;
                var origError = console.error;
                var origWarn = console.warn;
                console.log = function() { origLog.apply(console, arguments); window.webkit.messageHandlers.iOSBridge.postMessage({action:'consoleLog', message: Array.from(arguments).join(' ')}); };
                console.error = function() { origError.apply(console, arguments); window.webkit.messageHandlers.iOSBridge.postMessage({action:'consoleError', message: Array.from(arguments).join(' ')}); };
                console.warn = function() { origWarn.apply(console, arguments); window.webkit.messageHandlers.iOSBridge.postMessage({action:'consoleWarn', message: Array.from(arguments).join(' ')}); };
                window.onerror = function(msg, url, line) { window.webkit.messageHandlers.iOSBridge.postMessage({action:'consoleError', message: msg + ' at ' + url + ':' + line}); };
            })();
            """,
            injectionTime: .atDocumentStart,
            forMainFrameOnly: true
        )
        config.userContentController.addUserScript(consoleScript)

        let wv = WKWebView(frame: .zero, configuration: config)

        wv.navigationDelegate = context.coordinator
        wv.uiDelegate = context.coordinator
        wv.scrollView.bounces = false
        wv.scrollView.bouncesZoom = false
        wv.scrollView.minimumZoomScale = 1.0
        wv.scrollView.maximumZoomScale = 1.0
        wv.scrollView.pinchGestureRecognizer?.isEnabled = false
        wv.scrollView.isMultipleTouchEnabled = false
        wv.isOpaque = true
        wv.backgroundColor = .white

        // Coordinator에 webView 참조 저장
        context.coordinator.webView = wv

        // 바인딩 업데이트
        DispatchQueue.main.async {
            self.webView = wv
        }

        if let myURL = url {
            var request = URLRequest(url: myURL)
            if let cookies = HTTPCookieStorage.shared.cookies(for: myURL) {
                request.allHTTPHeaderFields = HTTPCookie.requestHeaderFields(with: cookies)
            }
            wv.load(request)
        }

        return wv
    }

    func updateUIView(_ uiView: WKWebView, context: Context) {
        // shouldReload가 true면 리로드 실행
        if shouldReload {
            DispatchQueue.main.async {
                self.shouldReload = false
            }
            if let myURL = url {
                var request = URLRequest(url: myURL)
                if let cookies = HTTPCookieStorage.shared.cookies(for: myURL) {
                    request.allHTTPHeaderFields = HTTPCookie.requestHeaderFields(with: cookies)
                }
                uiView.load(request)
            }
        }
    }
}

class WebViewCoordinator: NSObject {
    var parent: WebViewContainer
    var webView: WKWebView?

    init(_ parent: WebViewContainer) {
        self.parent = parent
    }
}

// MARK: - WebView Navigation Delegate
extension WebViewCoordinator: WKNavigationDelegate {
    // 외부 호스트 링크는 Safari로 열기 (앱 도메인 외 모든 http/https는 외부)
    func webView(_ webView: WKWebView, decidePolicyFor navigationAction: WKNavigationAction, decisionHandler: @escaping (WKNavigationActionPolicy) -> Void) {
        guard let url = navigationAction.request.url else {
            decisionHandler(.allow)
            return
        }

        // 사용자 액션으로 발생한 링크 클릭만 검사
        if navigationAction.navigationType == .linkActivated,
           let host = url.host,
           !host.contains("revuplan.com") {
            if UIApplication.shared.canOpenURL(url) {
                UIApplication.shared.open(url, options: [:], completionHandler: nil)
            }
            decisionHandler(.cancel)
            return
        }

        decisionHandler(.allow)
    }

    // 정상 로드 시
    func webView(_ webView: WKWebView, didFinish navigation: WKNavigation!) {
        parent.isError = false

        defaultJS(webView)
        // WebView 로드 완료 콜백
        parent.onLoadComplete?()
    }

    // 에러
    func webView(_ webView: WKWebView, didFail navigation: WKNavigation!, withError error: Error) {
        print("[WebView] ❌ didFail: \(error.localizedDescription)")
        parent.isError = true
    }

    func webView(_ webView: WKWebView, didFailProvisionalNavigation navigation: WKNavigation!, withError error: Error) {
        print("[WebView] ❌ didFailProvisional: \(error.localizedDescription)")
        parent.isError = true
    }

    func webView(_ webView: WKWebView, didStartProvisionalNavigation navigation: WKNavigation!) {
        print("[WebView] 🔄 Loading: \(webView.url?.absoluteString ?? "nil")")
    }

    func webView(_ webView: WKWebView, didCommit navigation: WKNavigation!) {
        print("[WebView] 📄 Committed: \(webView.url?.absoluteString ?? "nil")")
    }
}

// MARK: - JS -> iOS 메시지 처리
extension WebViewCoordinator: WKScriptMessageHandler {
    func userContentController(_ userContentController: WKUserContentController, didReceive message: WKScriptMessage) {
        guard message.name == "iOSBridge" else { return }
        guard let body = message.body as? [String: Any],
              let action = body["action"] as? String else {
            print("Invalid message format")
            return
        }

        parent.onMessage?(action, body)
    }
}

// MARK: - WKUIDelegate (alert, confirm, prompt 처리)
extension WebViewCoordinator: WKUIDelegate {
    // target="_blank" 또는 window.open() 으로 새 창 열기 요청 시 외부 브라우저(Safari)로 열기
    func webView(_ webView: WKWebView, createWebViewWith configuration: WKWebViewConfiguration, for navigationAction: WKNavigationAction, windowFeatures: WKWindowFeatures) -> WKWebView? {
        if let url = navigationAction.request.url {
            if UIApplication.shared.canOpenURL(url) {
                UIApplication.shared.open(url, options: [:], completionHandler: nil)
            }
        }
        return nil
    }

    func webView(_ webView: WKWebView, runJavaScriptAlertPanelWithMessage message: String, initiatedByFrame frame: WKFrameInfo, completionHandler: @escaping () -> Void) {
        let alert = UIAlertController(title: nil, message: message, preferredStyle: .alert)
        alert.addAction(UIAlertAction(title: "확인", style: .default) { _ in
            completionHandler()
        })

        if let scene = UIApplication.shared.connectedScenes.first as? UIWindowScene,
           let rootVC = scene.windows.first?.rootViewController {
            rootVC.present(alert, animated: true)
        } else {
            completionHandler()
        }
    }

    func webView(_ webView: WKWebView, runJavaScriptConfirmPanelWithMessage message: String, initiatedByFrame frame: WKFrameInfo, completionHandler: @escaping (Bool) -> Void) {
        let alert = UIAlertController(title: nil, message: message, preferredStyle: .alert)
        alert.addAction(UIAlertAction(title: "취소", style: .cancel) { _ in
            completionHandler(false)
        })
        alert.addAction(UIAlertAction(title: "확인", style: .default) { _ in
            completionHandler(true)
        })

        if let scene = UIApplication.shared.connectedScenes.first as? UIWindowScene,
           let rootVC = scene.windows.first?.rootViewController {
            rootVC.present(alert, animated: true)
        } else {
            completionHandler(false)
        }
    }

    func webView(_ webView: WKWebView, requestMediaCapturePermissionFor origin: WKSecurityOrigin, initiatedByFrame frame: WKFrameInfo, type: WKMediaCaptureType, decisionHandler: @escaping (WKPermissionDecision) -> Void) {
        // 음성 기능 비활성화 — 모든 미디어 권한 거부
        decisionHandler(.deny)
    }

    func webView(_ webView: WKWebView, runJavaScriptTextInputPanelWithPrompt prompt: String, defaultText: String?, initiatedByFrame frame: WKFrameInfo, completionHandler: @escaping (String?) -> Void) {
        let alert = UIAlertController(title: nil, message: prompt, preferredStyle: .alert)
        alert.addTextField { textField in
            textField.text = defaultText
        }
        alert.addAction(UIAlertAction(title: "취소", style: .cancel) { _ in
            completionHandler(nil)
        })
        alert.addAction(UIAlertAction(title: "확인", style: .default) { _ in
            completionHandler(alert.textFields?.first?.text)
        })

        if let scene = UIApplication.shared.connectedScenes.first as? UIWindowScene,
           let rootVC = scene.windows.first?.rootViewController {
            rootVC.present(alert, animated: true)
        } else {
            completionHandler(nil)
        }
    }
}

// MARK: - Default JS Injection
extension WebViewCoordinator {

    /// 웹뷰 줌, 더블 클릭 등 iOS 기본 웹 제스쳐 제거
    func defaultJS(_ webView: WKWebView) {
        var js = """
        var meta = document.createElement('meta');
        meta.name = 'viewport';
        meta.content = 'width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no';
        document.getElementsByTagName('head')[0].appendChild(meta);
        """

        js += """
        document.documentElement.style.webkitUserSelect = 'none';
        document.documentElement.style.webkitTouchCallout = 'none';
        """

        js += """
        document.addEventListener('dragstart', function(e) {
            if (e.target.tagName === 'IMG') {
                e.preventDefault();
            }
        });
        """
        webView.evaluateJavaScript(js, completionHandler: nil)
    }
}
