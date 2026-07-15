## Why

The customer's self-service pay link (`{frontend}/pay/{order_no}?t={hmac}`) is generated only
inside the placement SMS. It is never surfaced to the admin, so staff cannot copy it into
WhatsApp, open it to check, or re-send it when the customer says the SMS never arrived. And a
naive re-send would be silently swallowed by the notification channel's idempotency guard.

## What Changes

- **`OrderController::show`** exposes `pay_url` (the same HMAC link) on the order payload.
- **Admin order detail** gains a "Payment link" card: the URL, a **Copy** button, an **Open**
  link, and a **Resend SMS** button.
- **`POST /admin/orders/{order}/resend-pay-link`** — `orders.manage`, **rate-limited to
  3/hour/order** so it can never become an SMS-bill DoS. It clears this order's prior `placed`
  notification logs (so the idempotency guard lets the message go again) and sends synchronously
  for immediate feedback. The message re-renders from the live order row, so a resend after a
  discount carries the reduced total automatically.

## Impact

- Affected specs: `admin-order-ops`.
- No DB change. The pay token remains an unexpiring HMAC — noted in the security backlog
  (`PayLink.php`) for a later hardening pass (expiry + revocation); out of scope here.
- Security: the endpoint is permission-gated and rate-limited; the link is unguessable
  (HMAC of the order number).
