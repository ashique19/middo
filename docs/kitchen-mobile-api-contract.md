# Kitchen Mobile API Contract

**Status:** Phase B API + Phase D FCM + Phase E Flutter scaffold (2026-08-30)  
**Auth:** Sanctum bearer token  
**Base path:** `/api/kitchen`  
**Role gate:** `auth:sanctum` + `role:kitchen` (+ `permission:kitchen.*` on resource routes)  
**Reference client:** `mobile/kitchen/` (Flutter scaffold)

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

Response tiles + capacity flags (`insufficient_box_stock`, `ops_incoming_notices[]`).

---

## Alerts

| Method | Path | Permission |
|--------|------|------------|
| `GET` | `/alerts` | `kitchen.alerts` |
| `PATCH` | `/alerts/{id}/read` | `kitchen.alerts` |
| `POST` | `/alerts/read-all` | `kitchen.alerts` |

---

## Groups (claim pool)

| Method | Path | Permission |
|--------|------|------------|
| `GET` | `/order-groups` | `kitchen.order-groups` |
| `POST` | `/order-groups/{id}/accept` | `kitchen.order-groups` |
| `POST` | `/order-groups/{id}/decline` | `kitchen.order-groups` |

Decline body: `{ "reason": "…" }`.

---

## Orders (active + dispatch)

| Method | Path | Permission |
|--------|------|------------|
| `GET` | `/orders/active` | `kitchen.orders` |
| `GET` | `/orders/{id}` | `kitchen.orders` |
| `POST` | `/orders/{id}/ready` | `kitchen.orders` |
| `GET` | `/orders/{id}/dispatch-options` | `kitchen.orders` |
| `POST` | `/orders/{id}/dispatch` | `kitchen.orders` |
| `POST` | `/order-groups/{id}/ready` | `kitchen.orders` |
| `POST` | `/order-groups/{id}/release` | `kitchen.orders` |
| `POST` | `/order-groups/{id}/shortage` | `kitchen.orders` |

Dispatch body: `{ "box_ids": [1, 2, …] }` — must match order quantity. Uses `OrderKitchenDispatch`.

---

## Menus & prep

| Method | Path | Permission |
|--------|------|------------|
| `GET` | `/menus/today` | `kitchen.menus` |
| `GET` | `/menus/{id}` | `kitchen.menus` |
| `GET` | `/prep/shopping-list` | `kitchen.prep` |

Shopping list query: `?date=YYYY-MM-DD` (default today Dhaka).

---

## Boxes

| Method | Path | Permission |
|--------|------|------------|
| `GET` | `/boxes/at-kitchen` | `kitchen.boxes` |
| `GET` | `/boxes/incoming` | `kitchen.boxes` |
| `POST` | `/boxes/{id}/receive` | `kitchen.boxes` |
| `POST` | `/boxes/{id}/damage` | `kitchen.boxes` |
| `POST` | `/boxes/{id}/send-to-warehouse` | `kitchen.boxes` |
| `POST` | `/boxes/request` | `kitchen.boxes` |
| `POST` | `/boxes/requests/{id}/cancel` | `kitchen.boxes` |

Request body: `{ "quantity": 10, "note": "…" }`. Damage body: `{ "notes": "…" }`.

---

## Money & complaints

| Method | Path | Permission |
|--------|------|------------|
| `GET` | `/account` | `kitchen.account` |
| `POST` | `/account/withdraw` | `kitchen.account` |
| `POST` | `/account/transfer-to-middo` | `kitchen.account` |
| `GET` | `/cash-handovers` | `kitchen.cash-handovers` |
| `POST` | `/cash-handovers/{id}/accept` | `kitchen.cash-handovers` |
| `POST` | `/cash-handovers/{id}/reject` | `kitchen.cash-handovers` |
| `GET` | `/complaints` | `kitchen.complaints` |
| `GET` | `/complaints/{id}` | `kitchen.complaints` |

Transfer is multipart: `amount`, `proof` (image), optional `reference` / `notes`.

---

## Privacy rules (must keep)

Kitchen API **must not** expose corporate customer name, street address, or receiver PII on order payloads — same as web. Use area name + order id + menu + qty + status.

---

## Push (Phase D)

Device token registration: `POST/DELETE /device-tokens`.

When `StaffAlerts::createOnce()` inserts an alert, `SendStaffAlertPush` is queued. Payload data:

| Key | Value |
|-----|--------|
| `type` | `staff_alert` |
| `alert_id` | string id |
| `alert_type` | e.g. `group_assigned` |
| `order_group_id` | string or empty |

Android FCM channel: `middo_staff_alerts` (corporate order pushes keep `middo_orders`).

---

## Client (Phase E)

Flutter scaffold at `mobile/kitchen/` (`com.middo.kitchen`): login, Home / Orders / Groups / Prep / More shell, FCM token sync. Accept/dispatch/box/account actions are API-ready; deeper UI still TODO.
