<?php

namespace App\Http\Requests\Account;

use App\Enums\ProviderType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Vedlio 1 žingsnis: teikėjo duomenys.
 * Autorizacija – maršrutuose (role:provider) ir controller'yje (Policy), todėl authorize() čia nėra.
 */
class UpdateProviderDetailsRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(ProviderType::class)],
            'display_name' => ['required', 'string', 'min:2', 'max:150'],
            'company_code' => ['nullable', 'required_if:type,'.ProviderType::Company->value, 'regex:/^\d{9}$/'],
            'vat_code' => ['nullable', 'regex:/^LT(\d{9}|\d{12})$/'],
            'city_id' => ['required', 'integer', Rule::exists('cities', 'id')],
            'years_experience' => ['nullable', 'integer', 'min:0', 'max:80'],
            'headline' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^\+370\d{8}$/'],
        ];
    }

    /**
     * Prieš validaciją sutvarkom įvestį, kad žmogui nereikėtų spėlioti formato:
     * „8 612 34567" → „+37061234567", „lt 123456789" → „LT123456789", „www.x.lt" → „https://www.x.lt".
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => $this->normalizePhone($this->input('phone')),
            'company_code' => $this->withoutSpaces($this->input('company_code')),
            'vat_code' => is_string($code = $this->withoutSpaces($this->input('vat_code'))) ? strtoupper($code) : $code,
            'website' => $this->withScheme($this->input('website')),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'company_code.required_if' => 'Įmonei įmonės kodas privalomas.',
            'company_code.regex' => 'Įmonės kodą sudaro 9 skaitmenys.',
            'vat_code.regex' => 'PVM mokėtojo kodas – „LT" ir 9 arba 12 skaitmenų.',
            'phone.regex' => 'Įveskite Lietuvos telefono numerį, pvz. +370 612 34567 arba 8 612 34567.',
        ];
    }

    /**
     * Lietuviški laukų pavadinimai klaidų pranešimams („Laukas rodomas pavadinimas privalomas").
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type' => 'teikėjo tipas',
            'display_name' => 'rodomas pavadinimas',
            'company_code' => 'įmonės kodas',
            'vat_code' => 'PVM mokėtojo kodas',
            'city_id' => 'miestas',
            'years_experience' => 'patirtis metais',
            'headline' => 'trumpas prisistatymas',
            'description' => 'aprašymas',
            'website' => 'svetainė',
            'phone' => 'telefonas',
        ];
    }

    private function normalizePhone(mixed $phone): mixed
    {
        if (! is_string($phone)) {
            return $phone;
        }

        $digits = (string) preg_replace('/[\s\-()]/', '', $phone);

        // Vietinis formatas „8 6xx xxxxx" ir be pliuso „370…" → tarptautinis E.164 (docs/DB_SCHEMA.md → users.phone)
        if (preg_match('/^(?:8|\+?370)(\d{8})$/', $digits, $matches) === 1) {
            return '+370'.$matches[1];
        }

        return $digits;
    }

    private function withoutSpaces(mixed $value): mixed
    {
        return is_string($value) ? (string) preg_replace('/\s+/', '', $value) : $value;
    }

    private function withScheme(mixed $url): mixed
    {
        if (! is_string($url) || $url === '' || preg_match('#^https?://#i', $url) === 1) {
            return $url;
        }

        return 'https://'.$url;
    }
}
