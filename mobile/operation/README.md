# Middo Operation (Flutter)

Field-pulse Android/iOS app for ops during lunch peak.

Bottom nav: **Home · Boxes · Riders · Cash · More**

Package: `com.middo.operation` · version `0.3.0+4`

- Plan: [`docs/operation-mobile-plan.json`](../../docs/operation-mobile-plan.json)
- Contract: [`docs/operation-mobile-api-contract.md`](../../docs/operation-mobile-api-contract.md)

## Screens (pilot)

| Area | Status |
|------|--------|
| Login / splash / logout | Wired (Sanctum bearer) |
| Home | Serial boards: packages / orders / grouping / cash / boxes / complaints; alerts via nav bell |
| Home (legacy note) | Dashboard tiles with deep-links + Orders/Alerts/SLA chips |
| Alerts | List, mark read, mark all read |
| Boxes | QR paste + **camera scan**, assign request, reassign, ack return |
| Riders | Awaiting assign/reassign, custom-run create/cancel |
| Cash | Accept + reject-propose Middo Due handovers |
| SLA | Unassigned groups → assign kitchen |
| Orders | Search + detail with force-cancel / release rider |
| Complaints | List (More) + detail reply/complete |
| Offline queue | Enqueue stub + **Flush** on More |
| FCM | Firebase Messaging wired (`google-services.json` + token sync)

Desk catalog / packages / deep finance stay on web.

## Run (local Laravel)

```bash
php artisan serve --host=127.0.0.1 --port=8001
cd mobile/operation
flutter pub get
flutter run --dart-define=API_BASE_URL=http://127.0.0.1:8001
```

Android emulator: `http://10.0.2.2:8001`.

Seeded operation login:

- Mobile: `01310123455`
- Password: `12345678`

## Sideload / release (P1.8)

```bash
# Debug sideload for ops pilot phones
flutter build apk --debug \
  --dart-define=API_BASE_URL=https://x.middo.com.bd

# Release APK / AAB (requires android/key.properties)
flutter build apk --release \
  --android-skip-build-dependency-validation \
  --dart-define=API_BASE_URL=https://x.middo.com.bd
flutter build appbundle --release \
  --android-skip-build-dependency-validation \
  --dart-define=API_BASE_URL=https://x.middo.com.bd
```

Without `android/key.properties`, **release builds fail** (avoids Play debug-signed rejects).

```bash
cd mobile/operation/android
keytool -genkey -v -keystore upload-keystore.jks -keyalg RSA -keysize 2048 -validity 10000 -alias middo-operation
cp key.properties.example key.properties
# Edit store/key passwords
```

## FCM

`android/app/google-services.json` is present for `com.middo.operation` (Firebase project `middo-55888`).
`PushNotificationService` initializes Firebase Messaging, requests notification permission, and registers the FCM token via `POST /device-tokens` after login. Works on sideloaded APKs (Play listing not required).

## Play Console (P3.3 — parked)

1. Create listing for `com.middo.operation` (closed testing first).
2. Upload release-signed AAB.
3. Invite ops pilot emails.

## Architecture

- Flutter + Material 3, Middo tokens, Plus Jakarta Sans
- `go_router` shell + full-screen stack (alerts, SLA, orders, complaints, QR)
- Sanctum bearer (`AuthStore`)
- `OperationRepository` → `/api/operation/*`
- `OfflineMutationQueue` FIFO flush
- `mobile_scanner` for box QR

## Home boards (feedback)

Home is a date-scoped serial board (packages, orders, grouping with payment badges, rider cash, box requests, complaints). Alerts live on the top-right notification icon.
