# Google Play — Middo Kitchen

Package: `com.middo.kitchen` · Display name: **Middo Kitchen** · Current version: **0.3.1 (13)**

Upload artifact: `mobile/kitchen/releases/middo-kitchen-release.aab`

Privacy policy: https://x.middo.com.bd/privacy  
Terms: https://x.middo.com.bd/terms

---

## Store listing (copy-paste)

### App name
```
Middo Kitchen
```

### Short description (80 chars max)
```
Partner kitchen app — prep lunch orders, pack Middo Boxes, hand off to riders.
```

### Full description
```
Middo Kitchen is the partner cook app for Middo’s corporate lunch network in Dhaka.

Accept and prep grouped lunch orders, track packing, manage Middo Boxes, and hand off runs to delivery riders — all from one staff phone.

WHAT YOU CAN DO
• See today’s order groups and prep queues
• Mark meals ready and pack Middo Boxes
• Hand off kitchen→ops / rider runs
• Track box custody and stock requests
• View account / commission summaries
• Get push alerts for new work

WHO IT’S FOR
Partner kitchen staff on Middo. Sign in with your registered kitchen mobile number.

Questions? Visit https://x.middo.com.bd or contact Middo ops.
```

### Category
**Business** (or Food & Drink)

### Contact / website
Same support email as https://x.middo.com.bd/contact · Website: `https://x.middo.com.bd`

### Graphics checklist
| Asset | Size | Notes |
|-------|------|--------|
| App icon | 512×512 PNG | `mobile/kitchen/play-store/app-icon-512.png` (no transparency) |
| Feature graphic | 1024×500 PNG | `mobile/kitchen/play-store/feature-graphic-1024x500.png` |
| Phone screenshots | Min 2 | Home, prep queue, boxes, handoff |

---

## Upload keystore (one-time)

```bash
cd mobile/kitchen/android
keytool -genkey -v -keystore upload-keystore.jks -keyalg RSA -keysize 2048 -validity 10000 -alias middo-kitchen
cp key.properties.example key.properties
# Fill store/key passwords — never commit these files
```

On first Play upload, accept **Google Play App Signing**. Back up `upload-keystore.jks`.

---

## Rebuild signed AAB

```bash
cd mobile/kitchen/android
# Ensure upload-keystore.jks + key.properties exist (never commit)
cd ..
flutter pub get
flutter build appbundle --release \
  --android-skip-build-dependency-validation \
  --dart-define=API_BASE_URL=https://x.middo.com.bd
cp build/app/outputs/bundle/release/app-release.aab releases/middo-kitchen-release.aab
```

Bump `version:` in `pubspec.yaml` before each Play upload (`0.3.1+13` → name `0.3.1`, code `13`).

Upload only AABs signed with the kitchen upload keystore (`CN=Middo Kitchen`). Debug-signed builds are rejected by Play.

---

## Advertising ID (Android 13 / API 33+)

Play Console → **Policy and programs** → **App content** → **Advertising ID**:

1. Declare that the app **uses** an advertising ID (Firebase Analytics / Play services may read it; Kitchen has **no ads**).
2. Purpose: Analytics / App functionality (not advertising / remarketing).
3. Manifest already includes `com.google.android.gms.permission.AD_ID` (same as Middo Corporate).

If you instead declare “No”, you must remove the AD_ID permission and ensure Analytics does not collect it — keep the permission + “Yes” path to match Corporate.

---

## Test credentials for Play reviewers

- Mobile: kitchen seed user (e.g. from `database/seeders`)  
- Password: dedicated test account only — never share admin passwords in Play Console

---

## Policy / Data safety (high level)

Collected: account credentials, device IDs (FCM), possibly approximate location if enabled for ops.  
Purpose: app functionality, push notifications, fraud prevention.  
Privacy policy URL: `https://x.middo.com.bd/privacy`
