<?php

namespace App\Http\Requests\Owner;

use App\Enums\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IngredientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // role is enforced by the route middleware
    }

    /**
     * Unit and opening stock can only be set when the ingredient is created:
     * changing the unit later would change the meaning of existing stock and recipes,
     * and later stock changes go through restock/adjustment (phase 3).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('post');

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('ingredients')->ignore($this->route('ingredient'))],
            'min_stock' => ['required', 'decimal:0,2', 'min:0', 'max:9999999999'],
            'cost_per_unit' => ['required', 'decimal:0,2', 'min:0', 'max:99999999'],
            ...$creating ? [
                'unit' => ['required', Rule::enum(Unit::class)],
                'initial_stock' => ['required', 'decimal:0,2', 'min:0', 'max:9999999999'],
            ] : [],
        ];
    }
}
