# Google Play — Middo Delivery (Rider)

Package: `com.middo.delivery` · Display name: **Middo Delivery** (store can show **Middo Rider**) · Current version: **0.3.2 (8)**

Upload artifact: `mobile/delivery/releases/middo-delivery-release.aab`

Privacy policy: https://x.middo.com.bd/privacy  
Terms: https://x.middo.com.bd/terms

---

## Store listing (copy-paste)

### App name
```
Middo Delivery
```

### Short description (80 chars max)
```
Rider app for Middo — pick up lunch runs, deliver, collect cash, hand over Due.
```

### Full description
```
Middo Delivery is the rider app for Middo’s corporate lunch network in Dhaka.

Pick up packed lunch runs from kitchens, deliver to offices, collect cash, hand over Due to Middo, and manage Middo Box returns — with offline-safe actions and push alerts.

WHAT YOU CAN DO
• View assigned lunch runs and custom runs
• Confirm pickup and deliver with OTP / proof of delivery
• Set customer ETA for corporate track
• Collect cash and send online pay links
• Hand over Due cash to Middo / kitchen
• Accept and hand Middo Boxes (warehouse / kitchen / ops)
• Withdraw earnings after payout profile is complete
• Work offline — safe actions sync when back online

WHO IT’S FOR
Middo delivery riders. Sign in with your registered rider mobile number.

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
| Phone screenshots | Min 2 | Runs, deliver OTP, cash, boxes |

---

## Upload keystore (one-time)

```bash
cd mobile/delivery/android
keytool -genkey -v -keystore upload-keystore.jks -keyalg RSA -keysize 2048 -validity 10000 -alias middo-delivery
cp key.properties.example key.properties
# Fill store/key passwords — never commit these files
```

On first Play upload, accept **Google Play App Signing**. Back up `upload-keystore.jks`.

---

## Rebuild signed AAB

```bash
cd mobile/delivery/android
# Ensure upload-keystore.jks + key.properties exist (never commit)
cd ..
flutter pub get
flutter build appbundle --release \
  --android-skip-build-dependency-validation \
  --dart-define=API_BASE_URL=https://x.middo.com.bd
cp build/app/outputs/bundle/release/app-release.aab releases/middo-delivery-release.aab
```

Bump `version:` in `pubspec.yaml` before each Play upload (`0.3.2+8` → name `0.3.2`, code `8`).

Upload only AABs signed with the delivery upload keystore (`CN=Middo Delivery`). Debug-signed builds are rejected by Play.

---

## Advertising ID (Android 13 / API 33+)

Play Console → **Policy and programs** → **App content** → **Advertising ID**:

1. Declare that the app **uses** an advertising ID (Firebase Analytics / Play services may read it; Delivery has **no ads**).
2. Purpose: Analytics / App functionality (not advertising / remarketing).
3. Manifest already includes `com.google.android.gms.permission.AD_ID` (same as Middo Corporate).

If you instead declare “No”, you must remove the AD_ID permission and ensure Analytics does not collect it — keep the permission + “Yes” path to match Corporate.

---

## Test credentials for Play reviewers

- Mobile: delivery seed user (e.g. from `database/seeders`)  
- Password: dedicated test account only — never share admin passwords in Play Console

---

## Policy / Data safety (high level)

Collected: account credentials, delivery addresses / receiver phone (for assigned runs), device IDs (FCM), financial/cash handover amounts, optional photos (POD).  
Purpose: app functionality, delivery operations, payouts, push notifications.  
Privacy policy URL: `https://x.middo.com.bd/privacy`
