# Middo Corporate (Flutter)

iOS & Android app for Middo’s **corporate** role — office lunch ordering, scheduling, tracking, wallet, and support.

## Screens

- Login
- Home dashboard
- Menu browse + filters
- Multi-date checkout
- Scheduled orders
- Order tracking timeline
- Order history
- Wallet / top-up
- Complaint / support chat

## Production API

Default API root: `https://x.middo.com.bd`

### Build release APK

```bash
cd mobile/corporate
flutter pub get
flutter build apk --release \
  --dart-define=API_BASE_URL=https://x.middo.com.bd
```

Output: `build/app/outputs/flutter-apk/app-release.apk`

### Release signing (Play Store / production)

Without `android/key.properties`, release builds fall back to the **debug** keystore (fine for sideload tests only).

One-time setup on a machine with JDK `keytool`:

```bash
cd mobile/corporate/android
keytool -genkey -v -keystore upload-keystore.jks -keyalg RSA -keysize 2048 -validity 10000 -alias middo-corporate
cp key.properties.example key.properties
# Edit key.properties with your store/key passwords
```

Keep `upload-keystore.jks` and `key.properties` out of git (already gitignored). Losing the keystore blocks Play Store updates for the same app listing.

## Run (local Laravel API)

From the monorepo root:

```bash
php artisan serve
```

Then:

```bash
cd mobile/corporate
flutter pub get
flutter run \
  --dart-define=API_BASE_URL=http://127.0.0.1:8000
```

Android emulator tip: use `http://10.0.2.2:8000` as `API_BASE_URL`.

Demo corporate login (seeded):

- Mobile: `01310123452`
- Password: `12345678`

Offline mock mode:

```bash
flutter run --dart-define=USE_MOCK=true
```

Requires [Flutter](https://docs.flutter.dev/get-started/install) 3.32+.

## Architecture

- **UI:** Flutter + Material 3, Middo brand tokens, Plus Jakarta Sans
- **Routing:** `go_router` with bottom-tab shell (Home / Menu / Schedule / Wallet)
- **Auth:** Laravel Sanctum bearer tokens (`AuthStore` + `shared_preferences`)
- **Data:** `ApiCorporateRepository` → `/api/corporate/*` (mock fallback via `USE_MOCK`)

Design reference (HTML prototype): `/designs/corporate-mobile/`

## API

| Method | Path | Notes |
|---|---|---|
| POST | `/api/corporate/login` | mobile + password → Sanctum token |
| POST | `/api/corporate/register/send-otp` | SMS OTP for signup (debug OTP `1234` when `APP_DEBUG=true`) |
| POST | `/api/corporate/register` | create corporate account (requires OTP) → token |
| POST | `/api/corporate/forgot-password` | SMS OTP for password reset |
| POST | `/api/corporate/reset-password` | otp + new password |
| GET | `/api/corporate/locations` | cities + areas for signup |
| POST | `/api/corporate/logout` | revoke current token |
| GET | `/api/corporate/me` | current profile |
| PATCH | `/api/corporate/profile` | update profile |
| POST | `/api/corporate/change-password` | current + new password |
| GET | `/api/corporate/dashboard` | KPIs + upcoming/recent |
| GET | `/api/corporate/menu` | featured menu + checkout dates/cities |
| POST | `/api/corporate/orders/send-otp` | SMS OTP + prepayment quote |
| POST | `/api/corporate/orders/gateway-prepay` | start online prepayment session |
| POST | `/api/corporate/orders` | multi-date schedule (OTP; prepay via `payment_method` when required) |
| DELETE | `/api/corporate/orders/{id}` | cancel pending order before cut-off (refund balance) |
| GET | `/api/corporate/orders/scheduled` | upcoming |
| GET | `/api/corporate/orders/history` | past |
| GET | `/api/corporate/orders/{id}/track` | order logs timeline |
| GET/POST | `/api/corporate/orders/{id}/support` | complaint thread |
| POST | `/api/corporate/wallet/top-up` | credit Middo balance |
| POST | `/api/corporate/device-tokens` | register FCM device token |
| DELETE | `/api/corporate/device-tokens` | unregister FCM device token |

## Push notifications (FCM HTTP v1)

Order status changes (`processing`, `on_the_way_to_delivery`, `delivered` / `delivered_and_paid`, `cancelled`) enqueue `SendOrderStatusPush`, which delivers via the **FCM HTTP v1** API (service account OAuth — not the deprecated legacy server key).

### Server

1. In Firebase Console → **Project settings → Service accounts**, click **Generate new private key** and download the JSON.
2. Place it on the server (recommended path: `storage/app/firebase/service-account.json`) and set in `.env`:

```env
FIREBASE_CREDENTIALS=/absolute/or/relative/path/to/service-account.json
# Optional — defaults to project_id inside the JSON (middo-55888):
# FIREBASE_PROJECT_ID=middo-55888
```

3. Run migrations: `php artisan migrate` (creates `device_tokens`), **or** create the table in phpMyAdmin with:

```sql
CREATE TABLE `device_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `token` varchar(512) NOT NULL,
  `platform` varchar(32) NOT NULL DEFAULT 'android',
  `device_name` varchar(120) DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `device_tokens_token_unique` (`token`),
  KEY `device_tokens_user_id_platform_index` (`user_id`, `platform`),
  CONSTRAINT `device_tokens_user_id_foreign`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

4. Push jobs run **inline** when an order status changes (no `queue:work` / SSH required).

Without a valid service account file, the API still accepts device tokens but skips sending (logged as skipped).

Do **not** commit the service account JSON — it is gitignored under `storage/app/firebase/`.

### Android app

1. Create a Firebase project and add an Android app with package `com.middo.corporates`.
2. Download the real `google-services.json` and place it at `android/app/google-services.json`.
3. Rebuild the APK. After login, the app registers its FCM token with `POST /api/corporate/device-tokens`.
4. Tapping a notification opens `/track/{orderId}`.

Foreground updates show a snackbar with a Track action.

**Android application id:** `com.middo.corporates` (must match Firebase).
