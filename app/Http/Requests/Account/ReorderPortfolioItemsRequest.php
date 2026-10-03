<?php

namespace App\Http\Requests\Account;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Nauja atliktų darbų tvarka: ids = [3, 1, 2].
 */
class ReorderPortfolioItemsRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $profileId = $this->user()?->providerProfile?->id;

        return [
            'ids' => ['required', 'array'],
            // Tik savi darbai – svetimo darbo ID validacija atmes
            'ids.*' => [
                'integer', 'distinct',
                Rule::exists('portfolio_items', 'id')->where('provider_profile_id', $profileId),
            ],
        ];
    }

    /**
     * @return list<int>
     */
    public function orderedIds(): array
    {
        return array_values(array_map(intval(...), (array) $this->validated('ids')));
    }
}
