# Google Play — Middo Kitchen

Package: `com.middo.kitchen` · Display name: **Middo Kitchen** · Current version: **0.3.1 (10)**

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
| App icon | 512×512 PNG | No transparency for Play |
| Feature graphic | 1024×500 | Brand banner |
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

Bump `version:` in `pubspec.yaml` before each Play upload (`0.3.1+10` → name `0.3.1`, code `10`).

**Do not upload AABs from `main` until this signing PR is merged** — older `releases/*.aab` on `main` were signed with the Android **debug** keystore and Play will reject them.

---

## Test credentials for Play reviewers

- Mobile: kitchen seed user (e.g. from `database/seeders`)  
- Password: dedicated test account only — never share admin passwords in Play Console

---

## Policy / Data safety (high level)

Collected: account credentials, device IDs (FCM), possibly approximate location if enabled for ops.  
Purpose: app functionality, push notifications, fraud prevention.  
Privacy policy URL: `https://x.middo.com.bd/privacy`
