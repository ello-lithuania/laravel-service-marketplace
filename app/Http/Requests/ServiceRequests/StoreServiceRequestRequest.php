<?php

namespace App\Http\Requests\ServiceRequests;

use App\Enums\StartPreference;
use App\Models\Category;
use App\Models\ServiceRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Užklausos kūrimo formos validacija. Forma daugiažingsnė: kiekvieno žingsnio laukai tikrinami per
 * Precognition (tos pačios taisyklės, tik be išsaugojimo), o visa forma – išsiunčiant.
 * https://laravel.com/docs/13.x/validation#form-request-validation · https://laravel.com/docs/13.x/precognition
 *
 * Biudžetas formoje – sveikais eurais (žmonėms patogiau), DB – centais (toServiceRequestAttributes()).
 */
class StoreServiceRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', ServiceRequest::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Tik aktyvi 3 lygio kategorija (užklausos visada priskiriamos lapui – docs/DB_SCHEMA.md 2.2)
            'category_id' => ['required', 'integer', Rule::exists(Category::class, 'id')
                ->where('depth', Category::MAX_DEPTH)
                ->where('is_active', true)],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'title' => ['required', 'string', 'min:10', 'max:150'],
            'description' => ['required', 'string', 'min:30', 'max:5000'],
            'address' => ['nullable', 'string', 'max:255'],
            'budget_min' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            // gte lyginam tik kai „nuo" nurodytas – kitaip Laravel lygintų su null ir visada atmestų
            'budget_max' => ['nullable', 'integer', 'min:1', 'max:1000000', Rule::when($this->filled('budget_min'), 'gte:budget_min')],
            'start_preference' => ['required', Rule::enum(StartPreference::class)],
            'start_date' => [
                'nullable',
                'required_if:start_preference,'.StartPreference::Date->value,
                'date',
                'after_or_equal:today',
                'before_or_equal:'.now()->addYear()->toDateString(),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        /** @var array<string, string> */
        return __('service_requests.attributes');
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_id.exists' => __('service_requests.validation.category_leaf'),
            'budget_max.gte' => __('service_requests.validation.budget_max_gte'),
            'start_date.required_if' => __('service_requests.validation.start_date_required'),
        ];
    }

    /**
     * Validuoti laukai, paversti modelio atributais (eurai → centai, data – tik kai pasirinkta „Konkrečią dieną").
     *
     * @return array{category_id: int, city_id: int, title: string, description: string, address: ?string,
     *     budget_min_cents: ?int, budget_max_cents: ?int, start_preference: string, start_date: ?string}
     */
    public function toServiceRequestAttributes(): array
    {
        $startPreference = $this->string('start_preference')->toString();

        return [
            'category_id' => $this->integer('category_id'),
            'city_id' => $this->integer('city_id'),
            'title' => $this->string('title')->squish()->toString(),
            'description' => trim($this->string('description')->toString()),
            'address' => $this->filled('address') ? $this->string('address')->squish()->toString() : null,
            'budget_min_cents' => $this->filled('budget_min') ? $this->integer('budget_min') * 100 : null,
            'budget_max_cents' => $this->filled('budget_max') ? $this->integer('budget_max') * 100 : null,
            'start_preference' => $startPreference,
            'start_date' => $startPreference === StartPreference::Date->value ? $this->string('start_date')->toString() : null,
        ];
    }
}
