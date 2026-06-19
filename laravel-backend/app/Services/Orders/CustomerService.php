<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Models\Customer;
use App\Support\MobileNumber;

final class CustomerService
{
    /**
     * Resolve a customer by mobile, creating one if none exists. The mobile is
     * normalized to canonical E.164 first (throws on an invalid BD number, so
     * callers should validate input beforehand). Name/email are filled only
     * when currently blank — an existing non-empty name is never overwritten.
     */
    public function findOrCreateByMobile(string $mobile, ?string $name = null, ?string $email = null): Customer
    {
        $normalized = MobileNumber::normalize($mobile);

        $customer = Customer::query()->firstOrCreate(
            ['mobile' => $normalized],
            ['name' => $name, 'email' => $email],
        );

        $fill = [];

        if (blank($customer->name) && filled($name)) {
            $fill['name'] = $name;
        }

        if (blank($customer->email) && filled($email)) {
            $fill['email'] = $email;
        }

        if ($fill !== []) {
            $customer->update($fill);
        }

        return $customer;
    }
}
