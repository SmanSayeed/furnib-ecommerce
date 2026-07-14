# Design — courier-credentials-and-order-ops

## A. Courier credentials — one truth

### The bug, stated precisely

```
                          ┌─ couriers.config (encrypted:array)   ← Shipping → Couriers
credential source ────────┤
                          └─ settings[group=steadfast]           ← Settings → Integrations

Courier::isConfigured()   reads ONLY couriers.config          →  false  →  Book button disabled
driver factory            reads config ?? settings            →  works  →  the call would succeed
```

The gate and the door disagree. The owner's keys are in the settings group (proved by the green **`Configured`** badge on Settings → Integrations, which reads that group), so the gate says no while the door is unlocked.

### The fix

Push the fallback **down into the model**, so every caller sees one resolved value:

```php
// Courier
public function credential(string $key): ?string
{
    $value = $this->safeConfig()[$key] ?? null;
    $value = is_scalar($value) ? (string) $value : null;

    return filled($value) ? $value : $this->legacyCredential($key);
}

private function legacyCredential(string $key): ?string
{
    // Only Steadfast ever had a settings group (Settings → Integrations).
    if ($this->driver !== self::DRIVER_STEADFAST) {
        return null;
    }
    $value = app(SettingsService::class)->get('steadfast', $key);

    return is_string($value) && $value !== '' ? $value : null;
}
```

`isConfigured()` already calls `credential()`, so it is fixed for free — and so are `canBookViaApi()`, the Book button, the auto-push observer, and `CourierUiController::formData()`'s "is set" flags.

`RepositoryServiceProvider`'s duplicated `?? $settings->get(...)` fallback is then **removed** — there is exactly one place that knows about the legacy store.

### Why not just tell the owner to re-type the keys in the other page?

Because the next person hits it again. Two write surfaces that disagree is the defect; a one-off data fix leaves it armed. The data migration below *also* runs, so `couriers.config` becomes the store of record — but the fallback stays as the safety net.

### DecryptException — the second, hidden 500

`couriers.config` is `encrypted:array`. If `APP_KEY` ever differs from the one the row was encrypted with (DB moved between environments, key rotated, `config:cache` baked a build-time key), **merely reading the attribute throws**, and nothing catches it. The Couriers list and the Integrations page become hard 500s — with `APP_DEBUG=false` the admin just sees a blank error.

```php
/** @return array<string, mixed> */
public function safeConfig(): array
{
    try {
        return $this->config ?? [];
    } catch (DecryptException $e) {
        report($e);      // visible in /admin/dev/errors

        return [];       // degrade to "not configured", never 500
    }
}
```

`SettingsService::decrypt()` gets the same treatment. Degrading to *"not configured — re-enter the credentials"* is honest and actionable; a 500 is neither.

## B. Loud adapters

Today every SteadFast failure mode collapses into one message:

```php
$response = $this->client()->post(self::BASE_URL.'/create_order', [...]);   // no timeout
$consignment = $response->json('consignment');                              // no status check
if (! is_array($consignment) || empty($consignment['consignment_id'])) {
    throw new RuntimeException('Failed to create SteadFast consignment.');  // no body, no status
}
```

A 401, a 422, an HTML error page and a hung socket are indistinguishable. New shape:

```php
final class CourierException extends RuntimeException
{
    public static function http(string $courier, int $status, string $body): self;
    public static function missingCredentials(string $courier): self;
    public static function unreachable(string $courier, string $reason): self;
}
```

Every adapter:
- `->timeout(20)->connectTimeout(10)` — a blocked egress now fails in seconds with a clear message instead of hanging until Traefik 504s.
- `->retry(2, 300, throw: false)` — survives a transient blip.
- checks `$response->successful()` and throws `CourierException::http()` with the status and the **truncated** body.
- wraps `ConnectionException` into `CourierException::unreachable()` (DNS / TLS / firewall — the classic "worked on my machine, not on the VPS", often because the merchant's server IP isn't whitelisted in the provider panel).

**Body redaction**: provider error bodies do not contain our keys (the keys go in *request headers*), so the body is safe to show. It is still truncated to 300 chars and the request headers are never logged.

`ShipmentController::store` wraps the call:

```php
try {
    $createConsignment->handle($order, $courier, $note, $meta);
} catch (CourierException $e) {
    Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
    report($e);
    return back();
}
```

So the admin sees *"SteadFast rejected the request (HTTP 401): Unauthorized"* instead of a white 500 page.

`CourierLocationController::guard()` keeps returning `200 + options: []` (the order page must not break), but now **reports** the exception, so the real cause finally lands in `/admin/dev/errors`.

## C. "Test connection" — answering *"how do I verify the keys?"*

A new optional capability interface, so a driver opts in without changing `CourierGateway`:

```php
interface TestsConnection
{
    /** Human-readable success line. Throws CourierException on failure. */
    public function testConnection(): string;
}
```

| Driver | Probe | Why |
|---|---|---|
| SteadFast | `GET /get_balance` | read-only, cheap, authenticates with the same `Api-Key`/`Secret-Key` headers as booking |
| RedX | `GET /areas` | read-only, authenticates the bearer token |
| Pathao | issue an OAuth token (cache busted first) | proves client_id/secret/username/password |

`POST /admin/shipping/couriers/{courier}/test` (`permission:couriers.manage`) → flashes the real result:

- ✅ *"SteadFast connected. Current balance: ৳1,240.00"*
- ❌ *"SteadFast rejected the credentials (HTTP 401). Check the Api-Key/Secret-Key, and confirm your server IP is whitelisted in the SteadFast panel."*
- ❌ *"Could not reach SteadFast: connection timed out."*

