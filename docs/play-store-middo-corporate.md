# Google Play — Middo (corporate app)

Package: `com.middo.corporates` · Display name: **Middo** · Current version: **1.0.3 (4)**

Upload artifact: `mobile/corporate/releases/middo-release.aab`

Privacy policy: https://x.middo.com.bd/privacy  
Terms: https://x.middo.com.bd/terms

---

## Store listing (copy-paste)

### App name
```
Middo
```

### Short description (80 chars max)
```
Office lunch ordering in Dhaka — browse menus, schedule meals, track delivery.
```
*(79 characters)*

### Full description
```
Middo brings fresh, curated local meals straight to your office desk in Dhaka.

Lunch you actually look forward to — without the hassle of coordinating orders, chasing riders, or guessing what’s for lunch.

WHAT YOU CAN DO
• Browse daily menus from partner kitchens
• Schedule meals for multiple dates in one checkout
• Top up Middo Balance and pay securely
• Track orders from kitchen prep to desk-side delivery
• View scheduled and past orders
• Open support threads on any order
• Get push alerts when order status changes

HOW IT WORKS
1. Discover & select — browse menus tailored for corporate teams
2. Schedule & order — book your week or month; fund Middo Balance when needed
3. Seamless delivery — meals arrive in thermal Middo Boxes with traceability
4. Track & support — follow status in the app and contact Middo if you need help

THE MIDDO DIFFERENCE
• Zero hidden fees — pay for the meal, not surprise platform charges
• Daily variety — rotating menus so lunch stays interesting
• Diet-friendly options — portions that fit wellness goals
• Guaranteed freshness — quality-inspected meals, cooked fresh and on time

WHO IT’S FOR
Corporate buyers ordering workplace lunch in Bangladesh. Sign in with your registered mobile number.

Questions? Visit https://x.middo.com.bd or use in-app support on an order.
```

### Category
**Food & Drink**

### Contact email
Use the same support email shown on https://x.middo.com.bd/contact (Play Console requires a reachable address).

### Website
```
https://x.middo.com.bd
```

### Graphics checklist
| Asset | Size | Source |
|-------|------|--------|
| App icon | 512×512 PNG | `public/img/settings/logo.png` (resize; no transparency for Play) |
| Feature graphic | 1024×500 JPG/PNG | Brand banner — create in Figma/Canva if not ready |
| Phone screenshots | Min 2, 1080×1920 recommended | Capture from app: login, menu, checkout, track |

---

## Play Console walkthrough

Policy forms appear **after** you create the app. If you only see “Create app”, you have not created this listing yet (or you are on the account home screen).

### Step 1 — Create the app
1. [Play Console](https://play.google.com/console) → **Create app**
2. **App name:** Middo
3. **Default language:** English (United States) or English — add Bengali later if needed
4. **App or game:** App
5. **Free or paid:** Free
6. Declarations: confirm policies; create app

### Step 2 — Set up the app (left sidebar → **Policy and programs** / **App content**)

Complete every section until no red errors remain:

| Section | What to choose |
|---------|----------------|
| **App access** | “All functionality is available without special access” *or* “Some functionality is restricted” → explain corporate login is required (provide test mobile + password in notes) |
| **Ads** | No, my app does not contain ads |
| **Content rating** | Start questionnaire → category **Utility, Productivity, Communication, or Other** → answer No to violence, sexual content, drugs, gambling, etc. → typically **Everyone** or low teen |
| **Target audience** | 18 and over (office/corporate buyers) |
| **News app** | No |
| **COVID-19 contact tracing / Data safety** | See Data safety below |
| **Government apps** | No |
| **Financial features** | Yes — in-app wallet / balance top-up (not a bank; prepaid balance for meals) |

### Step 3 — Data safety form

Declare data **collected** and **shared** (with kitchens/riders/ops for fulfillment only):

| Data type | Collected | Shared | Purpose |
|-----------|-----------|--------|---------|
| Name | Yes | Yes (fulfillment) | Account, delivery |
| Email address | Optional | No | Account |
| Phone number | Yes | Yes (SMS/ops) | Login, OTP, delivery contact |
| Address | Yes | Yes (rider) | Delivery |
| App activity (orders) | Yes | Yes (kitchen/rider) | Core service |
| Device or other IDs (FCM token) | Yes | No (Firebase only) | Push notifications |
| Financial info (wallet top-up) | Yes | Payment partner | Payments |

- **Data encrypted in transit:** Yes (HTTPS)
- **Users can request deletion:** Yes (via Contact / support)
- **Data not sold**

Privacy policy URL: `https://x.middo.com.bd/privacy`

### Step 4 — Store listing
**Grow → Store presence → Main store listing**

Paste short + full description above, upload icon (512), feature graphic, screenshots, contact email.

### Step 5 — Upload the bundle
**Release → Testing → Internal testing** (recommended first)

1. **Create new release**
2. Upload `middo-release.aab`
3. Release name: `1.0.3`
4. Release notes: `Initial Play release — corporate lunch ordering, scheduling, tracking, wallet, push notifications.`
5. **Review release** → roll out to internal testers

Add yourself (and QA emails) under **Testers** tab.

### Step 6 — Play App Signing
On first upload, accept **Google Play App Signing**. Google holds the app signing key; your **upload keystore** (generated separately) signs bundles before upload. Back up the upload keystore — losing it requires a Google upload-key reset.

### Step 7 — Production (when ready)
After internal testing passes: **Release → Production → Create new release** → promote the tested AAB or upload a new version with incremented `versionCode` in `pubspec.yaml` (the `+4` in `1.0.3+4`).

---

## Test credentials for Play reviewers

If **App access** asks for login:

- Mobile: `01310123452` (or your production test corporate account)
- Password: *(provide a dedicated staging/production test account — do not use shared admin passwords in Play Console)*

---

## Rebuild signed AAB (future releases)

```bash
cd mobile/corporate/android
# Ensure upload-keystore.jks + key.properties exist (never commit)
cd ..
flutter pub get
flutter build appbundle --release --dart-define=API_BASE_URL=https://x.middo.com.bd
cp build/app/outputs/bundle/release/app-release.aab releases/middo-release.aab
```

Bump `version:` in `pubspec.yaml` before each Play upload (`1.0.3+4` → name `1.0.3`, code `4`).
