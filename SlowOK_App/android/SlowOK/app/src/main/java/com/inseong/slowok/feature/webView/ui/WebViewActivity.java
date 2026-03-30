package com.inseong.slowok.feature.webView.ui;

import android.annotation.SuppressLint;
import android.content.Intent;
import android.graphics.Bitmap;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.util.Log;
import android.view.KeyEvent;
import android.webkit.CookieManager;
import android.webkit.ValueCallback;
import android.webkit.PermissionRequest;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;

import androidx.activity.result.ActivityResultLauncher;
import androidx.activity.result.contract.ActivityResultContracts;
import androidx.appcompat.app.AppCompatActivity;

import com.inseong.slowok.BuildConfig;
import com.inseong.slowok.R;
import com.inseong.slowok.databinding.ActivityWebviewBinding;
import com.inseong.slowok.feature.webView.di.WebAppInterface;
import com.inseong.slowok.feature.webView.di.WebConstants;

public class WebViewActivity extends AppCompatActivity {

    private static final String TAG = "WebViewActivity";

    private ActivityWebviewBinding binding;
    private WebAppInterface webAppInterface;

    // File upload support
    private ValueCallback<Uri[]> fileUploadCallback;
    private final ActivityResultLauncher<Intent> fileChooserLauncher =
            registerForActivityResult(
                    new ActivityResultContracts.StartActivityForResult(),
                    result -> {
                        if (fileUploadCallback == null) return;

                        Uri[] results = null;
                        if (result.getResultCode() == RESULT_OK && result.getData() != null) {
                            String dataString = result.getData().getDataString();
                            if (dataString != null) {
                                results = new Uri[]{Uri.parse(dataString)};
                            }
                        }
                        fileUploadCallback.onReceiveValue(results);
                        fileUploadCallback = null;
                    }
            );

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityWebviewBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());

        setupWebView();
        loadUrl();
    }

    @SuppressLint("SetJavaScriptEnabled")
    private void setupWebView() {
        WebView webView = binding.webView;
        WebSettings webSettings = webView.getSettings();

        // JavaScript
        webSettings.setJavaScriptEnabled(true);
        webSettings.setJavaScriptCanOpenWindowsAutomatically(true);

        // DOM Storage & Database
        webSettings.setDomStorageEnabled(true);
        webSettings.setDatabaseEnabled(true);

        // File access
        webSettings.setAllowFileAccess(true);
        webSettings.setAllowContentAccess(true);

        // Mixed content (for development)
        webSettings.setMixedContentMode(WebSettings.MIXED_CONTENT_ALWAYS_ALLOW);

        // Cache
        webSettings.setCacheMode(WebSettings.LOAD_DEFAULT);

        // Viewport & Zoom
        webSettings.setUseWideViewPort(true);
        webSettings.setLoadWithOverviewMode(true);
        webSettings.setSupportZoom(false);
        webSettings.setBuiltInZoomControls(false);
        webSettings.setDisplayZoomControls(false);

        // User agent
        String defaultUserAgent = webSettings.getUserAgentString();
        webSettings.setUserAgentString(defaultUserAgent + " SlowOK_Android");

        // Text encoding
        webSettings.setDefaultTextEncodingName("UTF-8");

        // Media playback
        webSettings.setMediaPlaybackRequiresUserGesture(false);

        // Cookies
        CookieManager cookieManager = CookieManager.getInstance();
        cookieManager.setAcceptCookie(true);
        cookieManager.setAcceptThirdPartyCookies(webView, true);

        // JavaScript Bridge
        webAppInterface = new WebAppInterface(this, webView);
        webView.addJavascriptInterface(webAppInterface, "AndroidBridge");

        // WebViewClient
        webView.setWebViewClient(new WebViewClient() {
            @Override
            public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
                String url = request.getUrl().toString();

                // Handle external URLs (tel, mailto, etc.)
                if (url.startsWith("tel:") || url.startsWith("mailto:") ||
                        url.startsWith("sms:") || url.startsWith("intent:")) {
                    try {
                        Intent intent = new Intent(Intent.ACTION_VIEW, Uri.parse(url));
                        startActivity(intent);
                    } catch (Exception e) {
                        Log.e(TAG, "Failed to handle external URL: " + url, e);
                    }
                    return true;
                }

                // Handle market URLs
                if (url.startsWith("market:") || url.contains("play.google.com")) {
                    try {
                        Intent intent = new Intent(Intent.ACTION_VIEW, Uri.parse(url));
                        startActivity(intent);
                    } catch (Exception e) {
                        Log.e(TAG, "Failed to open market: " + url, e);
                    }
                    return true;
                }

                return false;
            }

            @Override
            public void onPageStarted(WebView view, String url, Bitmap favicon) {
                super.onPageStarted(view, url, favicon);
                Log.d(TAG, "Page started: " + url);
            }

            @Override
            public void onPageFinished(WebView view, String url) {
                super.onPageFinished(view, url);
                Log.d(TAG, "Page finished: " + url);
            }
        });

        // WebChromeClient
        webView.setWebChromeClient(new WebChromeClient() {
            @Override
            public boolean onShowFileChooser(WebView webView,
                                             ValueCallback<Uri[]> filePathCallback,
                                             FileChooserParams fileChooserParams) {
                if (fileUploadCallback != null) {
                    fileUploadCallback.onReceiveValue(null);
                }
                fileUploadCallback = filePathCallback;

                try {
                    Intent intent = fileChooserParams.createIntent();
                    fileChooserLauncher.launch(intent);
                } catch (Exception e) {
                    Log.e(TAG, "Failed to open file chooser", e);
                    fileUploadCallback.onReceiveValue(null);
                    fileUploadCallback = null;
                }
                return true;
            }

            @Override
            public void onPermissionRequest(final PermissionRequest request) {
                // 음성 기능 비활성화 — 모든 미디어 권한 거부
                request.deny();
            }
        });

        // Enable debugging in debug builds
        if (BuildConfig.DEBUG) {
            WebView.setWebContentsDebuggingEnabled(true);
        }
    }

    private void loadUrl() {
        String url;
        if (BuildConfig.DEBUG) {
            url = WebConstants.DEBUG_WebView_URL;
        } else {
            url = WebConstants.WebView_URL;
        }
        Log.d(TAG, "Loading URL: " + url);
        binding.webView.loadUrl(url);
    }

    @Override
    protected void onNewIntent(Intent intent) {
        super.onNewIntent(intent);
        setIntent(intent);

        // Handle FCM notification tap - navigate to specific URL if provided
        Bundle extras = intent.getExtras();
        if (extras != null) {
            String targetUrl = extras.getString("url");
            if (targetUrl != null && !targetUrl.isEmpty()) {
                Log.d(TAG, "onNewIntent - Loading target URL: " + targetUrl);
                binding.webView.loadUrl(targetUrl);
            }
        }
    }

    @Override
    public boolean onKeyDown(int keyCode, KeyEvent event) {
        if (keyCode == KeyEvent.KEYCODE_BACK) {
            if (binding.webView.canGoBack()) {
                binding.webView.goBack();
                return true;
            }
        }
        return super.onKeyDown(keyCode, event);
    }

    @Override
    protected void onResume() {
        super.onResume();
        binding.webView.onResume();
    }

    @Override
    protected void onPause() {
        super.onPause();
        binding.webView.onPause();
    }

    @Override
    protected void onDestroy() {
        if (binding != null && binding.webView != null) {
            binding.webView.removeJavascriptInterface("AndroidBridge");
            binding.webView.destroy();
        }
        super.onDestroy();
    }
}
