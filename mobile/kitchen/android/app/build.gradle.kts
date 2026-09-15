import java.util.Properties
import java.io.FileInputStream

plugins {
    id("com.android.application")
    id("kotlin-android")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
    id("com.google.gms.google-services")
}

val keystoreProperties = Properties()
val keystorePropertiesFile = rootProject.file("key.properties")
val hasReleaseKeystore = keystorePropertiesFile.exists()
if (hasReleaseKeystore) {
    keystoreProperties.load(FileInputStream(keystorePropertiesFile))
}

android {
    namespace = "com.middo.kitchen"
    compileSdk = flutter.compileSdkVersion
    ndkVersion = "28.2.13676358"

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_11
        targetCompatibility = JavaVersion.VERSION_11
    }

    kotlinOptions {
        jvmTarget = JavaVersion.VERSION_11.toString()
    }

    // image_picker_android declares minSdk 24, so we cannot go lower without
    // tools:overrideLibrary (unsafe). Keep an explicit val so Flutter's migrator
    // (which rewrites bare minSdk = 16..23) cannot silently change policy.
    val middoMinSdk = 24

    defaultConfig {
        applicationId = "com.middo.kitchen"
        // You can update the following values to match your application needs.
        // For more information, see: https://flutter.dev/to/review-gradle-config.
        minSdk = middoMinSdk
        targetSdk = flutter.targetSdkVersion
        versionCode = flutter.versionCode
        versionName = flutter.versionName

        // Do not abiFilter here — Flutter ships armeabi-v7a + arm64-v8a + x86_64.
        // Filtering would silently drop 32-bit ARM (~large Play device share).
    }

    packaging {
        jniLibs {
            // Legacy packaging (extractNativeLibs=true) avoids install failures on
            // some older API-24/25 devices with compressed 16KB-aligned native libs.
            useLegacyPackaging = true
        }
    }

    signingConfigs {
        if (hasReleaseKeystore) {
            create("release") {
                keyAlias = keystoreProperties["keyAlias"] as String
                keyPassword = keystoreProperties["keyPassword"] as String
                storeFile = rootProject.file(keystoreProperties["storeFile"] as String)
                storePassword = keystoreProperties["storePassword"] as String
            }
        }
    }

    buildTypes {
        release {
            // Require upload keystore for release — silent debug fallback caused Play Console
            // "signed in debug mode" rejects. Sideload with `flutter build apk --debug` instead.
            check(hasReleaseKeystore) {
                "Missing android/key.properties (and upload-keystore.jks). " +
                    "Copy key.properties.example → key.properties and set store/key passwords. " +
                    "See docs/play-store-middo-kitchen.md."
            }
            signingConfig = signingConfigs.getByName("release")
        }
    }
}

flutter {
    source = "../.."
}

dependencies {
    // Firebase Android BoM — keeps native Firebase libs on compatible versions.
    // Dart APIs come from the Flutter firebase_* plugins.
    implementation(platform("com.google.firebase:firebase-bom:34.16.0"))
    implementation("com.google.firebase:firebase-analytics")
}
