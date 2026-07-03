<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only gateway transactions list. Gated by `payments.view`. The encrypted
 * raw gateway payload is never exposed — only non-sensitive summary fields.
 */
class PaymentController extends Controller
{
    public function index(): Response
    {
        $payments = Payment::query()
            ->with('order:id,order_no')
            ->latest('id')
            ->limit(300)
            ->get()
            ->map(fn (Payment $p): array => [
                'id' => $p->id,
                'order_no' => $p->order?->order_no,
                'gateway' => $p->gateway,
                'amount' => $p->amount->format('৳'),
                'type' => $p->type,
                'status' => $p->status,
                'note' => $p->note,
                'tran_id' => $p->tran_id,
                'val_id' => $p->val_id,
                'at' => $p->created_at?->toDateTimeString(),
            ])
            ->all();

        return Inertia::render('payments/index', ['payments' => $payments]);
    }
}
