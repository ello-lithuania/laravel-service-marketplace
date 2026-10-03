<?php

namespace App\Http\Requests\Complaints;

use App\Enums\ComplaintReason;
use App\Enums\ReportableType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Skundas: ką skundžiam (type + id), kodėl (reason) ir neprivalomas aprašymas.
 * Ar vartotojas gali skųsti būtent šį įrašą, tikrina ComplaintPolicy controller'yje – po validacijos,
 * kai jau žinom, kad tipas teisingas ir įrašas egzistuoja.
 */
class StoreComplaintRequest extends FormRequest
{
    private ?Model $reportable = null;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(ReportableType::class)],
            'id' => ['required', 'integer', 'min:1'],
            'reason' => ['required', Rule::enum(ComplaintReason::class)],
            'description' => ['nullable', 'string', 'max:1000', 'required_if:reason,'.ComplaintReason::Other->value],
        ];
    }

    /**
     * Po pagrindinių taisyklių: ar toks įrašas yra (paslėpta žinutė ar ištrinta užklausa – nebėra).
     * https://laravel.com/docs/13.x/validation#performing-additional-validation-on-form-requests
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $this->reportable = ReportableType::from($this->string('type')->toString())->find($this->integer('id'));

                if ($this->reportable === null) {
                    $validator->errors()->add('id', __('complaints.validation.not_found'));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        /** @var array<string, string> */
        return __('complaints.attributes');
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'description.required_if' => __('complaints.validation.description_required'),
        ];
    }

    public function reportable(): Model
    {
        if ($this->reportable === null) {
            abort(404);
        }

        return $this->reportable;
    }

    public function reason(): ComplaintReason
    {
        return ComplaintReason::from($this->string('reason')->toString());
    }

    public function description(): ?string
    {
        $description = trim($this->string('description')->toString());

        return $description === '' ? null : $description;
    }
}
