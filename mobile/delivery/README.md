# Middo Delivery (Flutter)

Android/iOS rider app — kitchen pickups, Middo box runs, cash collection, and wallet.

Maps to delivery PWA IA: **Home · Runs · Boxes · Cash · More**.

Package version: `0.3.1+4` · application id: `com.middo.delivery`

## Screens

| Area | Status |
|------|--------|
| Login / splash / logout | Wired + FCM token sync |
| Home | Dashboard tiles + shift chip (on / off / unable) |
| Runs | Active list with pickup / deliver (OTP + optional POD photo) + run detail + customer ETA chips |
| Boxes | Pending actions + bulk `run_groups` accept/hand |
| Cash | Collect cash (collection − commission preview) + order-based handovers |
| Account | Wallet, withdraw guards (`can_request_payment`), statement / withdrawals |
| Alerts | List, mark read / mark all read |
| Profile | Change password + payout methods (bKash / Nagad / bank) |
| More | Custom runs start/complete, history, account links |
| Offline / UX | Connectivity banner, offline mutation queue, skeletons, deep links |

## Production API

Default API root: `https://x.middo.com.bd` → `/api/delivery`

### Run (local Laravel)

```bash
php artisan serve
cd mobile/delivery
flutter pub get
flutter run \
  --dart-define=API_BASE_URL=http://127.0.0.1:8000
```

Android emulator: `http://10.0.2.2:8000`.

Seeded delivery login:

- Mobile: `01310123454`
- Password: `12345678`

Offline mock:

```bash
flutter run --dart-define=USE_MOCK=true
```

Mock deliver OTP is `1234`.

### Build release APK / AAB

```bash
cd mobile/delivery
flutter pub get
flutter build apk --release \
  --android-skip-build-dependency-validation \
  --dart-define=API_BASE_URL=https://x.middo.com.bd
flutter build appbundle --release \
  --android-skip-build-dependency-validation \
  --dart-define=API_BASE_URL=https://x.middo.com.bd
```

Copy artifacts into `mobile/delivery/releases/` when publishing sideload builds.

### Build release AAB (Google Play upload)

```bash
cd mobile/delivery
flutter pub get
flutter build appbundle --release \
  --android-skip-build-dependency-validation \
  --dart-define=API_BASE_URL=https://x.middo.com.bd
```

Output: `build/app/outputs/bundle/release/app-release.aab`

### Release signing (Play Store / production)

Without `android/key.properties`, release builds fall back to the **debug** keystore (fine for sideload tests only).

One-time setup on a machine with JDK `keytool`:

```bash
cd mobile/delivery/android
keytool -genkey -v -keystore upload-keystore.jks -keyalg RSA -keysize 2048 -validity 10000 -alias middo-delivery
cp key.properties.example key.properties
# Edit key.properties with your store/key passwords
```

`keyAlias` must be `middo-delivery` (see `android/key.properties.example`).

Keep `upload-keystore.jks` and `key.properties` out of git (already gitignored). Losing the keystore blocks Play Store updates for the same app listing.

Play Console: create an app for package `com.middo.delivery`, upload the AAB, complete store listing / content ratings / target audience forms.

### Firebase

`android/app/google-services.json` is already present for `com.middo.delivery`. Replace it with a production Firebase Android app config when going live. Until a valid project is wired, push init no-ops gracefully.

Requires Flutter 3.32+.

## Offline mutation queue

`lib/data/offline_mutation_queue.dart` persists safe retries (pickup, deliver, collect cash, box actions) in `SharedPreferences` as a JSON list. Each item carries `id`, `method`, `path`, `body`, `created_at`, and `idempotency_key`.

- When offline, pickup / deliver / collect / box taps enqueue and snack **Saved — will sync when online**.
- Shell shows **N actions pending sync** while the queue is non-empty.
- On connectivity restore, the queue flushes FIFO. `ApiClient` sends `Idempotency-Key` on POST/PATCH when provided; multipart deliver uses the same header.

## POD / delivery OTP

Before deliver:

1. `POST /runs/{id}/send-delivery-otp`
2. Dialog: OTP + optional POD photo (`image_picker`)
3. `POST /runs/{id}/deliver` as multipart (`otp`, `pod_photo`) or JSON `{otp}` when no photo

## Customer ETA

On an active run detail screen, riders tap **15 / 25 / 40 / 60 min** chips. That calls `POST /runs/{id}/eta` and corporate track shows the updated `eta_label`.

## Architecture

- **UI:** Flutter + Material 3, Middo brand tokens, Plus Jakarta Sans
- **Routing:** `go_router` bottom-tab shell (Home · Runs · Boxes · Cash · More) + stack routes
- **Auth:** Sanctum bearer (`AuthStore` + `shared_preferences`)
- **Data:** `ApiDeliveryRepository` → `/api/delivery/*` (`USE_MOCK` fallback)
- **Push:** `PushNotificationService` → `POST/DELETE /device-tokens` (deep link via `path` / `deep_link`)
- **Deep links:** `middo-delivery://`


## Sync conflicts (offline queue)

When a queued mutation fails with a permanent 4xx (except 408/429), it moves to a **failed** list instead of being dropped silently. The network banner shows **sync conflicts — tap to review** with Retry / Discard actions.

## Play signing checklist

1. Generate an upload keystore (never commit it):
   `keytool -genkey -v -keystore upload-keystore.jks -keyalg RSA -keysize 2048 -validity 10000 -alias middo-delivery`
2. Copy `android/key.properties.example` → `android/key.properties` and fill store/key passwords.
3. Build: `flutter build appbundle --release --dart-define=API_BASE_URL=https://x.middo.com.bd`
4. Upload the AAB from CI or local `build/app/outputs/bundle/release/`; keep large APK/AAB out of git (Git LFS or release artifacts).

## FCM payload (production)

Staff alert pushes include:

- `path` — app route (e.g. `/runs/123`, `/boxes`, `/alerts`)
- `deep_link` — `middo-delivery://runs/123`
- `type`, `alert_id`, `alert_type`, `order_id`

Wire Firebase with a production `google-services.json` for `com.middo.delivery` before Play rollout.
