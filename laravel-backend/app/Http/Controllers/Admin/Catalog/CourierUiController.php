<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CourierFormRequest;
use App\Models\Courier;
use App\Support\Courier\CourierException;
use App\Support\Courier\CourierManager;
use App\Support\Courier\TestsConnection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD for couriers (Manual + API providers). Credentials live in the model's
 * encrypted `config`; the browser only ever sees "is set" flags, and a blank
 * credential field on update keeps the stored secret. At most one courier is the
 * default (auto-booked on confirm).
 */
class CourierUiController extends Controller
{
    public function __construct(private readonly CourierManager $couriers) {}

    public function index(): Response
    {
        return Inertia::render('shipping/couriers/index', [
            'couriers' => Courier::query()
                ->orderBy('position_order')
                ->orderBy('name')
                ->get()
                ->map(fn (Courier $c): array => $this->listRow($c))
                ->all(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('shipping/couriers/form', [
            'courier' => null,
            'drivers' => CourierFormRequest::SELECTABLE_DRIVERS,
        ]);
    }

    public function store(CourierFormRequest $request): RedirectResponse
    {
        $data = $request->safe()->only(['name', 'driver', 'is_active', 'is_default', 'position_order']);
        $data['slug'] = $this->resolveSlug($request->validated('slug'), $data['name'], null);
        $data['config'] = $this->buildConfig($request, null);

        $courier = Courier::query()->create($data);
        $this->syncDefault($courier);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Courier created.')]);

        return to_route('admin.couriers.index');
    }

    public function edit(Courier $courier): Response
    {
        return Inertia::render('shipping/couriers/form', [
            'courier' => $this->formData($courier),
            'drivers' => CourierFormRequest::SELECTABLE_DRIVERS,
        ]);
    }

    public function update(CourierFormRequest $request, Courier $courier): RedirectResponse
    {
        $data = $request->safe()->only(['name', 'driver', 'is_active', 'is_default', 'position_order']);
        $data['slug'] = $this->resolveSlug($request->validated('slug'), $data['name'], $courier);
        $data['config'] = $this->buildConfig($request, $courier);

        $courier->update($data);
        $this->syncDefault($courier);

        // Pathao's OAuth token is cached for days and is returned BEFORE the
        // credentials are checked — so a corrected password would keep 401-ing
        // behind the stale token. Drop it whenever the courier is saved.
        Cache::forget(Courier::pathaoTokenCacheKey($courier->id));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Courier updated.')]);

        return to_route('admin.couriers.index');
    }

    /**
     * Prove the stored credentials actually work, without shipping anything.
     *
     * Until this existed there was no way to answer "are my keys right?" short of
     * placing a live order and watching it fail. The provider's real answer is
     * shown — balance, area count, or the exact rejection.
     */
    public function test(Courier $courier): RedirectResponse
    {
        $driver = $this->couriers->driverFor($courier);

        if (! $driver instanceof TestsConnection) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => __(':name has no API to test — it is booked by hand.', ['name' => $courier->name]),
            ]);

            return back();
        }

        try {
            $message = $driver->testConnection();
        } catch (CourierException $e) {
            report($e);
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    public function destroy(Courier $courier): RedirectResponse
    {
        $courier->delete(); // soft delete — historical shipments keep the name snapshot

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Courier removed.')]);

        return to_route('admin.couriers.index');
    }

    /**
     * Merge submitted credentials into the stored config; a blank credential field
     * keeps the existing secret. Only the keys relevant to the chosen driver are
     * persisted. API drivers also carry a `sandbox` flag. A manual courier carries
     * no config.
     *
     * @return array<string, mixed>|null
     */
    private function buildConfig(CourierFormRequest $request, ?Courier $courier): ?array
    {
        $driver = (string) $request->validated('driver');

        // Manual courier — no credentials at all.
        if (! in_array($driver, Courier::API_DRIVERS, true)) {
            return null;
        }

        $config = $courier !== null ? ($courier->config ?? []) : [];

        // $driver is one of the API drivers here (guarded above), so its required
        // credential list always exists.
        foreach (Courier::REQUIRED_CREDENTIALS[$driver] as $key) {
            $value = $request->validated($key);

            if (filled($value)) {
                $config[$key] = (string) $value;
            }
        }

        // Sandbox is a plain flag (not a blank-keeps secret) — always set it.
        $config['sandbox'] = (bool) $request->validated('sandbox', false);

        return $config;
    }

    /** Ensure at most one default courier when this one is marked default. */
    private function syncDefault(Courier $courier): void
    {
        if ($courier->is_default) {
            Courier::query()->whereKeyNot($courier->id)->where('is_default', true)->update(['is_default' => false]);
        }
    }

    private function resolveSlug(?string $slug, string $name, ?Courier $courier): string
    {
        $base = filled($slug) ? Str::slug((string) $slug) : Str::slug($name);
        $candidate = $base;
        $suffix = 1;

        while (Courier::query()
            ->when($courier !== null, fn ($q) => $q->whereKeyNot($courier->id))
            ->where('slug', $candidate)
            ->exists()
        ) {
            $candidate = $base.'-'.(++$suffix);
        }

        return $candidate;
    }

    /**
     * @return array<string, mixed>
     */
    private function listRow(Courier $courier): array
    {
        return [
            'id' => $courier->id,
            'name' => $courier->name,
            'slug' => $courier->slug,
            'driver' => $courier->driver,
            'is_api' => $courier->isApi(),
            'is_active' => $courier->is_active,
            'is_default' => $courier->is_default,
            'configured' => $courier->isConfigured(),
            'position_order' => $courier->position_order,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Courier $courier): array
    {
        // Secrets never reach the browser — only whether each credential is set.
        $credentialSet = [];

        foreach (Courier::REQUIRED_CREDENTIALS[$courier->driver] ?? [] as $key) {
            $credentialSet[$key.'_set'] = filled($courier->credential($key));
        }

        return [
            'id' => $courier->id,
            'name' => $courier->name,
            'slug' => $courier->slug,
            'driver' => $courier->driver,
            'is_active' => $courier->is_active,
            'is_default' => $courier->is_default,
            'position_order' => $courier->position_order,
            'sandbox' => (bool) ($courier->config['sandbox'] ?? false),
            ...$credentialSet,
        ];
    }
}
