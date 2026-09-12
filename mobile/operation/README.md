# Middo Operation (Flutter)

Field-pulse Android/iOS app for ops during lunch peak: alerts, boxes, riders, Middo cash accept, SLA, complaints.

Bottom nav: **Home · Boxes · Riders · Cash · More**.

Desk catalog / packages / deep finance stay on web. API mirrors kitchen/delivery Sanctum pattern.

- Plan: [`docs/operation-mobile-plan.json`](../../docs/operation-mobile-plan.json)
- Contract: [`docs/operation-mobile-api-contract.md`](../../docs/operation-mobile-api-contract.md)

Package: `com.middo.operation` · app name Middo Operation · version `0.1.0+1`

## Screens (Phase 1–3 pilot)

| Area | Status |
|------|--------|
| Login / splash / logout | Wired (Sanctum bearer) |
| Home | Dashboard tiles |
| Boxes | Open kitchen requests + **QR text lookup** (`GET /boxes/lookup`) |
| Riders | Board counts + awaiting-accept queue |
| Cash | Pending Middo Due handovers + accept |
| More | Me, SLA, ops-day, complaints count, offline-queue stub |
| Push / FCM | Device-token API ready; Firebase wiring deferred |
| Camera QR scan | Deferred — paste/type QR for pilot |
| Offline mutation flush | Stub queue only (`OfflineMutationQueue`) |

## Production API

Default API root: `https://x.middo.com.bd` → `/api/operation`

### Run (local Laravel)

Prefer `npm run build` + HTTPS proxy for the web app; Flutter talks to the API directly:

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

### Build release APK / AAB

```bash
cd mobile/operation
flutter pub get
flutter build apk --release \
  --android-skip-build-dependency-validation \
  --dart-define=API_BASE_URL=https://x.middo.com.bd
flutter build appbundle --release \
  --android-skip-build-dependency-validation \
  --dart-define=API_BASE_URL=https://x.middo.com.bd
```

Without `android/key.properties`, **release builds fail** (avoids Play “debug-signed” rejects). Sideload with `flutter build apk --debug`.

One-time signing:

```bash
cd mobile/operation/android
keytool -genkey -v -keystore upload-keystore.jks -keyalg RSA -keysize 2048 -validity 10000 -alias middo-operation
cp key.properties.example key.properties
# Edit store/key passwords
```

Keep `upload-keystore.jks` and `key.properties` out of git.

### Play Console (Phase 3.3 — parked)

1. Create app listing for package `com.middo.operation` (internal / closed testing track first).
2. Upload a release-signed AAB from the commands above.
3. Complete store listing, content rating, and target audience forms.
4. Invite ops pilot emails on the closed testing track before production.

Firebase / FCM for ops push is intentionally out of this scaffold; register tokens via `POST /device-tokens` once a Firebase Android app is added.

## Architecture

- **UI:** Flutter + Material 3, Middo cream/forest/orange tokens, Plus Jakarta Sans
- **Routing:** `go_router` bottom-tab shell
- **Auth:** Sanctum bearer (`AuthStore` + `shared_preferences`)
- **Data:** `OperationRepository` → `/api/operation/*`
- **Offline:** `OfflineMutationQueue` stub (enqueue + count; flush next)
