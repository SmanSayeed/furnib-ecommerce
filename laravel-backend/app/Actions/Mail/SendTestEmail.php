<?php

declare(strict_types=1);

namespace App\Actions\Mail;

use App\Mail\TestMail;
use App\Support\Mail\MailConfigurator;
use Illuminate\Support\Facades\Mail;

/**
 * Sends a deliverability test using the dynamically-configured SMTP transport.
 */
final class SendTestEmail
{
    public function __construct(private readonly MailConfigurator $configurator) {}

    public function handle(string $to): void
    {
        $this->configurator->apply();

        Mail::to($to)->send(new TestMail);
    }
}
