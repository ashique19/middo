# Middo Kitchen (Flutter)

Android/iOS scaffold for Middo’s **kitchen** role — claim groups, cook/dispatch orders, prep shopping list, boxes, and account.

Maps to kitchen PWA IA: **Home · Orders · Groups · Prep · More**.

API contract: [`docs/kitchen-mobile-api-contract.md`](../../docs/kitchen-mobile-api-contract.md)

## Screens (scaffold)

| Tab | Status |
|-----|--------|
| Login / splash | Wired to `POST /api/kitchen/login` |
| Home | Dashboard tiles + alerts preview |
| Orders | Active orders list |
| Groups | Claimable / assigned groups |
| Prep | Today menus + shopping-list stub |
| More | Profile, logout, placeholders for boxes/account |

FCM: registers device tokens after login; deep-links `staff_alert` → `/alerts` or `/groups`. Android channel id expected: `middo_staff_alerts`.

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

### Firebase

Replace `android/app/google-services.json` with a Firebase Android app registered as `com.middo.kitchen` (same Firebase project Middo uses for corporate is fine). Until then, push init no-ops gracefully.

Requires Flutter 3.32+.

## Architecture

- **UI:** Flutter + Material 3, Middo brand tokens, Plus Jakarta Sans
- **Routing:** `go_router` bottom-tab shell
- **Auth:** Sanctum bearer (`AuthStore` + `shared_preferences`)
- **Data:** `ApiKitchenRepository` → `/api/kitchen/*` (`USE_MOCK` fallback)
- **Push:** `PushNotificationService` → `POST/DELETE /device-tokens`
