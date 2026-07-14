<?php

declare(strict_types=1);

namespace App\Support\Courier;

/**
 * A courier driver that can prove its credentials work, without shipping anything.
 *
 * Optional capability (kept off CourierGateway so a new driver can skip it): the
 * admin clicks "Test connection" and gets the provider's real answer. Before this,
 * the only way to find out whether a key was right was to place a live order and
 * watch it fail.
 */
interface TestsConnection
{
    /**
     * Make a cheap, READ-ONLY authenticated call to the provider.
     *
     * @return string a human-readable success line, shown to the admin
     *
     * @throws CourierException when the provider rejects us or cannot be reached
     */
    public function testConnection(): string;
}
