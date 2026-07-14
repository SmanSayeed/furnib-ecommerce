/**
 * Human labels for order enums. Mirrors Order::PENDING_REASONS on the server.
 *
 * Lives here because the map was copy-pasted into both orders/index.tsx and
 * orders/show.tsx, so a new reason had to be added in three places (and one of
 * them was always forgotten).
 */
export const PENDING_REASON_LABELS: Record<string, string> = {
    new_order: 'New order',
    call_waiting: 'Call waiting',
    payment_pending: 'Payment pending',
    need_expert_call: 'Need expert call',
    other: 'Other',
};

export const pendingReasonLabel = (reason: string | null | undefined): string =>
    reason ? (PENDING_REASON_LABELS[reason] ?? reason) : '';
