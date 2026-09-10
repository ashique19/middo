# Middo Delivery (Flutter)

Android/iOS rider app — kitchen pickups, Middo box runs, cash collection, and wallet.

Maps to delivery PWA IA: **Home · Runs · Boxes · Cash · More**.

## Screens

| Area | Status |
|------|--------|
| Login / splash / logout | Wired + FCM token sync |
| Home | Dashboard tiles + shift chip (on / off / unable) |
| Runs | Active list with pickup / deliver + run detail |
| Boxes | Pending actions + bulk accept/hand |
| Cash | Delivered orders, collect cash, handovers |
| Account | Wallet balance + withdraw |
| Alerts | List, mark read / mark all read |
| Profile | Change password |
| More | Custom runs start/complete, history, account links |
| Offline / UX | Connectivity banner, skeletons, empty states, haptics, deep links |

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

Without `android/key.properties`, release builds are signed with the debug keystore (fine for sideload smoke). Play upload needs a real upload keystore.

### Firebase

Replace `android/app/google-services.json` with a Firebase Android app registered as `com.middo.delivery`. Until then, push init no-ops gracefully.

Requires Flutter 3.32+.

## Architecture

- **UI:** Flutter + Material 3, Middo brand tokens, Plus Jakarta Sans
- **Routing:** `go_router` bottom-tab shell (Home · Runs · Boxes · Cash · More) + stack routes
- **Auth:** Sanctum bearer (`AuthStore` + `shared_preferences`)
- **Data:** `ApiDeliveryRepository` → `/api/delivery/*` (`USE_MOCK` fallback)
- **Push:** `PushNotificationService` → `POST/DELETE /device-tokens`
- **Deep links:** `middo-delivery://`
