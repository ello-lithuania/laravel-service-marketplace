<?php

namespace App\Http\Requests\ServiceRequests;

use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Užklausos atšaukimas. Vykdomai (in_progress) užklausai priežastis privaloma (docs/STATES.md 1 sk.).
 */
class CancelServiceRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('cancel', $this->serviceRequest());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => [
                Rule::requiredIf($this->serviceRequest()->status === ServiceRequestStatus::InProgress),
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['reason' => __('service_requests.attributes.reason')];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['reason.required' => __('service_requests.cancel.reason_required')];
    }

    public function serviceRequest(): ServiceRequest
    {
        /** @var ServiceRequest */
        return $this->route('serviceRequest');
    }
}
