<?php

namespace App\Http\Requests\Account;

use App\Concerns\ImageValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Vienos nuotraukos įkėlimas: avataras, logotipas, viršelis (laukas „image").
 */
class ImageUploadRequest extends FormRequest
{
    use ImageValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'image' => ['required', ...$this->imageRules()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.required' => 'Pasirinkite nuotrauką.',
            ...$this->imageMessages('image'),
        ];
    }
}
