<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Payments\RecordManualPayment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreManualPaymentRequest;
use App\Models\Order;
use App\Models\Payment;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Admin manual payment adjustments on an order. Adding a payment (credit) or
 * recording a refund/reduction (debit) appends a NEW ledger row — the customer's
 * original gateway payment is never modified. Gated by orders.manage (enforced
 * in the FormRequest); every entry is audit-logged with the actor and note.
 */
class OrderPaymentController extends Controller
{
    public function __construct(private readonly RecordManualPayment $recordManual) {}

    public function store(StoreManualPaymentRequest $request, Order $order): RedirectResponse
    {
        $data = $request->validated();
        $amount = Money::fromMinor((int) $request->integer('amount_minor'));

        // A refund/reduction can never exceed what has actually been paid.
        if ($data['direction'] === Payment::DIRECTION_DEBIT
            && $amount->toMinor() > $order->advance_paid->toMinor()) {
            throw ValidationException::withMessages([
                'amount' => 'A refund cannot exceed the amount already paid ('.$order->advance_paid->format('৳').').',
            ]);
        }

        $this->recordManual->handle($order, $data['direction'], $amount, $data['note'], $data['method']);

        $verb = $data['direction'] === Payment::DIRECTION_CREDIT ? 'Payment recorded.' : 'Refund recorded.';
        Inertia::flash('toast', ['type' => 'success', 'message' => __($verb)]);

        return back();
    }
}
