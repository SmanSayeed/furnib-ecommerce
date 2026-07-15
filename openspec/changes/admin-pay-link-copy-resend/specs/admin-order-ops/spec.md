## ADDED Requirements

### Requirement: The admin can see, copy and re-send an order's pay link
The order detail SHALL expose the customer's self-service payment link, and an authorized admin
(`orders.manage`) SHALL be able to re-send it by SMS. Re-sending SHALL be rate-limited per order
and SHALL not be blocked by the channel's send-once idempotency guard. The re-sent message SHALL
be rendered from the current order state.

#### Scenario: The pay link is on the order payload
- **WHEN** an admin opens an order's detail page
- **THEN** the payload carries the order's `pay_url` (the HMAC-tokenised pay link)

#### Scenario: Resend delivers the SMS again
- **WHEN** an admin resends the pay link for an order whose placement SMS already went out
- **THEN** the SMS is sent again, carrying the order number and the current pay link

#### Scenario: Resend is rate-limited
- **WHEN** an admin resends the pay link more than 3 times within an hour for the same order
- **THEN** the 4th attempt is rejected and no further SMS is sent

#### Scenario: Only authorized staff can resend
- **WHEN** a user without `orders.manage` posts to the resend endpoint
- **THEN** the request is forbidden
