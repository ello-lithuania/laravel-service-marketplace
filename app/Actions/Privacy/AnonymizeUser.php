<?php

namespace App\Actions\Privacy;

use App\Actions\Offers\WithdrawOffer;
use App\Actions\ServiceRequests\CancelServiceRequest;
use App\Actions\Subscriptions\CancelSubscription;
use App\Enums\OfferStatus;
use App\Enums\ProviderStatus;
use App\Enums\ProviderType;
use App\Enums\ServiceRequestStatus;
use App\Enums\SubscriptionStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\Offer;
use App\Models\PortfolioItem;
use App\Models\ProviderProfile;
use App\Models\ServiceRequest;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Privacy\DataExportStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Paskyros ištrynimas pagal BDAR = anonimizavimas (docs/DB_SCHEMA.md → „BDAR: saugojimas ir anonimizavimas").
 *
 * Kodėl ne tikras DELETE: užklausos, pasiūlymai, atsiliepimai, mokėjimai ir kreditų istorija – kitų žmonių ir
 * buhalterijos įrašai (FK restrict neleistų jų ištrinti, o ištrynus sugriūtų kitų istorija). Todėl pašalinam tai,
 * kas identifikuoja žmogų (vardas, el. paštas, telefonas, nuotraukos, adresas, pranešimai), o įrašus paliekam su
 * „Ištrintas vartotojas". users eilutė pažymima soft delete – prisijungti nebeįmanoma, o tuo pačiu el. paštu
 * galima registruotis iš naujo (el. paštas pakeistas į deleted-{id}@example.invalid).
 *
 * Tvarka:
 *  1. verslo perėjimai per esamas Actions (savo transakcijose, su kreditų taisyklėmis iš docs/STATES.md):
 *     kliento laukiančios / atviros / vykdomos užklausos atšaukiamos, teikėjo laukiantys pasiūlymai atšaukiami;
 *  2. viena DB transakcija: asmens duomenų pakeitimas, profilio paslėpimas, pranešimų ir sesijų ištrynimas;
 *  3. PO transakcijos – failai (nuotraukos): failų ištrynimo atšaukti negalima, todėl jie trinami tik kai DB
 *     pakeitimai jau patvirtinti.
 */
final class AnonymizeUser
{
    public function __construct(
        private readonly CancelServiceRequest $cancelServiceRequest,
        private readonly WithdrawOffer $withdrawOffer,
        private readonly CancelSubscription $cancelSubscription,
        private readonly DataExportStorage $exports,
    ) {}

    public function handle(User $user): void
    {
        if ($user->isAdmin()) {
            throw new InvalidArgumentException(__('privacy.delete.admin_forbidden'));
        }

        $this->closeOpenBusiness($user);

        $originalEmail = $user->email;
        $profile = ProviderProfile::query()->where('user_id', $user->id)->first();

        DB::transaction(function () use ($user, $profile, $originalEmail): void {
            $this->anonymizeAccount($user);

            if ($profile !== null) {
                $this->anonymizeProviderProfile($profile);
            }

            // Užklausų adresai – privatūs duomenys; pačios užklausos lieka teikėjų istorijai
            ServiceRequest::withTrashed()->where('client_id', $user->id)->update(['address' => null]);

            // Pranešimai – tik šiam žmogui skirti ir be verslo vertės
            $user->notifications()->delete();

            DB::table('password_reset_tokens')->where('email', $originalEmail)->delete();
            $this->deleteDatabaseSessions($user);

            $user->delete();
        });

        $this->deleteFiles($user, $profile);
        // Anksčiau paruošti BDAR archyvai – juose visi asmens duomenys
        $this->exports->deleteAll($user);
    }

    /**
     * Atšaukia tai, kas dar vyksta: kitaip teikėjai lauktų atsakymo iš nebesančio kliento,
     * o klientai galėtų priimti nebesančio teikėjo pasiūlymą.
     */
    private function closeOpenBusiness(User $user): void
    {
        $requests = ServiceRequest::query()
            ->where('client_id', $user->id)
            ->whereIn('status', [ServiceRequestStatus::Pending, ServiceRequestStatus::Open, ServiceRequestStatus::InProgress])
            ->get();

        foreach ($requests as $request) {
            $this->ignoringRaces(fn () => $this->cancelServiceRequest->handle($request, $user, __('privacy.request_cancel_reason')));
        }

        $offers = Offer::query()
            ->whereHas('providerProfile', fn (Builder $query) => $query->where('user_id', $user->id))
            ->where('status', OfferStatus::Pending)
            ->whereHas('serviceRequest', fn (Builder $query) => $query->where('status', ServiceRequestStatus::Open))
            ->get();

        foreach ($offers as $offer) {
            $this->ignoringRaces(fn () => $this->withdrawOffer->handle($offer));
        }
    }

    private function anonymizeAccount(User $user): void
    {
        // forceFill: dalis laukų nėra Fillable (jų per formą keisti negalima)
        $user->forceFill([
            'first_name' => __('privacy.deleted_user_name'),
            'last_name' => '',
            'email' => "deleted-{$user->id}@example.invalid",
            'phone' => null,
            // Atsitiktinis slaptažodis: net atkūrus įrašą, senuoju slaptažodžiu prisijungti nepavyktų
            'password' => Hash::make(Str::random(64)),
            'remember_token' => null,
            'email_verified_at' => null,
            'city_id' => null,
            'notification_settings' => null,
            'last_seen_at' => null,
        ])->save();
    }

    private function anonymizeProviderProfile(ProviderProfile $profile): void
    {
        $isCompany = $profile->type === ProviderType::Company;

        $profile->forceFill([
            'display_name' => __('privacy.deleted_provider_name'),
            'headline' => null,
            'description' => null,
            'website' => null,
            'years_experience' => null,
            // Įmonės kodas ir PVM kodas – juridinio asmens (ne asmens) duomenys, reikalingi sąskaitoms;
            // fizinio asmens atveju (individuali veikla) tai asmens duomenys – trinami
            'company_code' => $isCompany ? $profile->company_code : null,
            'vat_code' => $isCompany ? $profile->vat_code : null,
            'status' => ProviderStatus::Hidden,
            'verified_at' => null,
        ])->save();

        $profile->categories()->detach();
        $profile->serviceAreas()->detach();

        // Prenumeratos daugiau nepratęsiamos – per tą pačią Etapo 7 Action klasę, kuri atšaukia ir laukiančius
        // pratęsimo mokėjimus (pinigai už einamąjį laikotarpį negrąžinami, žr. CancelSubscription)
        Subscription::query()
            ->where('provider_profile_id', $profile->id)
            ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::PastDue])
            ->get()
            ->each(fn (Subscription $subscription) => $this->cancelSubscription->handle($subscription));

        $profile->delete();
    }

    /**
     * Nuotraukos ir priedai: avataras, logotipas, viršelis, atlikti darbai, užklausų nuotraukos, žinučių priedai.
     * Media::delete() ištrina ir failą diske, ir miniatiūras.
     */
    private function deleteFiles(User $user, ?ProviderProfile $profile): void
    {
        $owners = [
            'user' => [$user->id],
            'provider_profile' => $profile === null ? [] : [$profile->id],
            'service_request' => ServiceRequest::withTrashed()->where('client_id', $user->id)->pluck('id')->all(),
            'message' => DB::table('messages')->where('sender_id', $user->id)->pluck('id')->all(),
        ];

        foreach ($owners as $type => $ids) {
            if ($ids === []) {
                continue;
            }

            Media::query()->where('model_type', $type)->whereIn('model_id', $ids)->get()->each(fn (Media $media) => $media->delete());
        }

        // Atlikti darbai – teikėjo vieša reklama, be profilio prasmės neturi; trinant modelį medialibrary ištrina ir nuotraukas
        if ($profile !== null) {
            PortfolioItem::query()->where('provider_profile_id', $profile->id)->get()->each(fn (PortfolioItem $item) => $item->delete());
        }
    }

    private function deleteDatabaseSessions(User $user): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->delete();
    }

    /**
     * Jei būsena lygiagrečiai pasikeitė (pvz. užklausa ką tik pasibaigė) – tiesiog praleidžiam.
     */
    private function ignoringRaces(callable $callback): void
    {
        try {
            $callback();
        } catch (InvalidStateTransitionException) {
            // nieko: įrašas jau galutinėje būsenoje
        }
    }
}
