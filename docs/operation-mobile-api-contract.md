# Operation Mobile API Contract

**Status:** Phase 0–3 API live (incl. QR box lookup) (auth, home, boxes, riders, cash, SLA, complaints, ops-day, orders) — 2026-09-12  
**Auth:** Sanctum bearer token  
**Base path:** `/api/operation`  
**Role gate:** `auth:sanctum` + `role:operation` (+ `permission:operation.*` on resource routes)  
**Plan:** `docs/operation-mobile-plan.json`  
**Reference clients:** `mobile/kitchen/`, `mobile/delivery/` (Flutter patterns to copy)  
**Client:** `mobile/operation/` (Flutter pilot — read + mutations wired)

Screen IA (target): **Home · Boxes · Riders · Cash · More**.

---

## Auth & account

| Method | Path | Permission | Notes |
|--------|------|------------|-------|
| `POST` | `/login` | public | `{mobile, password, device_name?}` → `{token, token_type, user}` |
| `POST` | `/logout` | auth | Revokes current token |
| `GET` | `/me` | auth | `{user}` |
| `POST` | `/change-password` | auth | `{current_password, password, password_confirmation}` |
| `POST` | `/device-tokens` | auth | `{token, platform?, device_name?}` FCM |
| `DELETE` | `/device-tokens` | auth | `{token}` |

Non-operation role → `403` (`Login as Operation to continue.`). Inactive → `403`. Bad credentials → `422`.

### `POST /login`

```json
{ "mobile": "01310123451", "password": "…", "device_name": "pixel-ops" }
```

Response `200`:

```json
{ "token": "…", "token_type": "Bearer", "user": { /* OperationApiPresenter::user */ } }
```

---

## Home (Dashboard)

| Method | Path | Permission |
|--------|------|------------|
| `GET` | `/dashboard` | `operation.dashboard` |

Response (Phase 0):

```json
{
  "tiles": [
    {"key": "alerts", "label": "Alerts", "count": 0},
    {"key": "sla", "label": "Dispatch SLA", "count": 0},
    {"key": "awaiting_rider", "label": "Packed · awaiting rider", "count": 0},
    {"key": "box_requests", "label": "Box requests", "count": 0},
    {"key": "cash_handovers", "label": "Middo cash handovers", "count": 0},
    {"key": "complaints", "label": "Open complaints", "count": 0}
  ],
  "today": {"date": "2026-09-12", "label": "Fri, Sep 12", "orders": 0, "qty": 0},
  "tomorrow": {"date": "2026-09-13", "label": "Sat, Sep 13", "orders": 0, "qty": 0},
  "attention": [],
  "money": {
    "pending_middo_handovers": 0,
    "pending_middo_handover_amount": 0
  }
}
```

Tiles deep-link keys map to future Flutter routes (`middo-operation://…`).

---

## Alerts

| Method | Path | Permission |
|--------|------|------------|
| `GET` | `/alerts` | `operation.alerts` |
| `PATCH` | `/alerts/{id}/read` | `operation.alerts` |
| `POST` | `/alerts/read-all` | `operation.alerts` |

Response: `{unread_count, alerts:[], meta}`.

---

## Phase 1 (implemented)

### Boxes (`operation.boxes`)

| Method | Path | Notes |
|--------|------|-------|
| `GET` | `/boxes` |
| `GET` | `/boxes/lookup` | `?qr=` exact `MiddoBox.qr_code_id` → `{box}` or 404 | Custody list + filters |
| `GET` | `/boxes/requests` | Open kitchen box requests |
| `POST` | `/boxes/requests/{id}/assign` | Assign rider / stage |
| `POST` | `/boxes/{id}/reassign` | Reassign custody rider |
| `POST` | `/boxes/{id}/ack-return` | Ack return to warehouse |

### Riders (`operation.riders`)

| Method | Path | Notes |
|--------|------|-------|
| `GET` | `/riders/board` | Packed-awaiting-rider + on-shift riders |
| `POST` | `/orders/{id}/assign-rider` | Lunch assign |
| `POST` | `/orders/{id}/reassign-rider` | |
| `POST` | `/custom-runs/{id}/cancel` | |

### Cash (`operation.cash`)

| Method | Path | Notes |
|--------|------|-------|
| `GET` | `/cash-handovers` | Pending Middo Due |
| `POST` | `/cash-handovers/{id}/accept` | Ops day accept |
| `POST` | `/cash-handovers/{id}/reject` | Reject-propose (accounts confirms) |

### SLA (`operation.sla`)

| Method | Path | Notes |
|--------|------|-------|
| `GET` | `/sla` | Unassigned closed + late-to-pack |
| `POST` | `/order-groups/{id}/assign-kitchen` | |
| `POST` | `/order-groups/bulk-assign-kitchen` | `{group_ids, kitchen_id}` |

### Complaints (`operation.complaints`)

| Method | Path | Notes |
|--------|------|-------|
| `GET` | `/complaints` | Open inbox |
| `GET` | `/complaints/{id}` | |
| `POST` | `/complaints/{id}/reply` | `{body}` |
| `POST` | `/complaints/{id}/complete` | |

---

## Phase 2 (implemented — read/mutate; Flutter deferred)

| Method | Path | Permission |
|--------|------|------------|
| `GET` | `/ops-day` | `operation.dashboard` |
| `GET` | `/orders/{id}` | `operation.orders` |
| `POST` | `/orders/{id}/force-cancel` | `operation.orders` |
| `POST` | `/orders/{id}/release-rider` | `operation.orders` |
| `GET` | `/orders/search` | `operation.orders` |
| `POST` | `/custom-runs` | `operation.riders` |

---

## Permissions matrix

| Permission | Phase |
|------------|-------|
| `operation.dashboard` | P0 |
| `operation.alerts` | P0 |
| `operation.boxes` | P1 |
| `operation.riders` | P1 |
| `operation.cash` | P1 |
| `operation.sla` | P1 |
| `operation.complaints` | P1 |
| `operation.orders` | P2 |
| `operation.profile` | P0 (reserved) |

Synced via `OperationPermissions::syncOperationRole()`.

---

## Errors

| Code | When |
|------|------|
| `401` | Missing / invalid Sanctum token |
| `403` | Wrong role, inactive, or missing permission |
| `404` | Resource not found / not owned |
| `422` | Validation |

Audit source for `/api/operation/*`: `operation_mobile`.


## Phase 4 — Flutter mutation surfaces

Pilot screens call the Phase 1–2 mutation endpoints above:

- Boxes assign / reassign / ack-return
- Riders assign / reassign / custom-runs
- Cash accept / reject
- SLA assign-kitchen
- Complaints reply / complete
- Orders search / force-cancel / release-rider
- Alerts mark-read / read-all
