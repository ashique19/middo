# Middo Kitchen (Flutter)

Android/iOS kitchen partner app — claim groups, cook/dispatch orders, prep shopping list, boxes, account/cash, complaints.

Maps to kitchen PWA IA: **Home · Orders · Groups · Prep · More**.

API contract: [`docs/kitchen-mobile-api-contract.md`](../../docs/kitchen-mobile-api-contract.md)

## Screens

| Area | Status |
|------|--------|
| Login / splash / logout | Wired + FCM token sync |
| Home | Dashboard tiles (tappable) + alerts preview + low-box banner |
| Alerts | List, mark read / mark all read |
| Orders | Active groups: mark ready, release, shortage; per-order ready + dispatch |
| Order detail / dispatch | Detail + box multi-select dispatch |
| Groups | Claim pool: accept / decline + capacity |
| Prep | Today menus (tap → detail) + searchable shopping list |
| Boxes | In-stock (warehouse / damaged) + incoming receive + request |
| Account & cash | Receivable/payable, withdraw, pay Middo (proof photo), cash handovers |
| Complaints | List + thread detail |
| Profile | Edit details, weekly hours (read), change password |
| Order history | This / last / last-3-months via `/orders/history` |
| Offline / UX | Connectivity banner, skeletons, empty states, haptics, deep links |

## Production API

Default API root: `https://x.middo.com.bd` → `/api/kitchen`

### Run (local Laravel)

```bash
php artisan serve
cd mobile/kitchen
flutter pub get
flutter run \
  --dart-define=API_BASE_URL=http://127.0.0.1:8000
```

Android emulator: `http://10.0.2.2:8000`.

Seeded kitchen login:

- Mobile: `01310123453`
- Password: `12345678`

Offline mock:

```bash
flutter run --dart-define=USE_MOCK=true
```

### Build release APK / AAB

```bash
cd mobile/kitchen
flutter pub get
flutter build apk --release \
  --android-skip-build-dependency-validation \
  --dart-define=API_BASE_URL=https://x.middo.com.bd
flutter build appbundle --release \
  --android-skip-build-dependency-validation \
  --dart-define=API_BASE_URL=https://x.middo.com.bd
```

Published artifacts:

- APK: `mobile/kitchen/releases/middo-kitchen-release.apk`
- AAB: `mobile/kitchen/releases/middo-kitchen-release.aab`

### Release signing (Play Store / production)

Without `android/key.properties`, **release builds fail** (so Play never gets a debug-signed AAB). Use `flutter build apk --debug` for sideload tests.

One-time setup on a machine with JDK `keytool`:

```bash
cd mobile/kitchen/android
keytool -genkey -v -keystore upload-keystore.jks -keyalg RSA -keysize 2048 -validity 10000 -alias middo-kitchen
cp key.properties.example key.properties
# Edit key.properties with your store/key passwords
```

`keyAlias` must be `middo-kitchen` (see `android/key.properties.example`).

Keep `upload-keystore.jks` and `key.properties` out of git (already gitignored). Losing the keystore blocks Play Store updates for the same app listing.

Play Console: create an app for package `com.middo.kitchen`, upload the AAB, complete store listing / content ratings / target audience forms. See `docs/play-store-middo-kitchen.md`.

### Firebase

`android/app/google-services.json` is present for `com.middo.kitchen` (Firebase project `middo-55888`). Push init no-ops gracefully if Firebase is unavailable.

Requires Flutter 3.32+.

## Architecture

- **UI:** Flutter + Material 3, Middo brand tokens, Plus Jakarta Sans
- **Routing:** `go_router` bottom-tab shell + full-screen stack routes
- **Auth:** Sanctum bearer (`AuthStore` + `shared_preferences`)
- **Data:** `ApiKitchenRepository` → `/api/kitchen/*` (`USE_MOCK` fallback)
- **Push:** `PushNotificationService` → `POST/DELETE /device-tokens`
