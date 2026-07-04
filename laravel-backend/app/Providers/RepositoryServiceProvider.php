<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\CategoryRepository;
use App\Repositories\Eloquent\ProductRepository;
use App\Repositories\Eloquent\UserRepository;
use App\Services\Notifications\OrderNotificationService;
use App\Services\Settings\SettingsService;
use App\Storage\Contracts\StorageRepository;
use App\Storage\StorageManager;
use App\Support\Capi\ConversionApi;
use App\Support\Capi\MetaConversionApi;
use App\Support\Courier\CourierGateway;
use App\Support\Courier\SteadFastCourier;
use App\Support\Ga4\HttpMeasurementProtocol;
use App\Support\Ga4\MeasurementProtocol;
use App\Support\Notifications\Channels\SmsOrderChannel;
use App\Support\Payments\PaymentGateway;
use App\Support\Payments\SslCommerzGateway;
use App\Support\Sms\AutomasSmsGateway;
use App\Support\Sms\LogSmsGateway;
use App\Support\Sms\SmsGateway;
use App\Support\Tiktok\EventsApi;
use App\Support\Tiktok\HttpEventsApi;
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
        PaymentGateway::class => SslCommerzGateway::class,
        CourierGateway::class => SteadFastCourier::class,
        ConversionApi::class => MetaConversionApi::class,
        EventsApi::class => HttpEventsApi::class,
        MeasurementProtocol::class => HttpMeasurementProtocol::class,
    ];

    public function register(): void
    {
        // Resolve the active storage driver from settings on each resolution.
        $this->app->bind(
            StorageRepository::class,
            fn ($app) => $app->make(StorageManager::class)->driver(),
        );

        // SMS driver: the real Automas gateway once an API key + sender id are
        // configured, otherwise the log driver — so an unconfigured install (and
        // local dev) behaves exactly as before, and tests can swap in a fake.
        $this->app->bind(SmsGateway::class, function ($app): SmsGateway {
            $settings = $app->make(SettingsService::class);
            $configured = filled($settings->get('sms', 'api_key'))
                && filled($settings->get('sms', 'sender_id'));

            return $configured
                ? $app->make(AutomasSmsGateway::class)
                : $app->make(LogSmsGateway::class);
        });

        // Order notification channels (SMS now, email later). Tagging keeps the
        // fan-out service open for extension: register a channel + tag it, done.
        $this->app->tag([SmsOrderChannel::class], 'order-notification-channels');

        $this->app->bind(
            OrderNotificationService::class,
            fn ($app) => new OrderNotificationService($app->tagged('order-notification-channels')),
        );
    }
}
