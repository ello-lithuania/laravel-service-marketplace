<?php

namespace App\Http\Requests\Account;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Vedlio 3 žingsnis: aptarnavimo zonos – „visa Lietuva" arba bent viena savivaldybė.
 */
class UpdateProviderServiceAreasRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Kai pasirinkta „visa Lietuva", savivaldybių sąrašas nereikalingas – jį visai išmetam (exclude)
        $wholeCountry = Rule::excludeIf(fn (): bool => $this->boolean('serves_whole_country'));

        return [
            'serves_whole_country' => ['required', 'boolean'],
            'city_ids' => [$wholeCountry, 'required', 'array', 'min:1'],
            'city_ids.*' => [$wholeCountry, 'integer', 'distinct', Rule::exists('cities', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'city_ids.required' => 'Pažymėkite bent vieną miestą ar rajoną arba „Visa Lietuva".',
            'city_ids.min' => 'Pažymėkite bent vieną miestą ar rajoną arba „Visa Lietuva".',
        ];
    }

    /**
     * @return list<int>
     */
    public function cityIds(): array
    {
        return array_values(array_map(intval(...), (array) $this->validated('city_ids', [])));
    }
}
