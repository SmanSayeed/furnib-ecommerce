<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Payments\PendingPaymentReconciler;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Scheduled sweep that recovers payments where the money moved but our
 * callback/IPN was lost. Unique so overlapping runs never double-process, and
 * retryable so a transient gateway/DB hiccup is tried again.
 */
final class ReconcilePendingPayments implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    /** Only ever one queued/running at a time. */
    public int $uniqueFor = 600;

    public function handle(PendingPaymentReconciler $reconciler): void
    {
        $reconciler->sweep();
    }
}
