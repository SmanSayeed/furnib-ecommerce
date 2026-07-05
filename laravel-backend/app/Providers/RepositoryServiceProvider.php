<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Courier;
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
use App\Support\Courier\CourierManager;
use App\Support\Courier\PathaoCourier;
use App\Support\Courier\RedxCourier;
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

        // Courier gateways, keyed by driver. Open for extension: RedX/Pathao
        // register their own factory in later phases without touching callers. A
        // Steadfast courier reads its own encrypted config, falling back to the
        // legacy `steadfast` settings so pre-migration installs keep working.
        $this->app->singleton(CourierManager::class, function ($app): CourierManager {
            $manager = new CourierManager;

            $manager->register(Courier::DRIVER_STEADFAST, function (Courier $courier) use ($app): SteadFastCourier {
                $settings = $app->make(SettingsService::class);

                return new SteadFastCourier(
                    $courier->credential('api_key') ?? $settings->get('steadfast', 'api_key'),
                    $courier->credential('secret_key') ?? $settings->get('steadfast', 'secret_key'),
                );
            });

            $manager->register(Courier::DRIVER_REDX, fn (Courier $courier): RedxCourier => new RedxCourier(
                $courier->credential('access_token'),
                $courier->credential('pickup_store_id'),
                (bool) ($courier->config['sandbox'] ?? false),
            ));

            $manager->register(Courier::DRIVER_PATHAO, fn (Courier $courier): PathaoCourier => new PathaoCourier(
                $courier->credential('client_id'),
                $courier->credential('client_secret'),
                $courier->credential('username'),
                $courier->credential('password'),
                $courier->credential('store_id'),
                (bool) ($courier->config['sandbox'] ?? false),
                'courier:pathao:token:'.$courier->id,
            ));

            return $manager;
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
