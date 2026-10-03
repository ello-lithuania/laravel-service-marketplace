<?php

namespace App\Http\Requests\Account;

use App\Enums\PriceUnit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Vedlio 4 žingsnis: kainos „nuo". Kaina įvedama eurais („15" arba „15,50"), DB saugoma centais.
 */
class UpdateProviderPricesRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Kainą galima nurodyti tik savo pasirinktoms kategorijoms
        $ownCategoryIds = $this->user()?->providerProfile?->categories()->pluck('categories.id')->all() ?? [];

        return [
            'prices' => ['present', 'array'],
            'prices.*.category_id' => ['required', 'integer', 'distinct', Rule::in($ownCategoryIds)],
            'prices.*.price_from' => ['nullable', 'regex:/^\d{1,6}([.,]\d{1,2})?$/'],
            'prices.*.price_unit' => ['nullable', 'required_with:prices.*.price_from', Rule::enum(PriceUnit::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'prices.*.category_id.in' => 'Kainą galima nurodyti tik pasirinktoms kategorijoms.',
            'prices.*.price_from.regex' => 'Įveskite kainą eurais, pvz. 15 arba 15,50.',
            'prices.*.price_unit.required_with' => 'Pasirinkite, už ką ši kaina (val., m², darbas…).',
        ];
    }

    /**
     * @return list<array{category_id: int, price_from: string|null, price_unit: string|null}>
     */
    public function prices(): array
    {
        /** @var list<array{category_id: int|string, price_from?: int|float|string|null, price_unit?: string|null}> $prices */
        $prices = $this->validated('prices');

        return array_map(fn (array $price): array => [
            'category_id' => (int) $price['category_id'],
            'price_from' => isset($price['price_from']) ? (string) $price['price_from'] : null,
            'price_unit' => $price['price_unit'] ?? null,
        ], $prices);
    }
}
