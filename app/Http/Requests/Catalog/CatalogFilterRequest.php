<?php

namespace App\Http\Requests\Catalog;

use App\Enums\ProviderSort;
use App\Services\Catalog\CatalogCache;
use App\Services\Catalog\ProviderFilters;
use App\Services\Catalog\SearchTerms;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Katalogo filtrų URL parametrai: ?q=…&miestas=vilnius&patikrinti=1&reitingas=4&rikiuoti=atsiliepimai.
 *
 * Skirtumas nuo įprastos formos: neteisingas parametras (sena nuoroda, ranka pakeistas URL) neturi
 * nukreipti atgal su klaida – tokią reikšmę tiesiog ignoruojam. Todėl failedValidation() išimties nemeta,
 * o filters() ima tik validacijos praėjusias reikšmes.
 *
 * https://laravel.com/docs/13.x/validation#form-request-validation
 */
class CatalogFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:200'],
            // Savivaldybių sąrašas iš cache – be papildomos DB užklausos (exists:cities,slug jos reikalautų)
            'miestas' => ['nullable', 'string', Rule::in(app(CatalogCache::class)->geography()->citySlugs())],
            'patikrinti' => ['nullable', 'boolean'],
            'reitingas' => ['nullable', 'numeric', 'between:1,5'],
            'rikiuoti' => ['nullable', Rule::enum(ProviderSort::class)],
        ];
    }

    /** @var list<string> validacijos nepraėję parametrai */
    private array $invalid = [];

    /**
     * Įprastai čia metama ValidationException (nukreipimas atgal su klaidomis). Mes tik įsimenam,
     * kurie parametrai neteisingi, ir filters() juos ignoruoja.
     */
    protected function failedValidation(Validator $validator): void
    {
        $this->invalid = array_values($validator->errors()->keys());
    }

    public function filters(ProviderSort $defaultSort = ProviderSort::Rating): ProviderFilters
    {
        $search = SearchTerms::parse($this->validValue('q'));
        $sort = ProviderSort::tryFrom($this->validValue('rikiuoti') ?? '') ?? $defaultSort;
        $citySlug = $this->validValue('miestas');
        $rating = $this->validValue('reitingas');

        return new ProviderFilters(
            city: $citySlug === null ? null : app(CatalogCache::class)->geography()->findCityBySlug($citySlug),
            verifiedOnly: filter_var($this->validValue('patikrinti'), FILTER_VALIDATE_BOOLEAN),
            minRating: $rating === null ? null : (float) $rating,
            // „Tinkamiausi" be paieškos teksto neturi prasmės
            sort: $sort === ProviderSort::Relevance && $search === null ? ProviderSort::Rating : $sort,
            search: $search,
        );
    }

    /**
     * Parametro reikšmė, jei ji praėjo validaciją; kitaip null.
     */
    private function validValue(string $key): ?string
    {
        $value = in_array($key, $this->invalid, true) ? null : $this->query($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
