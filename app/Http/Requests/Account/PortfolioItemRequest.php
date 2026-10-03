<?php

namespace App\Http\Requests\Account;

use App\Concerns\ImageValidationRules;
use App\Models\PortfolioItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/**
 * Atlikto darbo kūrimas ir redagavimas (tas pats Form Request abiem atvejams).
 * Teises (ar tai tavo darbas) tikrina Policy maršrute: ->can('update', 'portfolioItem').
 */
class PortfolioItemRequest extends FormRequest
{
    use ImageValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $item = $this->route('portfolioItem');
        $item = $item instanceof PortfolioItem ? $item : null;

        // Kiek nuotraukų dar galima pridėti, kad iš viso būtų ne daugiau nei MAX_IMAGES
        $existingImages = $item?->getMedia('images')->count() ?? 0;
        $profileId = $this->user()?->providerProfile?->id;

        return [
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:3000'],
            // Kategorija – tik iš teikėjo pasirinktų (pivot lentelėje turi būti tokia eilutė)
            'category_id' => [
                'nullable', 'integer',
                Rule::exists('category_provider_profile', 'category_id')->where('provider_profile_id', $profileId),
            ],
            'city_id' => ['nullable', 'integer', Rule::exists('cities', 'id')],
            'completed_date' => ['nullable', 'date', 'before_or_equal:today'],
            // Naujam darbui bent viena nuotrauka privaloma, redaguojant – ne
            'images' => [$item === null ? 'required' : 'nullable', 'array', 'max:'.max(0, PortfolioItem::MAX_IMAGES - $existingImages)],
            'images.*' => $this->imageRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'images.required' => 'Įkelkite bent vieną nuotrauką.',
            'images.max' => 'Viename darbe gali būti daugiausia '.PortfolioItem::MAX_IMAGES.' nuotraukų.',
            'category_id.exists' => 'Pasirinkite vieną iš savo kategorijų.',
            ...$this->imageMessages('images.*'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'pavadinimas',
            'description' => 'aprašymas',
            'category_id' => 'kategorija',
            'city_id' => 'miestas',
            'completed_date' => 'atlikimo data',
            'images' => 'nuotraukos',
        ];
    }

    /**
     * Darbo laukai be failų (failai pridedami atskirai per medialibrary).
     *
     * @return array<string, mixed>
     */
    public function itemData(): array
    {
        return Arr::except($this->validated(), ['images']);
    }

    /**
     * @return list<UploadedFile>
     */
    public function images(): array
    {
        return array_values(Arr::wrap($this->file('images')));
    }
}
