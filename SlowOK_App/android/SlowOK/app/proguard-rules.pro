# Add project specific ProGuard rules here.
# You can control the set of applied configuration files using the
# proguardFiles setting in build.gradle.kts.

# ============================================================
# WebView
# ============================================================
# Keep JavaScript interface methods
-keepclassmembers class com.spoon.slowok.feature.webView.di.WebAppInterface {
    @android.webkit.JavascriptInterface <methods>;
}

# Keep WebView JavaScript interfaces
-keepattributes JavascriptInterface
-keepattributes *Annotation*

# Keep WebView client classes
-keep class * extends android.webkit.WebViewClient
-keep class * extends android.webkit.WebChromeClient

# ============================================================
# Firebase
# ============================================================
-keep class com.google.firebase.** { *; }
-keep class com.google.android.gms.** { *; }
-dontwarn com.google.firebase.**
-dontwarn com.google.android.gms.**

# ============================================================
# AndroidX
# ============================================================
-keep class androidx.** { *; }
-dontwarn androidx.**

# ============================================================
# General
# ============================================================
# Keep the application class
-keep class com.spoon.slowok.MyApp { *; }

# Keep activity classes
-keep class com.spoon.slowok.feature.splash.ui.SplashActivity { *; }
-keep class com.spoon.slowok.feature.webView.ui.WebViewActivity { *; }

# Keep service classes
-keep class com.spoon.slowok.service.MyFirebaseMessagingService { *; }

# Remove logging in release
-assumenosideeffects class android.util.Log {
    public static int v(...);
    public static int d(...);
    public static int i(...);
}

# Keep enum classes
-keepclassmembers enum * {
    public static **[] values();
    public static ** valueOf(java.lang.String);
}

# Keep Parcelable implementations
-keepclassmembers class * implements android.os.Parcelable {
    public static final android.os.Parcelable$Creator CREATOR;
}

# Keep Serializable implementations
-keepclassmembers class * implements java.io.Serializable {
    static final long serialVersionUID;
    private static final java.io.ObjectStreamField[] serialPersistentFields;
    private void writeObject(java.io.ObjectOutputStream);
    private void readObject(java.io.ObjectInputStream);
    java.lang.Object writeReplace();
    java.lang.Object readResolve();
}
