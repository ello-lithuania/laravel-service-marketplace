<?php

namespace App\Http\Requests\Reviews;

use App\Models\Review;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Teikėjo viešas atsakymas į atsiliepimą (vieną kartą – ReviewPolicy::reply).
 */
class ReplyToReviewRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('reply', $this->review());
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['reply' => trim($this->string('reply')->toString())]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reply' => ['required', 'string', 'min:2', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        /** @var array<string, string> */
        return __('reviews.attributes');
    }

    public function review(): Review
    {
        /** @var Review */
        return $this->route('review');
    }
}
