# Middo Corporate (Flutter)

iOS & Android app for Middo’s **corporate** role — office lunch ordering, scheduling, tracking, wallet, and support.

## Screens

- Login
- Home dashboard
- Menu browse + filters
- Multi-date checkout
- Scheduled orders
- Order tracking timeline
- Order history
- Wallet / top-up
- Complaint / support chat

## Run

```bash
cd mobile/corporate
flutter pub get
flutter run
```

Requires [Flutter](https://docs.flutter.dev/get-started/install) 3.32+.

## Architecture

- **UI:** Flutter + Material 3, Middo brand tokens, Plus Jakarta Sans
- **Routing:** `go_router` with bottom-tab shell (Home / Menu / Schedule / Wallet)
- **Data:** `MockRepository` today — swap for Laravel Sanctum API client next

Design reference (HTML prototype): `/designs/corporate-mobile/`

## Next: wire Laravel API

Suggested endpoints (see `routes/api/corporate.php` in the monorepo):

- `POST /api/corporate/login`
- `GET  /api/corporate/dashboard`
- `GET  /api/corporate/menu`
- `POST /api/corporate/orders`
- `GET  /api/corporate/orders/scheduled`
- `GET  /api/corporate/orders/history`
- `GET  /api/corporate/orders/{id}/track`
- `GET|POST /api/corporate/orders/{id}/support`
- `POST /api/corporate/wallet/top-up`
