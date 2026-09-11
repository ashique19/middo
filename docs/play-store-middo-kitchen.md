# Google Play — Middo Kitchen

Package: `com.middo.kitchen` · Display name: **Middo Kitchen** · Current version: **0.3.1 (15)**

Upload artifact: `mobile/kitchen/releases/middo-kitchen-release.aab`

Privacy policy: https://x.middo.com.bd/privacy  
Terms: https://x.middo.com.bd/terms

---

## Store listing (copy-paste)

> **Play Metadata policy:** Title, short description, and full description must **not** be the same (or nearly the same).  
> Do **not** paste `Middo Kitchen` into Short description. That triggers: *“Title matches description almost exactly.”*

### App name (title)
```
Middo Kitchen
```

### Short description (80 chars max — must differ from title)
```
Prep corporate lunches, pack Middo Boxes, and hand off runs to riders.
```

### Full description (must differ from title and short description)
```
Partner kitchen staff app for Middo’s corporate lunch network in Dhaka.

Accept and prep grouped lunch orders, track packing, manage Middo Boxes, and hand off runs to delivery riders — all from one staff phone.

WHAT YOU CAN DO
• See today’s order groups and prep queues
• Mark meals ready and pack Middo Boxes
• Hand off kitchen→ops / rider runs
• Track box custody and stock requests
• View account / commission summaries
• Get push alerts for new work

WHO IT’S FOR
Partner kitchen cooks and packers on Middo. Sign in with your registered kitchen mobile number.

Questions? Visit https://x.middo.com.bd or contact Middo ops.
```

### Fix in Play Console (no new AAB needed)
1. **Grow → Store presence → Main store listing**
2. **App name:** `Middo Kitchen`
3. **Short description:** paste the short text above (not the app name)
4. **Full description:** paste the full text above
5. Save → send for review again

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

Bump `version:` in `pubspec.yaml` before each Play upload (`0.3.1+15` → name `0.3.1`, code `15`).

Upload only AABs signed with the kitchen upload keystore (`CN=Middo Kitchen`). Debug-signed builds are rejected by Play.

---

## Before you can send for review (Play Console forms)

These **Policy** blockers are filled in Play Console — not fixed by uploading a new AAB.

Open the **Middo Kitchen** app → left nav **Policy and programs** → **App content**. Complete every row until nothing shows “Incomplete”.

### 1) Advertising ID (fixes “Incomplete advertising ID declaration”)

1. **App content** → **Advertising ID** → **Start** / **Manage**.
2. **Does your app use advertising ID?** → **Yes**  
   (Firebase Analytics / Play services may read it. Kitchen has **no ads**. AAB already has `com.google.android.gms.permission.AD_ID`.)
3. Purpose → **Analytics** (and/or **App functionality**). Do **not** select advertising / remarketing.
4. Save. If you previously answered **No**, switch to **Yes** (conflicts with the AD_ID permission otherwise).

### 2) Content ratings (fixes “Incomplete content ratings declaration”)

1. **App content** → **Content ratings** → **Start questionnaire**.
2. Enter email for the IARC certificate → continue.
3. Category: **Utility, Productivity, Communication, or Other** (not Games).
4. Answer **No** to violence, sexual content, drugs/alcohol/tobacco, gambling, and unrestricted public UGC.
5. Submit → apply rating (expect **Everyone** / PEGI 3) → save until status is **Completed**.

### Also complete on the same page

| Section | Answer for Kitchen |
|---------|---------------------|
| **Ads** | No ads |
| **App access** | Restricted — give Google a **test kitchen mobile + password** |
| **Target audience** | 18 and over |
| **News / Government / COVID apps** | No |
| **Data safety** | Must be Completed (account, FCM device IDs, ops data) |
| **Financial features** | Yes if kitchen sees Middo balance / transfers |

---

## Test credentials for Play reviewers

- Mobile: kitchen seed user (e.g. from `database/seeders`)  
- Password: dedicated test account only — never share admin passwords in Play Console

---

## Policy / Data safety (high level)

Collected: account credentials, device IDs (FCM), possibly approximate location if enabled for ops.  
Purpose: app functionality, push notifications, fraud prevention.  
Privacy policy URL: `https://x.middo.com.bd/privacy`
