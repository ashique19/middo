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
| Prep | Today menus + shopping list |
| Boxes | In-stock (warehouse / damaged) + incoming receive + request |
| Account & cash | Receivable/payable, withdraw, pay Middo (proof photo), cash handovers |
| Complaints | List + thread detail |
| Profile | Edit details + change password |

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

### Build release APK

```bash
cd mobile/kitchen
flutter pub get
flutter build apk --release \
  --dart-define=API_BASE_URL=https://x.middo.com.bd
```

Published APK: `mobile/kitchen/releases/middo-kitchen-release.apk`

### Firebase

Replace `android/app/google-services.json` with a Firebase Android app registered as `com.middo.kitchen`. Until then, push init no-ops gracefully.

Requires Flutter 3.32+.

## Architecture

- **UI:** Flutter + Material 3, Middo brand tokens, Plus Jakarta Sans
- **Routing:** `go_router` bottom-tab shell + full-screen stack routes
- **Auth:** Sanctum bearer (`AuthStore` + `shared_preferences`)
- **Data:** `ApiKitchenRepository` → `/api/kitchen/*` (`USE_MOCK` fallback)
- **Push:** `PushNotificationService` → `POST/DELETE /device-tokens`
