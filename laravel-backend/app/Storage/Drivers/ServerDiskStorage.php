<?php

declare(strict_types=1);

namespace App\Storage\Drivers;

final class ServerDiskStorage extends DiskStorage
{
    protected function disk(): string
    {
        return 'public';
    }
}
