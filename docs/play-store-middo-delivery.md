# Google Play — Middo Delivery (Rider)

Package: `com.middo.delivery` · Display name: **Middo Delivery** (store can show **Middo Rider**) · Current version: **0.3.3 (13)**

Upload artifact: `mobile/delivery/releases/middo-delivery-release.aab`

Privacy policy: https://x.middo.com.bd/privacy  
Terms: https://x.middo.com.bd/terms

---

## Store listing (copy-paste)

> **Play Metadata policy:** Title and short description must not match. Do **not** paste `Middo Delivery` into Short description.

### App name (title)
```
Middo Delivery
```

### Short description (80 chars max — must differ from title)
```
Pick up lunch runs, deliver to offices, collect cash, and manage Middo Boxes.
```

### Full description
```
Rider staff app for Middo’s corporate lunch network in Dhaka.

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
| App icon | 512×512 PNG | `mobile/delivery/play-store/app-icon-512.png` (no transparency) |

> Delivery icon is the **reverse** of Kitchen: cream tile + forest-green courier bag + orange speed mark (Kitchen is forest tile + cream takeout).
| Feature graphic | 1024×500 PNG | `mobile/delivery/play-store/feature-graphic-1024x500.png` |
| Phone screenshots | Min 2 | Runs, deliver OTP, cash, boxes |

---

## Before you can send for review (Play Console forms)

These two **Policy** blockers are filled in Play Console — not fixed by uploading a new AAB.

Open the **Middo Delivery** app → left nav **Policy and programs** → **App content**. Complete every row until the dashboard shows no “Incomplete” errors.

### 1) Advertising ID (fixes “Incomplete advertising ID declaration”)

1. Open **App content** → **Advertising ID** → **Start** / **Manage**.
2. **Does your app use advertising ID?** → **Yes**  
   (Firebase Analytics / Google Play services may read it. Middo Delivery has **no ads**. The AAB already declares `com.google.android.gms.permission.AD_ID`.)
3. **Why does your app use advertising ID?** → check **Analytics** (and/or **App functionality** if shown).  
   Do **not** check advertising, remarketing, or ads personalization.
4. Save.

If you previously answered **No**, change it to **Yes** — a “No” answer conflicts with the `AD_ID` permission in the uploaded bundle.

### 2) Content ratings (fixes “Incomplete content ratings declaration”)

1. Open **App content** → **Content ratings** → **Start questionnaire**.
2. Enter email for IARC certificate → continue.
3. **Category:** **Utility, Productivity, Communication, or Other** (not Games).
4. Answer the questionnaire — for Middo Delivery use **No** for all of:
   - Violence / blood / gore  
   - Sexual content / nudity  
   - Controlled substances / drugs / alcohol / tobacco  
   - Gambling / contests for money  
   - User-to-user communication that is unmoderated chat (riders talk to ops via the product, not an open social network — if asked about user-generated content / chat, prefer the option that matches a closed workplace app, or **No** if the form only asks about unrestricted public UGC)  
   - Location sharing as a social feature (job routing for riders is operational; if forced to pick, declare location only as needed for app functionality, not “share my location publicly”)
5. Submit → apply the generated rating (expect **Everyone** / **PEGI 3** / equivalent) to the Delivery app store listing.
6. Save until **Content ratings** shows **Completed**.

### Also complete (same App content page)

| Section | Answer for Delivery |
|---------|---------------------|
| **Ads** | No, this app does not contain ads |
| **App access** | Some functionality is restricted → provide a **test rider mobile + password** for Google reviewers |
| **Target audience** | 18 and over (professional riders) |
| **News app** | No |
| **COVID-19 contact tracing / status apps** | No |
| **Data safety** | See section below — must be Completed |
| **Government apps** | No |
| **Financial features** | Yes if riders see earnings / withdrawals; describe as payroll/payouts for delivery work, not a consumer bank |

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

Bump `version:` in `pubspec.yaml` before each Play upload (`0.3.3+13` → name `0.3.3`, code `13`).

Upload only AABs signed with the delivery upload keystore (`CN=Middo Delivery`). Debug-signed builds are rejected by Play.

---

## Test credentials for Play reviewers

- Mobile: delivery seed user (e.g. from `database/seeders`)  
- Password: dedicated test account only — never share admin passwords in Play Console

---

## Policy / Data safety (high level)

Collected: account credentials, delivery addresses / receiver phone (for assigned runs), device IDs (FCM), financial/cash handover amounts, optional photos (POD).  
Purpose: app functionality, delivery operations, payouts, push notifications.  
Privacy policy URL: `https://x.middo.com.bd/privacy`
