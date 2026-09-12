# Middo Operation (Flutter)

Field-pulse Android client for Middo **operation** staff.

## Status

Phase 0 backend API is live under `/api/operation` (auth, me, dashboard, alerts, device tokens).

Flutter scaffold is **not** started yet — copy structure from `mobile/kitchen/` or `mobile/delivery/` when Phase 1 begins.

## Docs

- Plan: `docs/operation-mobile-plan.json`
- API contract: `docs/operation-mobile-api-contract.md`

## Target IA

**Home · Boxes · Riders · Cash · More**

## Auth

```bash
curl -s -X POST "$API/api/operation/login" \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  -d '{"mobile":"01310123451","password":"…","device_name":"ops-pixel"}'
```
