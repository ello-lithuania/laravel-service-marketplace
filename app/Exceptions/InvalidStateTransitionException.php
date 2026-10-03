<?php

namespace App\Exceptions;

use App\Enums\OfferStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\SubscriptionStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use RuntimeException;

/**
 * Bandymas pereiti į būseną, kurios būsenų mašina neleidžia (docs/STATES.md), pvz. priimti pasiūlymą
 * jau vykdomai užklausai. Dažniausiai taip nutinka, kai būsena pasikeitė tarp puslapio atidarymo ir paspaudimo
 * (kitas skirtukas, dvigubas paspaudimas, lygiagretus veiksmas).
 */
class InvalidStateTransitionException extends RuntimeException
{
    public static function for(
        ServiceRequestStatus|OfferStatus|SubscriptionStatus $from,
        ServiceRequestStatus|OfferStatus|SubscriptionStatus $to,
        string $subject = 'Užklausos',
    ): self {
        return new self("{$subject} būsenos „{$from->label()}\" negalima pakeisti į „{$to->label()}\". Atnaujinkite puslapį.");
    }

    /**
     * Laravel pats kviečia render(), kai išimtis nepagauta: vartotojui – grįžimas atgal su klaidos pranešimu,
     * o ne 500 klaidos puslapis. https://laravel.com/docs/13.x/errors#renderable-exceptions
     */
    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage()], 409);
        }

        Inertia::flash('toast', ['type' => 'error', 'message' => $this->getMessage()]);

        return back();
    }
}
