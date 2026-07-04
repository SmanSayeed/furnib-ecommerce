# Steadfast Courier Integration

How order fulfilment works in furnib-ecommerce: booking a consignment with
**Steadfast** (packzy portal), auto-pushing confirmed orders, polling delivery
status (no webhook exists), and our **own fraud / return-ratio** signal built on
top. Designed to be provider-agnostic — a second courier can be added without
touching callers.

---

## 1. Players & files

| Player | Responsibility | Key files |
|---|---|---|
| Courier abstraction | Talk to a courier; faked in tests | `Support/Courier/CourierGateway.php` (interface), `SteadFastCourier.php` (real), `FakeCourierGateway.php` (tests) |
| Booking | Create/return the consignment for an order (idempotent) | `Actions/Shipments/CreateConsignment.php` |
| Auto-push | Book on order confirmed, off the request cycle | `Observers/OrderObserver.php`, `Jobs/PushOrderToCourier.php` |
| Status sync | Poll in-flight consignments (no webhook) | `Jobs/SyncCourierStatuses.php` (scheduled hourly) |
| Fraud signal | Return-ratio per customer phone | `Services/Courier/CustomerCourierStats.php` |
| Model / admin | Store consignment; manual ship/track buttons | `Models/Shipment.php`, `Http/Controllers/Admin/ShipmentController.php` |
| Credentials | Encrypted Api-Key / Secret-Key + auto_push flag | Admin → Settings → Integrations (Steadfast card) |

Binding: `CourierGateway => SteadFastCourier` in `RepositoryServiceProvider`.

---

## 2. Steadfast API (packzy portal)

Base `https://portal.packzy.com/api/v1`; auth headers `Api-Key`, `Secret-Key`,
`Content-Type: application/json`.

| Endpoint | Use |
|---|---|
| `POST /create_order` | Book a consignment: `invoice` (unique), `recipient_name/phone/address`, `cod_amount`, `note`. Returns `consignment_id`, `tracking_code`, `status`. |
| `POST /create_order/bulk-order` | Up to 500 consignments in one call (JSON array). |
| `GET /status_by_trackingcode/{code}` | Current `delivery_status` (also `/status_by_cid/{id}`, `/status_by_invoice/{invoice}`). |
| `GET /get_balance` | Current courier balance. |
| `POST /create_return_request`, `GET /get_return_request(s)` | Returns. |

`delivery_status` enum: `pending`, `in_review`, `delivered_approval_pending`,
`partial_delivered_approval_pending`, `delivered`, `partial_delivered`,
`cancelled_approval_pending`, `cancelled`, `hold`, `unknown`.

- **No webhook** — status must be **polled**.
- **No official fraud endpoint** — we build our own (see §5).
- No documented rate limit — poll conservatively (hourly here).

---

## 3. Architecture (provider-agnostic)

```
CourierGateway (interface): createConsignment(Shipment), getStatus(trackingCode)
  └── SteadFastCourier (impl)                         ← now
  └── PathaoCourier / RedxCourier (future impls)      ← no caller change
```

- Credentials in **encrypted settings** (`steadfast.api_key`, `steadfast.secret_key`),
  never in the repo or the client bundle — exactly like SSLCommerz.
- `Shipment` model: `order_id`, `courier`, `consignment_id`, `tracking_code`,
  `status`, `recipient_*`, `cod_amount` (integer paisa via MoneyCast),
  `raw_payload` (`encrypted:array`). Audit log records only non-sensitive fields.
- `CreateConsignment` is **idempotent per order** — once a `consignment_id`
  exists it is returned untouched, so retries/duplicate confirms never double-book.
- `cod_amount = max(0, total − advance_paid)` — always **server-computed**, so a
  partly-prepaid order collects only the remaining balance on delivery.

---

## 4. Auto-push on confirm + status polling

