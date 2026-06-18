<?php

declare(strict_types=1);

namespace App\Storage\Drivers;

final class CloudflareR2Storage extends DiskStorage
{
    protected function disk(): string
    {
        return 'r2';
    }
}
