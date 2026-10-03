<?php

namespace App\Services\Moderation;

use App\Models\ServiceRequest;
use App\Models\User;

/**
 * Automatinio moderavimo taisyklės (pending → open be administratoriaus).
 *
 * Užklausa paskelbiama iškart, kai issues() grąžina tuščią sąrašą. Kitaip ji lieka „pending" ir ją peržiūri
 * administratorius Filament panelėje, kur rodomos tos pačios priežastys.
 * Kontaktų tekste draudžiam, kad klientas ir teikėjas nesusitartų aplenkdami platformą (ir dėl šlamšto).
 */
class AutoModerator
{
    public const UNVERIFIED_EMAIL = 'Kliento el. paštas nepatvirtintas';

    public const BANNED_CLIENT = 'Klientas užblokuotas';

    public const CONTAINS_URL = 'Tekste yra nuoroda';

    public const CONTAINS_EMAIL = 'Tekste yra el. pašto adresas';

    public const CONTAINS_PHONE = 'Tekste yra telefono numeris';

    /**
     * Kodėl užklausos negalima paskelbti automatiškai. Tuščias sąrašas – galima.
     *
     * @return list<string>
     */
    public function issues(ServiceRequest $request, User $client): array
    {
        $issues = [];

        if (! $client->hasVerifiedEmail()) {
            $issues[] = self::UNVERIFIED_EMAIL;
        }

        if ($client->banned_at !== null) {
            $issues[] = self::BANNED_CLIENT;
        }

        return [...$issues, ...$this->textIssues($request->title.' '.$request->description.' '.$request->address)];
    }

    /**
     * Teksto taisyklės atskirai – jas galima naudoti ir kitur (pvz. pasiūlymams, žinutėms).
     *
     * @return list<string>
     */
    public function textIssues(string $text): array
    {
        $issues = [];

        // http(s)://…, www.… arba domenas su populiaria galūne (pvz. „meistras.lt")
        if (preg_match('~(https?://|www\.)\S+|\b[\pL0-9-]+\.(lt|com|eu|net|org|info|io)\b~iu', $text) === 1) {
            $issues[] = self::CONTAINS_URL;
        }

        if (preg_match('~[\pL0-9._%+-]+@[\pL0-9.-]+\.\pL{2,}~u', $text) === 1) {
            $issues[] = self::CONTAINS_EMAIL;
        }

        // Bent 8 skaitmenys, tarp kurių gali būti tarpai, brūkšniai ar skliaustai: +370 612 34567, 8-612-34567
        if (preg_match('~(?:\+?\d[\s\-().]{0,2}){8,}~', $text) === 1) {
            $issues[] = self::CONTAINS_PHONE;
        }

        return $issues;
    }
}