```
Admin confirms an order  (status: pending → confirmed)
  → OrderObserver::updated()
      fires ONLY when: status changed to `confirmed`
                       AND settings steadfast.auto_push is on (default true)
                       AND api_key + secret_key are configured
                       AND no consignment exists yet
  → PushOrderToCourier::dispatch(orderId)     (queued, unique per order, 5 tries)
      → CreateConsignment → SteadFastCourier::createConsignment
      → Shipment row filled with consignment_id + tracking_code + status
```

- The push is **queued** — it never blocks the admin action, and retries with
  backoff if the courier API is briefly down. Needs the **queue worker** running
  (see `SERVER-OPS-GUIDE.md` §"Background workers").
- **Opt-in by configuration:** an install without Steadfast creds behaves exactly
  as before (nothing is pushed). Turn it off explicitly with `steadfast.auto_push = false`.
- Manual control still exists: `POST /admin/orders/{order}/ship` and `/track`
  (guarded by `orders.manage`).

```
Hourly schedule: SyncCourierStatuses
  → for every Shipment with a tracking_code NOT in a terminal state
    (delivered / partial_delivered / cancelled / returned):
      SteadFastCourier::getStatus(tracking_code) → update Shipment.status
  → transport error on one shipment is logged and skipped, retried next hour
```

This keeps order tracking current **and** feeds the fraud stats below.

---

## 5. Our own fraud / return-ratio system (no official API)

Steadfast exposes no fraud check, so we build one from data we already own —
every consignment's final `delivery_status`, aggregated per **customer phone**
(`CustomerCourierStats::forPhone`, derived live from the `shipments` table, no
extra state to keep in sync):

| Field | Meaning |
|---|---|
| `delivered` | `delivered` + `partial_delivered` |
| `cancelled` / `returned` | count against the customer |
| `completed` | delivered + cancelled + returned (reached an outcome) |
| `fraud_score` | `(cancelled + returned) / completed`, `0` if none completed |
| `risk` | `new` (no history) · `low` · `medium` (>0) · `high` (≥2 completed & score ≥ 0.5) |

Surfaced on the **admin order page** as a "Courier history" card
(`resources/js/pages/orders/show.tsx`) — e.g. *"5 sent, 3 cancelled ⚠️ High
risk"*. The admin can then require an advance before shipping COD to a repeat
"cancel on arrival" buyer. As more orders flow, this becomes our own fraud DB.

**Future (optional):** add a third-party BD courier fraud aggregator (combined
Pathao+Steadfast+RedX success/return ratio by phone) behind a `FraudChecker`
interface as an adapter — provider-agnostic, no caller change.

---

## 6. Configuration

### Admin → Settings → Integrations → Steadfast
- **Api-Key / Secret-Key** — from your Steadfast merchant panel. Secret is
  write-only (leave blank to keep the saved one); both stored encrypted.
- **Auto-push** (`steadfast.auto_push`, default on) — book automatically when an
  order is confirmed. Turn off to book manually only.

### Requirements
- **Queue worker + scheduler must be running** (SERVER-OPS-GUIDE §"Background
  workers") — otherwise confirmed orders are never pushed and statuses never
  refresh.
- `QUEUE_CONNECTION=database`.

---

## 7. Security

- **cod_amount is server-computed only** — never taken from the client.
- Api-Key/Secret-Key **encrypted at rest**, never in repo/client/logs; raw courier
  payload stored `encrypted:array`.
- Booking is **idempotent** (unique `invoice = order_no` + existing-consignment
  guard) — no duplicate consignments on retries.
- Status polling is server-side; no PII (phone/address) is logged.
- Admin ship/track routes require the `orders.manage` permission.

---

## 8. Tests

- `tests/Feature/Admin/CourierTest.php` — manual booking, COD = remaining balance,
  idempotent re-ship, tracking status mapping, authz.
- `tests/Feature/Courier/CourierAutomationTest.php` — auto-push fires on confirm
  only when configured, respects the `auto_push` switch, ignores unrelated status
  changes, books exactly one consignment, polls & updates in-flight (skips
  terminal), and computes the fraud/return-ratio score + risk.

The real HTTP courier is swapped for `FakeCourierGateway`, so tests never hit the
network.
