<?php

declare(strict_types=1);

namespace App\Services\Courier;

use App\Models\Shipment;
use App\Support\MobileNumber;

/**
 * Our own courier fraud / return-ratio signal — Steadfast exposes no fraud API,
 * so we build it from history we already own. For a given customer phone it
 * aggregates every past consignment's final delivery outcome into a risk score
 * (higher = more cancellations/returns), which the admin can use to require an
 * advance before shipping COD to a repeat "cancel on arrival" customer.
 *
 * Derived on the fly from the shipments table — no extra state to keep in sync.
 */
final class CustomerCourierStats
{
    /** Outcomes that count as a successful delivery. */
    private const DELIVERED = ['delivered', 'partial_delivered'];

    /** Outcomes that count against the customer. */
    private const RETURNED = ['returned'];

    private const CANCELLED = ['cancelled'];

    /**
     * @return array{phone: string, total: int, delivered: int, cancelled: int,
     *   returned: int, completed: int, in_flight: int, fraud_score: float,
     *   success_rate: float, risk: string}
     */
    public function forPhone(string $phone): array
    {
        $normalized = MobileNumber::isValid($phone) ? MobileNumber::normalize($phone) : $phone;

        /** @var array<string, int> $counts status => count */
        $counts = Shipment::query()
            ->where('recipient_phone', $normalized)
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->map(fn ($c): int => (int) $c)
            ->all();

        $sum = static fn (array $statuses): int => array_sum(
            array_map(static fn (string $s): int => $counts[$s] ?? 0, $statuses)
        );

        $total = array_sum($counts);
        $delivered = $sum(self::DELIVERED);
        $cancelled = $sum(self::CANCELLED);
        $returned = $sum(self::RETURNED);
        $completed = $delivered + $cancelled + $returned;
        $bad = $cancelled + $returned;

        $fraudScore = $completed > 0 ? round($bad / $completed, 2) : 0.0;
        $successRate = $completed > 0 ? round($delivered / $completed, 2) : 0.0;

        return [
            'phone' => $normalized,
            'total' => $total,
            'delivered' => $delivered,
            'cancelled' => $cancelled,
            'returned' => $returned,
            'completed' => $completed,
            'in_flight' => $total - $completed,
            'fraud_score' => $fraudScore,
            'success_rate' => $successRate,
            'risk' => $this->risk($total, $completed, $fraudScore),
        ];
    }

    private function risk(int $total, int $completed, float $fraudScore): string
    {
        return match (true) {
            $total === 0 => 'new',
            $completed >= 2 && $fraudScore >= 0.5 => 'high',
            $fraudScore > 0 => 'medium',
            default => 'low',
        };
    }
}
