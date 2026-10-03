<?php

namespace App\Http\Requests\Account;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Vedlio 2 žingsnis: pasirinktos kategorijos (bet kurio lygio, docs/DB_SCHEMA.md 2.2).
 */
class UpdateProviderCategoriesRequest extends FormRequest
{
    /** Daugiausia pasirinktų medžio mazgų (tėvas skaičiuojamas kaip vienas, kad ir kiek turėtų vaikų). */
    public const MAX_CATEGORIES = 30;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category_ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_CATEGORIES],
            'category_ids.*' => ['integer', 'distinct', Rule::exists('categories', 'id')->where('is_active', true)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_ids.required' => 'Pasirinkite bent vieną kategoriją.',
            'category_ids.min' => 'Pasirinkite bent vieną kategoriją.',
            'category_ids.max' => 'Galima pasirinkti daugiausia '.self::MAX_CATEGORIES.' kategorijų. Vietoj kelių smulkių pasirinkite visą jų grupę.',
            'category_ids.*.exists' => 'Pasirinkta kategorija neegzistuoja arba išjungta.',
        ];
    }

    /**
     * @return list<int>
     */
    public function categoryIds(): array
    {
        return array_values(array_map(intval(...), (array) $this->validated('category_ids')));
    }
}
