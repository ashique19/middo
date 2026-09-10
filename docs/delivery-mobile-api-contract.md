# Delivery Mobile API Contract

**Status:** Sanctum API for Flutter delivery client (`mobile/delivery/`)  
**Auth:** Sanctum bearer token  
**Base path:** `/api/delivery`  
**Role gate:** `auth:sanctum` + `role:delivery` (+ `permission:delivery.*` on resource routes)  
**Reference client:** `mobile/delivery/lib/data/delivery_repository.dart`

Screen IA mirrors the delivery PWA: **Home · Runs · Boxes · Cash · More**.

---

## Auth & account

| Method | Path | Permission | Notes |
|--------|------|------------|-------|
| `POST` | `/login` | public | `{mobile, password, device_name?}` → `{token, token_type, user}` |
| `POST` | `/logout` | auth | |
| `GET` | `/me` | auth | `{user, shift_status, can_accept_new_runs}` |
| `POST` | `/change-password` | auth | `{current_password, password, password_confirmation}` |
| `POST` | `/device-tokens` | auth | `{token, platform?, device_name?}` |
| `DELETE` | `/device-tokens` | auth | `{token}` |

Non-delivery role → `403` (`Login as Delivery to continue.`). Inactive → `403`. Bad credentials → `422`.

---

## Home (Dashboard + shift)

| Method | Path | Permission |
|--------|------|------------|
| `GET` | `/dashboard` | `delivery.dashboard` |
| `POST` | `/shift` | `delivery.dashboard` |

### `GET /dashboard`

```json
{
  "tiles": [
    {"key": "alerts", "label": "Alerts", "count": 0},
    {"key": "runs", "label": "Kitchen dispatches", "count": 0},
    {"key": "custom_runs", "label": "Custom runs", "count": 0},
    {"key": "boxes", "label": "Middo boxes pending", "count": 0},
    {"key": "delivered", "label": "Delivered orders", "count": 0},
    {"key": "cash", "label": "Cash on hand", "count": 0}
  ],
  "shift_status": "on",
  "shift_label": "On shift",
  "shift_options": {"on": "On shift", "off": "Off shift", "unable": "Unable to continue"},
  "can_accept_new_runs": true
}
```

### `POST /shift`

Body: `{ "status": "on" | "off" | "unable" }`.

---

## Alerts

| Method | Path | Permission |
|--------|------|------------|
| `GET` | `/alerts` | `delivery.alerts` |
| `PATCH` | `/alerts/{id}/read` | `delivery.alerts` |
| `PATCH` | `/alerts/read-all` | `delivery.alerts` |

Response: `{alerts:[], unread_count, meta}`.

---

## Lunch runs (ops-assigned only)

| Method | Path | Permission |
|--------|------|------------|
| `GET` | `/runs` | `delivery.runs` |
| `GET` | `/runs/{id}` | `delivery.runs` |
| `POST` | `/runs/{id}/pickup` | `delivery.runs` |
| `POST` | `/runs/{id}/send-delivery-otp` | `delivery.runs` | SMS OTP to receiver (`debug_otp` in local/testing) |
| `POST` | `/runs/{id}/deliver` | `delivery.runs` | JSON or multipart: `otp`, optional `pod_photo`; send `Idempotency-Key` |
| `GET` | `/runs/history?period=` | `delivery.runs` |

**Critical:** riders **never** first-claim lunch runs. Lists are scoped via `DeliveryAreaScope` to `delivery_rider_id = rider`.

Run payloads **include** party PII + street `address` + `can_pick_up` / `can_mark_delivered` (also aliased as `can_pickup` / `can_deliver` for the Flutter client).

History `period`: `this_month` | `last_month` | `last_3_months`.

---

## Middo boxes

| Method | Path | Permission |
|--------|------|------------|
| `GET` | `/boxes/pending` | `delivery.boxes` |
| `POST` | `/boxes/{id}/accept-warehouse` | `delivery.boxes` |
| `POST` | `/boxes/{id}/hand-to-kitchen` | `delivery.boxes` |
| `POST` | `/boxes/{id}/accept-kitchen-return` | `delivery.boxes` |
| `POST` | `/boxes/{id}/hand-to-ops` | `delivery.boxes` |
| `POST` | `/boxes/{id}/collect-empty` | `delivery.boxes` |
| `POST` | `/boxes/requests/{id}/accept-all` | `delivery.boxes` |
| `POST` | `/boxes/requests/{id}/hand-all` | `delivery.boxes` |

`GET /boxes/pending` → `{boxes:[], run_groups:[], requests:[]}` (`requests` aliases `run_groups` for Flutter).

**Critical:** riders **never** first-claim kitchen→ops (`can_claim_kitchen_return` is always false). Ops assigns; rider only accepts dispatched custody.

---

## Cash & delivered orders

| Method | Path | Permission |
|--------|------|------------|
| `GET` | `/orders/delivered` | `delivery.cash` |
| `POST` | `/orders/{id}/collect-cash` | `delivery.cash` |
| `GET` | `/cash-handovers` | `delivery.cash` |
| `POST` | `/cash-handovers` | `delivery.cash` |

### Collect cash

Body: `{ "cash_amount": 450, "short_reason": "…" }`  
Aliases accepted: `amount` → `cash_amount`, `notes` → `short_reason` (Flutter client).

Delivered-order payload includes `cash_due`, `commission_open`, `projected_commission`, `projected_due_to_middo`.

**Cash Due = collection − commission** (commission settled in-kind from float; residual Due stays on `users.balance` / `cash_due_to_middo`).

### Create handover

Body: `{ "order_ids": [1, 2], "target": "kitchen" | "middo", "notes": "…" }`  
Amount is derived from selected orders’ Due to Middo (not a free-form amount).

---

## Account

| Method | Path | Permission |
|--------|------|------------|
| `GET` | `/account` | `delivery.account` |
| `POST` | `/account/withdraw` | `delivery.account` |

Account also returns `statement[]` and `withdrawals[]`.

Withdraw body: `{ "notes"?, "payout_channel"? }`. Amount = full wallet receivable.  
**Blocked while `users.balance` (Due to Middo) > 0.**

---

## Custom runs

| Method | Path | Permission |
|--------|------|------------|
| `GET` | `/custom-runs` | `delivery.runs` |
| `POST` | `/custom-runs/{id}/start` | `delivery.runs` |
| `POST` | `/custom-runs/{id}/complete` | `delivery.runs` |

Start requires On shift (`can_accept_new_runs`). Commission books on start via `MiddoOperatingCosts`.

---

## Domain rules (must keep)

1. No rider first-claim for lunch or kitchen→ops.
2. Runs include customer address / receiver PII (opposite of kitchen privacy).
3. Lists scoped to assigned rider only (`DeliveryAreaScope`).
4. Cash Due = collection − commission; withdraw blocked while Due (`users.balance`) > 0.

---

## Implementation map

| Layer | Class |
|-------|--------|
| Permissions | `App\Support\DeliveryPermissions` |
| Presenter | `App\Support\DeliveryApiPresenter` |
| Actions | `App\Support\DeliveryMobileActions` |
| Controller | `App\Http\Controllers\Api\Delivery\DeliveryMobileController` |
| Routes | `routes/api/delivery.php` |
| Audit source | `UserAudit::SOURCE_DELIVERY_MOBILE` (`delivery_mobile`) |
| Feature test | `tests/Feature/Api/DeliveryMobileApiTest.php` |
