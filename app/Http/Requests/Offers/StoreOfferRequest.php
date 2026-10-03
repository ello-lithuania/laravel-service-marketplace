<?php

namespace App\Http\Requests\Offers;

use App\Enums\OfferPriceType;
use App\Models\Offer;
use App\Models\ServiceRequest;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Pasiūlymo forma. Teisę siųsti (rolė, tinkamumas, ar dar nesiųsta) tikrina OfferPolicy::create,
 * kreditus ir būseną dar kartą – SendOffer, jau užrakinusi eilutes.
 * Kaina formoje – eurais (su centais), DB – centais.
 */
class StoreOfferRequest extends FormRequest
{
    /**
     * Gate::inspect grąžina Response su priežastimi – atsisakymo atveju vartotojas mato, KODĖL negalima.
     */
    public function authorize(): Response
    {
        return Gate::inspect('create', [Offer::class, $this->serviceRequest()]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'min:20', 'max:3000'],
            'price_type' => ['required', Rule::enum(OfferPriceType::class)],
            'price' => [
                Rule::requiredIf($this->input('price_type') !== OfferPriceType::AfterInspection->value),
                'nullable',
                'numeric',
                'min:0',
                'max:1000000',
                'decimal:0,2',
            ],
            'duration_text' => ['nullable', 'string', 'max:100'],
            'start_date' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        /** @var array<string, string> */
        return __('offers.attributes');
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['price.required' => __('offers.validation.price_required')];
    }

    public function serviceRequest(): ServiceRequest
    {
        /** @var ServiceRequest */
        return $this->route('serviceRequest');
    }

    /**
     * @return array{message: string, price_cents: ?int, price_type: string, duration_text: ?string, start_date: ?string}
     */
    public function toOfferAttributes(): array
    {
        return [
            'message' => trim($this->string('message')->toString()),
            // round(), nes 12.3 * 100 = 1229.9999… (float netikslumas – docs/DB_SCHEMA.md 2.7)
            'price_cents' => $this->filled('price') ? (int) round((float) $this->input('price') * 100) : null,
            'price_type' => $this->string('price_type')->toString(),
            'duration_text' => $this->filled('duration_text') ? $this->string('duration_text')->squish()->toString() : null,
            'start_date' => $this->filled('start_date') ? $this->string('start_date')->toString() : null,
        ];
    }
}
