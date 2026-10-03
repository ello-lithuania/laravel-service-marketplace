<?php

namespace App\Http\Requests\ServiceRequests;

use App\Concerns\ImageValidationRules;
use App\Models\ServiceRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

/**
 * Nuotraukų pridėjimas prie jau sukurtos užklausos (Etapas 6). Iš viso – ne daugiau ServiceRequest::MAX_PHOTOS.
 */
class StoreServiceRequestPhotosRequest extends FormRequest
{
    use ImageValidationRules;

    public function authorize(): bool
    {
        return Gate::allows('updatePhotos', $this->serviceRequest());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $remaining = max(0, ServiceRequest::MAX_PHOTOS - $this->serviceRequest()->getMedia('photos')->count());

        return [
            'photos' => ['required', 'array', 'max:'.$remaining],
            'photos.*' => $this->imageRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photos.required' => __('service_requests.validation.photos_required'),
            'photos.max' => __('service_requests.validation.photos_max', ['max' => ServiceRequest::MAX_PHOTOS]),
            ...$this->imageMessages('photos.*'),
        ];
    }

    public function serviceRequest(): ServiceRequest
    {
        /** @var ServiceRequest */
        return $this->route('serviceRequest');
    }

    /**
     * @return list<UploadedFile>
     */
    public function photos(): array
    {
        return array_values(Arr::wrap($this->file('photos')));
    }
}
