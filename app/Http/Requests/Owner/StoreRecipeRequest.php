<?php

namespace App\Http\Requests\Owner;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecipeRequest extends FormRequest
{
    protected $errorBag = 'recipe';

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
            'ingredient_id' => [
                'required',
                'integer',
                'exists:ingredients,id',
                Rule::unique('recipes')->where('product_id', $this->route('product')->id),
            ],
            'quantity' => ['required', 'decimal:0,2', 'gt:0', 'max:99999999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ingredient_id.unique' => 'Bahan ini sudah ada di resep. Ubah jumlahnya di daftar resep.',
        ];
    }
}
