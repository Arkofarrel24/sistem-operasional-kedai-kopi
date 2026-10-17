<?php

namespace App\Http\Requests\Owner;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // role is enforced by the route middleware
    }

    protected function prepareForValidation(): void
    {
        // One bag per recipe row so the message shows under the row that failed.
        $this->errorBag = 'recipe-'.$this->route('recipe')->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'decimal:0,2', 'gt:0', 'max:99999999'],
        ];
    }
}
