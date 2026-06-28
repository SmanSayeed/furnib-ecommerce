<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Newsletter subscribers list + CSV export. Gated by `settings.manage`.
 */
class SubscriberController extends Controller
{
    public function index(): Response
    {
        $subscribers = NewsletterSubscriber::query()
            ->latest('id')
            ->limit(500)
            ->get(['id', 'email', 'source', 'created_at'])
            ->map(fn (NewsletterSubscriber $s): array => [
                'id' => $s->id,
                'email' => $s->email,
                'source' => $s->source,
                'at' => $s->created_at?->toDateTimeString(),
            ])
            ->all();

        return Inertia::render('subscribers/index', [
            'subscribers' => $subscribers,
            'total' => NewsletterSubscriber::query()->count(),
        ]);
    }

    public function export(): StreamedResponse
    {
        $filename = 'subscribers-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'wb');

            if ($out === false) {
                return;
            }

            fputcsv($out, ['email', 'source', 'subscribed_at']);

            NewsletterSubscriber::query()
                ->orderBy('id')
                ->chunk(500, function ($rows) use ($out): void {
                    foreach ($rows as $s) {
                        fputcsv($out, [$s->email, $s->source, (string) $s->created_at]);
                    }
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
