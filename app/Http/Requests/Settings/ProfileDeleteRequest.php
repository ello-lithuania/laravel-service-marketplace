<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileDeleteRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Etapas 8: administratoriaus paskyros ištrinti negalima (taip neliktų kam administruoti).
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === false;
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException(__('privacy.delete.admin_forbidden'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password' => $this->currentPasswordRules(),
        ];
    }
}
