# Middo Operation (Flutter)

Field-pulse Android client for Middo **operation** staff.

## Status

Backend API **Phase 0–2** is live under `/api/operation`:

- Auth / me / dashboard / alerts / device tokens
- Boxes, riders board, Middo cash handovers, SLA kitchen assign
- Complaints inbox, ops-day checklist, order search/show/force-cancel/release-rider
- Custom run create/cancel

Flutter UI scaffold is **deferred** — copy `mobile/kitchen/` or `mobile/delivery/` when starting the client. Keep web for packages/catalog/deep finance.

## Docs

- Plan: `docs/operation-mobile-plan.json`
- API contract: `docs/operation-mobile-api-contract.md`

## Target IA

**Home · Boxes · Riders · Cash · More**

## Auth

```bash
curl -sk -X POST "$API/api/operation/login" \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  -d '{"mobile":"01310123451","password":"…","device_name":"ops-pixel"}'
```
