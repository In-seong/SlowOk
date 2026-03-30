package com.inseong.slowok.feature.webView.di;

import android.Manifest;
import android.app.Activity;
import android.content.Intent;
import android.content.pm.PackageInfo;
import android.content.pm.PackageManager;
import android.net.Uri;
import android.os.Build;
import android.os.Handler;
import android.os.Looper;
import android.provider.Settings;
import android.util.Log;
import android.webkit.JavascriptInterface;
import android.webkit.WebView;

import androidx.core.app.NotificationManagerCompat;
import androidx.core.content.ContextCompat;

import com.google.firebase.messaging.FirebaseMessaging;

import org.json.JSONObject;

public class WebAppInterface {

    private static final String TAG = "WebAppInterface";

    private final Activity activity;
    private final WebView webView;
    private final Handler mainHandler;

    public WebAppInterface(Activity activity, WebView webView) {
        this.activity = activity;
        this.webView = webView;
        this.mainHandler = new Handler(Looper.getMainLooper());
    }

    /**
     * Sends the FCM token to the web app via window.__handleFCMToken__()
     */
    @JavascriptInterface
    public void sendFCMToken() {
        FirebaseMessaging.getInstance().getToken()
                .addOnCompleteListener(task -> {
                    if (!task.isSuccessful()) {
                        Log.w(TAG, "Fetching FCM registration token failed", task.getException());
                        return;
                    }

                    String token = task.getResult();
                    Log.d(TAG, "FCM Token: " + token);

                    try {
                        JSONObject json = new JSONObject();
                        json.put("fcm_token", token);

                        String script = "javascript:window.__handleFCMToken__('" +
                                json.toString().replace("'", "\\'") + "')";
                        safeEvaluateJavascript(script);
                    } catch (Exception e) {
                        Log.e(TAG, "Failed to send FCM token", e);
                    }
                });
    }

    /**
     * Sends device type information to the web app via window.__handleType__()
     * device: 0 = Android
     */
    @JavascriptInterface
    public void sendDeviceType() {
        try {
            JSONObject json = new JSONObject();
            json.put("device_type", "android");
            json.put("app_version", getAppVersion());
            json.put("device", 0); // 0 = Android

            String script = "javascript:window.__handleType__('" +
                    json.toString().replace("'", "\\'") + "')";
            safeEvaluateJavascript(script);
        } catch (Exception e) {
            Log.e(TAG, "Failed to send device type", e);
        }
    }

    /**
     * Checks notification permission and reports to web app
     * via window.__handleNotificationPermission__()
     */
    @JavascriptInterface
    public void getNotificationPermission() {
        boolean isEnabled;

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            isEnabled = ContextCompat.checkSelfPermission(
                    activity, Manifest.permission.POST_NOTIFICATIONS
            ) == PackageManager.PERMISSION_GRANTED;
        } else {
            isEnabled = NotificationManagerCompat.from(activity).areNotificationsEnabled();
        }

        try {
            JSONObject json = new JSONObject();
            json.put("permission", isEnabled);

            String script = "javascript:window.__handleNotificationPermission__('" +
                    json.toString().replace("'", "\\'") + "')";
            safeEvaluateJavascript(script);
        } catch (Exception e) {
            Log.e(TAG, "Failed to check notification permission", e);
        }
    }

    /**
     * Opens the app's notification settings page
     */
    @JavascriptInterface
    public void requestNotificationPermission() {
        mainHandler.post(() -> {
            try {
                Intent intent = new Intent();
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                    intent.setAction(Settings.ACTION_APP_NOTIFICATION_SETTINGS);
                    intent.putExtra(Settings.EXTRA_APP_PACKAGE, activity.getPackageName());
                } else {
                    intent.setAction(Settings.ACTION_APPLICATION_DETAILS_SETTINGS);
                    intent.setData(Uri.parse("package:" + activity.getPackageName()));
                }
                activity.startActivity(intent);
            } catch (Exception e) {
                Log.e(TAG, "Failed to open notification settings", e);
            }
        });
    }

    /**
     * Triggers haptic feedback / vibration
     * @param duration vibration duration in milliseconds
     */
    @JavascriptInterface
    public void vibrate(int duration) {
        mainHandler.post(() -> {
            try {
                android.os.Vibrator vibrator;
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                    android.os.VibratorManager vm = (android.os.VibratorManager) activity.getSystemService(android.content.Context.VIBRATOR_MANAGER_SERVICE);
                    vibrator = vm.getDefaultVibrator();
                } else {
                    vibrator = (android.os.Vibrator) activity.getSystemService(android.content.Context.VIBRATOR_SERVICE);
                }
                if (vibrator != null && vibrator.hasVibrator()) {
                    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                        vibrator.vibrate(android.os.VibrationEffect.createOneShot(duration, android.os.VibrationEffect.DEFAULT_AMPLITUDE));
                    } else {
                        vibrator.vibrate(duration);
                    }
                }
            } catch (Exception e) {
                Log.e(TAG, "Failed to vibrate", e);
            }
        });
    }

    /**
     * Closes the app
     */
    @JavascriptInterface
    public void closeApp() {
        mainHandler.post(() -> {
            activity.finishAffinity();
        });
    }

    /**
     * Safely evaluates JavaScript on the WebView from any thread.
     * Ensures execution on the main thread and null-checks the WebView.
     *
     * @param script The JavaScript code to evaluate
     */
    private void safeEvaluateJavascript(String script) {
        if (webView == null || activity == null || activity.isFinishing()) {
            Log.w(TAG, "Cannot evaluate JS - WebView or Activity is null/finishing");
            return;
        }

        mainHandler.post(() -> {
            try {
                if (webView != null && !activity.isFinishing()) {
                    webView.evaluateJavascript(
                            script.replace("javascript:", ""),
                            value -> Log.d(TAG, "JS evaluation result: " + value)
                    );
                }
            } catch (Exception e) {
                Log.e(TAG, "Failed to evaluate JavaScript", e);
            }
        });
    }

    /**
     * Gets the app version name from PackageInfo
     */
    private String getAppVersion() {
        try {
            PackageInfo pInfo = activity.getPackageManager()
                    .getPackageInfo(activity.getPackageName(), 0);
            return pInfo.versionName;
        } catch (PackageManager.NameNotFoundException e) {
            Log.e(TAG, "Failed to get app version", e);
            return "1.0.0";
        }
    }
}
