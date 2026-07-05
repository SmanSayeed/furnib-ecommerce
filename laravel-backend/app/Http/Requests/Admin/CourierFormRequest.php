<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Courier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create/update a courier. Credentials arrive as plain inputs and are stored in
 * the model's encrypted `config` by the controller (blank keeps the stored
 * secret). All drivers are selectable: Manual, Steadfast, RedX and Pathao.
 */
class CourierFormRequest extends FormRequest
{
    /** Drivers selectable in the admin form. */
    public const SELECTABLE_DRIVERS = [
        Courier::DRIVER_MANUAL,
        Courier::DRIVER_STEADFAST,
        Courier::DRIVER_REDX,
        Courier::DRIVER_PATHAO,
    ];

    public function authorize(): bool
    {
        return $this->user()?->can('couriers.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable', 'string', 'max:255', 'alpha_dash',
                Rule::unique('couriers', 'slug')->ignore($this->route('courier')),
            ],
            'driver' => ['required', Rule::in(self::SELECTABLE_DRIVERS)],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
            'position_order' => ['nullable', 'integer', 'min:0'],
            'sandbox' => ['boolean'],

            // Credentials (all nullable; a blank field on update keeps the stored
            // secret). Which ones apply depends on the driver — the controller
            // only persists the keys relevant to the chosen driver.
            // Steadfast:
            'api_key' => ['nullable', 'string', 'max:255'],
            'secret_key' => ['nullable', 'string', 'max:255'],
            // RedX:
            'access_token' => ['nullable', 'string', 'max:2000'],
            'pickup_store_id' => ['nullable', 'string', 'max:64'],
            // Pathao:
            'client_id' => ['nullable', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'store_id' => ['nullable', 'string', 'max:64'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'is_default' => $this->boolean('is_default'),
            'sandbox' => $this->boolean('sandbox'),
            'position_order' => $this->input('position_order', 0),
        ]);
    }
}
