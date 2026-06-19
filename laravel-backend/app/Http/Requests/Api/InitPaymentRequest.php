<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Starts a gateway payment for an existing order. The amount is derived
 * server-side from the order and the chosen type — never taken from the client.
 */
class InitPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // public: guest checkout pays from the success page
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'order_no' => ['required', 'string', Rule::exists('orders', 'order_no')],
            'type' => ['required', 'string', Rule::in(Payment::TYPES)],
        ];
    }
}
