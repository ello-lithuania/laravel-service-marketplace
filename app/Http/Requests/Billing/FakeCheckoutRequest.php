<?php

namespace App\Http\Requests\Billing;

use App\Enums\PaymentStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Testinio mokėjimų tiekėjo mygtukai: „Apmokėti", „Nepavyko", „Atšaukti".
 */
class FakeCheckoutRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'result' => ['required', Rule::in([PaymentStatus::Paid->value, PaymentStatus::Failed->value, PaymentStatus::Cancelled->value])],
        ];
    }

    public function result(): PaymentStatus
    {
        return PaymentStatus::from($this->string('result')->toString());
    }
}
