# Kitchen Mobile API Contract

**Status:** Phase B foundation (2026-08-30)  
**Auth:** Sanctum bearer token  
**Base path:** `/api/kitchen`  
**Role gate:** `auth:sanctum` + `role:kitchen` (+ `permission:kitchen.*` on resource routes)  
**Reference client:** clone `mobile/corporate/` → future `mobile/kitchen/`

Screen IA maps to the kitchen PWA bottom nav: **Home · Orders · Groups · Prep · More**.

---

## Auth & account

| Method | Path | Permission | Screen |
|--------|------|------------|--------|
| `POST` | `/login` | public | Login |
| `POST` | `/logout` | auth | More |
| `GET` | `/me` | auth | Profile |
| `PATCH` | `/profile` | `kitchen.profile` | Profile |
| `POST` | `/change-password` | auth | Profile |
| `POST` | `/device-tokens` | auth | App bootstrap (FCM) |
| `DELETE` | `/device-tokens` | auth | Logout / unregister |

### `POST /login`

```json
{ "mobile": "01310123453", "password": "…", "device_name": "pixel-kitchen" }
```

Response `200`:

```json
{ "token": "…", "token_type": "Bearer", "user": { /* KitchenApiPresenter::user */ } }
```

Non-kitchen role → `403`. Inactive → `403`. Bad credentials → `422`.

---

## Home (Dashboard)

| Method | Path | Permission |
|--------|------|------------|
| `GET` | `/dashboard` | `kitchen.dashboard` |

Response tiles (counts + keys for deep links):

- `alerts`, `complaints`, `orders_this_month`, `orders_last_three_months`
- `preparing`, `ready_for_pickup`, `active_orders`, `claimable_groups`
- flags: `insufficient_box_stock`, `ops_incoming_notices[]`

---

## Alerts

| Method | Path | Permission |
|--------|------|------------|
| `GET` | `/alerts` | `kitchen.alerts` |
| `PATCH` | `/alerts/{id}/read` | `kitchen.alerts` |
| `POST` | `/alerts/read-all` | `kitchen.alerts` |

Query: `?page=1`. Response includes `unread_count` + paginated `alerts[]`.

---

## Groups (claim pool)

| Method | Path | Permission | Notes |
|--------|------|------------|-------|
| `GET` | `/order-groups` | `kitchen.order-groups` | Unassigned Middo pool |
| `POST` | `/order-groups/{id}/accept` | `kitchen.order-groups` | → `OrderGroupKitchenAssignment::accept` |
| `POST` | `/order-groups/{id}/decline` | `kitchen.order-groups` | body: `{ "reason": "…" }` |

Each group node includes privacy-safe orders (area, not customer address), accept window, capacity flags.

---

## Orders (active)

| Method | Path | Permission | Notes |
|--------|------|------------|-------|
| `GET` | `/orders/active` | `kitchen.orders` | Assigned groups with active orders |
| `GET` | `/orders/{id}` | `kitchen.orders` | Single order (kitchen-safe fields) |
| `POST` | `/orders/{id}/ready` | `kitchen.orders` | → `OrderKitchenActions::markReady` |
| `POST` | `/order-groups/{id}/ready` | `kitchen.orders` | Mark all processing orders ready |
| `POST` | `/order-groups/{id}/release` | `kitchen.orders` | → `release` |
| `POST` | `/order-groups/{id}/shortage` | `kitchen.orders` | body: `{ "reason": "…" }` |

**Not in Phase B (web still owns UI):** full dispatch modal / rider pairing — follow-up endpoint  
`POST /orders/{id}/dispatch` once `DispatchOrderModal` logic is extracted.

---

## Menus & prep (Phase B list + detail stub)

| Method | Path | Permission | Phase |
|--------|------|------------|-------|
| `GET` | `/menus/today` | `kitchen.menus` | B |
| `GET` | `/menus/{id}` | `kitchen.menus` | B |
| `GET` | `/prep/shopping-list` | `kitchen.prep` | follow-up |

---

## Boxes

| Method | Path | Permission | Phase |
|--------|------|------------|-------|
| `GET` | `/boxes/at-kitchen` | `kitchen.boxes` | B (list) |
| `GET` | `/boxes/incoming` | `kitchen.boxes` | follow-up |
| `POST` | `/boxes/{id}/receive` | `kitchen.boxes` | follow-up |
| `POST` | `/boxes/{id}/damage` | `kitchen.boxes` | follow-up |
| `POST` | `/boxes/request` | `kitchen.boxes` | follow-up |

---

## Money & complaints (follow-up)

| Method | Path | Permission |
|--------|------|------------|
| `GET` | `/account` | `kitchen.account` |
| `POST` | `/account/withdraw` | `kitchen.account` |
| `POST` | `/account/transfer-to-middo` | `kitchen.account` |
| `GET` | `/cash-handovers` | `kitchen.cash-handovers` |
| `POST` | `/cash-handovers/{id}/accept` | `kitchen.cash-handovers` |
| `GET` | `/complaints` | `kitchen.complaints` |
| `GET` | `/complaints/{id}` | `kitchen.complaints` |

---

## Privacy rules (must keep)

Kitchen API **must not** expose corporate customer name, street address, or receiver PII on order payloads — same as web (`FormatsOrderGroups` / kitchen-safe Excel export). Use area name + order id + menu + qty + status.

---

## Push (Phase D)

Device token registration exists in Phase B. FCM dispatch for `StaffAlerts` kitchen types is **not** wired yet (reuse `FcmClient` + `DeviceToken`).

---

## Implemented in this PR

Auth, me/profile/password, device tokens, dashboard, alerts, order-groups (list/accept/decline), orders/active + mark ready/group ready/release/shortage, menus/today + show, boxes/at-kitchen list.
