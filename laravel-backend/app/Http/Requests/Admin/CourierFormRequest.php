<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Courier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create/update a courier. Credentials arrive as plain inputs and are stored in
 * the model's encrypted `config` by the controller (blank keeps the stored
 * secret). Phase 1 exposes the Manual and Steadfast drivers; RedX/Pathao are
 * added in later phases.
 */
class CourierFormRequest extends FormRequest
{
    /** Drivers selectable in the admin form for this phase. */
    public const SELECTABLE_DRIVERS = [Courier::DRIVER_MANUAL, Courier::DRIVER_STEADFAST];

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

            // Steadfast credentials (nullable; blank keeps the stored value).
            'api_key' => ['nullable', 'string', 'max:255'],
            'secret_key' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'is_default' => $this->boolean('is_default'),
            'position_order' => $this->input('position_order', 0),
        ]);
    }
}
