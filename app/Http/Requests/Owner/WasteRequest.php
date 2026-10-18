<?php

namespace App\Http\Requests\Owner;

use Illuminate\Foundation\Http\FormRequest;

class WasteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // role is enforced by the route middleware
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'decimal:0,2', 'gt:0', 'max:9999999999'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