This is the single most valuable thing in this change: it turns "it doesn't work" into a one-click diagnosis, forever.

**Pathao token cache**: `PathaoCourier::accessToken()` returns the cached token *before* validating credentials, with a TTL of up to ~5 days, keyed `courier:pathao:token:{id}`. Nothing invalidated it when the credentials changed — so after fixing a wrong password you would keep getting 401s. `CourierUiController::update()` now forgets that key.

## D. Shipping recompute — the sharp edge

Changing an order's shipping zone changes its shipping cost, hence its total, hence the pay link and the invoice. The formula currently lives inline in `PlaceOrder.php:130-148` (and is duplicated in TypeScript in `CheckoutForm.tsx:50-59`).

Extract it once:

```php
final class ShippingCalculator
{
    /**
     * shipping = (any chargeable line ? zone.cost : 0)
     *          + Σ over chargeable lines [ product.extraPerUnitMinorFor(zone) × qty ]
     */
    public function minorFor(Collection $lines, ?ShippingZone $zone): int;
}
```

`PlaceOrder` calls it (behaviour byte-identical — pinned by the existing `PlaceOrderTest`), and so does the new customer/address update. The next change (`admin-order-discount`) and the one after (`admin-create-order`) reuse it too.

Then a single totals invariant, in one place:

```php
final class RecalculateOrderTotals
{
    // total = subtotal + shipping_cost      (discount arrives in the next change)
    // followed by OrderPaymentReconciler so payment_status / due stay correct
    public function handle(Order $order): void;
}
```

### Recompute guards

| Situation | Behaviour |
|---|---|
| Zone changed, order **unpaid** | recompute shipping + total, reconcile payment status |
| Zone changed, order **paid** | **rejected** — validation error. Changing the total of a settled order silently creates a debt or a refund obligation. |
| Zone changed, new total < `advance_paid` | **rejected** — the customer would be owed money; that is a refund decision, not an address edit |
| Zone unchanged | totals untouched, not even recomputed (no accidental drift from a formula change) |
| Consignment already booked | edit **allowed**, but a warning is flashed: the courier still holds the old address and COD amount — cancel and re-book |

## E. Customer editing — the shared-row trap

`customers` is keyed by mobile and **shared across every order that person placed** (`CustomerService::findOrCreateByMobile`). So editing name/mobile/email on the order page edits *the customer*, not *this order's copy* — every past order of theirs shows the new name.

That is the correct semantics (it is the same human), but it must be **said out loud in the UI**, and the mobile needs care:

- Mobile is normalised the same way checkout does (`+880…`) before the uniqueness check, so `01712345678` and `+8801712345678` cannot become two customers.
- `Rule::unique('customers','mobile')->ignore($customer->id)` — changing a phone number to one that already belongs to another customer is rejected. (Merging two customers is a different, bigger feature.)
- The address, by contrast, lives on the **order** (`orders.address`), so editing it affects only this order. Good — that is what an address correction should do.

## Corner cases

| # | Case | Expected |
|---|---|---|
| 1 | Keys only in the legacy settings group (the owner's situation) | `isConfigured()` = true, Book enabled, booking succeeds |
| 2 | Keys in `couriers.config` only | unchanged — config wins |
| 3 | Keys in **both**, different values | `couriers.config` wins (it is the newer, per-courier store) |
| 4 | Keys in neither | `isConfigured()` = false, clear message, Book disabled — as today |
| 5 | `couriers.config` encrypted with a different APP_KEY | `report()` + treated as not configured. No 500. |
| 6 | SteadFast returns 401 | `CourierException` → red toast with the status + a hint about IP whitelisting |
| 7 | SteadFast returns 422 (duplicate `invoice` on re-book) | the provider's own message reaches the admin |
| 8 | Courier host unreachable / egress blocked | fails in ≤10 s with *"Could not reach SteadFast"* — no hung request, no 504 |
| 9 | Test connection on a **manual** courier | no-op with a clear "this courier has no API" message |
| 10 | Pathao credentials changed | cached token forgotten, next call re-authenticates |
| 11 | Pending-reason filter + "select all matching" bulk | the bulk targets only the filtered rows |
| 12 | Inline pending reason set to "Other" | the note input appears and is required (matches `UpdatePendingReasonRequest`) |
| 13 | Inline pending reason on a **non-pending** row | control not rendered; the server still rejects it (`updatePending` guards on status) |
| 14 | Admin note on a confirmed/delivered order | persists — unlike `pending_note`, it is never wiped by a status change |
| 15 | Address edited after the consignment is booked | allowed + warning; the courier still has the old address |
| 16 | Mobile changed to one already used by another customer | rejected with a validation error |
| 17 | Zone changed on a paid order | rejected |
| 18 | Zone changed so the new total < advance already paid | rejected |
| 19 | Zone cleared (set to none) | shipping → 0, total = subtotal, reconciled |
| 20 | Free-shipping-only cart, zone changed | shipping stays 0 (no chargeable line) — the calculator already handles this |

## Verification

- **Pest**: credential fallback matrix (corner cases 1–5); `CourierException` on 401/422/timeout (`Http::fake`); `testConnection()` success + failure; `ShippingCalculator` parity with the existing `PlaceOrderTest` expectations; totals recompute + every guard (15–20); pending-reason filter; admin note survives a status change; customer edit uniqueness.
- **Production, after deploy** — the acceptance test the owner actually cares about:
  1. Shipping → Couriers → SteadFast → **Test connection** → expect *"Connected. Balance ৳X"*.
  2. Order detail → the Book button is now **enabled** and no longer says "needs credentials".
  3. Book a real order with the courier → a consignment id comes back.
  4. If it fails, the toast now names the reason — and `/admin/dev/errors` has the full trace.
