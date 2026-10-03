<?php

namespace App\Enums;

use App\Enums\Concerns\HasFilamentLabel;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Ką galima skųsti (Etapas 6). Reikšmės – tie patys trumpi vardai kaip morph map'e (AppServiceProvider),
 * todėl complaints.reportable_type DB'e = šio enum'o reikšmė (docs/DB_SCHEMA.md 2.13).
 * Vartotojo (user) kol kas neskundžiam tiesiogiai – skundžiamas jo turinys (užklausa, žinutė, profilis).
 */
enum ReportableType: string implements HasLabel
{
    use HasFilamentLabel;

    case ServiceRequest = 'service_request';
    case Offer = 'offer';
    case Review = 'review';
    case Message = 'message';
    case ProviderProfile = 'provider_profile';

    public function label(): string
    {
        return match ($this) {
            self::ServiceRequest => 'Užklausa',
            self::Offer => 'Pasiūlymas',
            self::Review => 'Atsiliepimas',
            self::Message => 'Žinutė',
            self::ProviderProfile => 'Teikėjo profilis',
        };
    }

    /**
     * Modelio klasė iš morph map'o (pvz. „review" → App\Models\Review).
     *
     * @return class-string<Model>
     */
    public function modelClass(): string
    {
        /** @var class-string<Model> */
        return Relation::getMorphedModel($this->value);
    }

    /**
     * Skundžiamas įrašas pagal tipą ir ID (arba null, jei tokio nėra ar jis paslėptas).
     */
    public function find(int $id): ?Model
    {
        return $this->modelClass()::query()->find($id);
    }
}
