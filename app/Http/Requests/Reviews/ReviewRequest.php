<?php

namespace App\Http\Requests\Reviews;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Bendros atsiliepimo taisyklės (įvertinimas 1–5 ir tekstas). Teises tikrina vaikinės klasės authorize():
 * StoreVerifiedReviewRequest (po atlikto darbo) ir StoreInvitationReviewRequest (pagal pakvietimą).
 */
abstract class ReviewRequest extends FormRequest
{
    public const MIN_COMMENT = 20;

    public const MAX_COMMENT = 2000;

    protected function prepareForValidation(): void
    {
        $this->merge(['comment' => trim($this->string('comment')->toString())]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['required', 'string', 'min:'.self::MIN_COMMENT, 'max:'.self::MAX_COMMENT],
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

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rating.required' => __('reviews.validation.rating'),
            'rating.between' => __('reviews.validation.rating'),
            'rating.integer' => __('reviews.validation.rating'),
        ];
    }

    public function rating(): int
    {
        return $this->integer('rating');
    }

    public function comment(): string
    {
        return $this->string('comment')->toString();
    }
}
