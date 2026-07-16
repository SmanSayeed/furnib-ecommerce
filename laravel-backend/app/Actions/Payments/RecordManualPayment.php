<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\OrderPaymentReconciler;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Records an admin's manual payment adjustment against an order WITHOUT touching
 * the customer's original gateway payment: a new ledger row is appended (credit =
 * payment received offline, debit = refund/reduction), then the order's paid/due
 * is recomputed from the whole ledger. Whole-taka amounts only (no poysha); the
 * note is required and the actor is captured by the model's audit log.
 */
final class RecordManualPayment
{
    public function __construct(private readonly OrderPaymentReconciler $reconciler) {}

    public function handle(Order $order, string $direction, Money $amount, string $note, ?string $method = null): Payment
    {
        return DB::transaction(function () use ($order, $direction, $amount, $note, $method): Payment {
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            $payment = Payment::query()->create([
                'order_id' => $locked->id,
                'gateway' => Payment::GATEWAY_MANUAL,
                // The channel the money moved through (bKash/Nagad/…); the tran id /
                // bank reference lives in the note.
                'method' => $method,
                'amount' => $amount,
                'type' => Payment::TYPE_MANUAL,
                'direction' => $direction,
                'tran_id' => 'MANUAL-'.Str::upper(Str::random(18)),
                'status' => Payment::STATUS_SUCCESS,
                'note' => $note,
            ]);

            $this->reconciler->reconcile($locked);

            return $payment;
        });
    }
}
