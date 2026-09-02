# Process-flow money E2E tests

Feature tests under `tests/Feature/Money/` walk Middo’s lunch order lifecycle and assert **checkout maths + role balances after every step**.

## Files

| File | Covers |
|------|--------|
| `tests/Support/MoneyFlowAssertions.php` | Shared oracles: role balances, tree summary, payables, event types, VAT breakdown helper |
| `tests/Feature/Money/OrderMoneyFlowCodProcessTest.php` | COD happy path + Middo handover + accounts settle |
| `tests/Feature/Money/OrderMoneyFlowPrepaidProcessTest.php` | Wallet prepaid path + accounts settle |

## Roles checked

| Role | Steps asserted |
|------|----------------|
| **Corporate** | Place order; `amountDue` / `amount_paid`; Middo Balance wallet |
| **Kitchen** | Mark ready; dispatch share credit; accept cash Due handover |
| **Operation** | Rider assign (via `LunchRunFlow`); accept Middo cash handover |
| **Delivery** | Pickup (run-start commission); deliver; COD cash collect; create handover |
| **Accounts** | Settle kitchen partner payable (Middo cash + kitchen wallet) |

## Oracle (default fixture)

Menu: price **৳200**, kitchen **৳50**, delivery **৳25**, qty **2**, food VAT **5%**.

```
food = 400
vat  = 19          # round(400/1.05)=381 → VAT 19
kitchen = 100
delivery = 50
middo_rest = 400 - 19 - 100 - 50 = 231
COD Due after cash = 400 - 50 = 350
```

## Run

```bash
php artisan test --filter='OrderMoneyFlowCodProcessTest|OrderMoneyFlowPrepaidProcessTest'
```
