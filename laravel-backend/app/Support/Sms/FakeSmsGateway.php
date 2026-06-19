<?php

declare(strict_types=1);

namespace App\Support\Sms;

/**
 * In-memory SMS gateway for tests. Records every message so assertions can
 * verify a send happened (and with what content) without hitting a network.
 * The next send can be forced to fail to exercise non-fatal failure handling.
 */
final class FakeSmsGateway implements SmsGateway
{
    /** @var array<int, array{mobile: string, message: string}> */
    public array $sent = [];

    public bool $shouldFail = false;

    public function send(string $mobile, string $message): bool
    {
        if ($this->shouldFail) {
            return false;
        }

        $this->sent[] = ['mobile' => $mobile, 'message' => $message];

        return true;
    }

    /**
     * @return array<int, array{mobile: string, message: string}>
     */
    public function messagesTo(string $mobile): array
    {
        return array_values(array_filter(
            $this->sent,
            static fn (array $m): bool => $m['mobile'] === $mobile,
        ));
    }

    public function failNext(): void
    {
        $this->shouldFail = true;
    }
}
