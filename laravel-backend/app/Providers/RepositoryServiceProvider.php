<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\CategoryRepository;
use App\Repositories\Eloquent\ProductRepository;
use App\Repositories\Eloquent\UserRepository;
use App\Storage\Contracts\StorageRepository;
use App\Storage\StorageManager;
use App\Support\Payments\PaymentGateway;
use App\Support\Payments\SslCommerzGateway;
use App\Support\Sms\LogSmsGateway;
use App\Support\Sms\SmsGateway;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Interface → implementation bindings. Add a line per repository.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        UserRepositoryInterface::class => UserRepository::class,
        CategoryRepositoryInterface::class => CategoryRepository::class,
        ProductRepositoryInterface::class => ProductRepository::class,
        SmsGateway::class => LogSmsGateway::class,
        PaymentGateway::class => SslCommerzGateway::class,
    ];

    public function register(): void
    {
        // Resolve the active storage driver from settings on each resolution.
        $this->app->bind(
            StorageRepository::class,
            fn ($app) => $app->make(StorageManager::class)->driver(),
        );
    }
}
