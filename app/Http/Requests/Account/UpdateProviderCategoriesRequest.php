<?php

namespace App\Http\Requests\Account;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Vedlio 2 žingsnis: pasirinktos kategorijos (bet kurio lygio, docs/DB_SCHEMA.md 2.2).
 *
 * Čia tikrinama tik formos struktūra. Kiek kategorijų galima turėti, priklauso nuo prenumeratos ir dabartinio
 * pasirinkimo (Etapas 9c) – tai tikrina SyncProviderCategories.
 */
class UpdateProviderCategoriesRequest extends FormRequest
{
    /**
     * Techninė apsauga nuo milžiniškų užklausų (kiekvienam ID – exists patikra DB). Gerokai daugiau nei didžiausia
     * plano riba, todėl paprastam teikėjui niekada nesuveikia.
     */
    public const MAX_SUBMITTED_IDS = 300;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category_ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_SUBMITTED_IDS],
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
            'category_ids.max' => 'Pasirinkta per daug kategorijų. Vietoj kelių smulkių pasirinkite visą jų grupę.',
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
