<?php

namespace App\Actions\Complaints;

use App\Actions\Reviews\HideReview;
use App\Enums\ComplaintStatus;
use App\Exceptions\ComplaintAlreadyHandledException;
use App\Models\Complaint;
use App\Models\Message;
use App\Models\Review;
use App\Models\User;
use App\Notifications\ComplaintResolved;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Administratorius užbaigia skundą: resolved (pažeidimas patvirtintas) arba rejected (pažeidimo nėra).
 *
 * Patvirtinus galima iš karto paslėpti skundžiamą turinį: atsiliepimą – HideReview (perskaičiuojamas
 * reitingas), žinutę – soft delete (pokalbyje lieka „Žinutė paslėpta administratoriaus").
 * Pranešėjas gauna ComplaintResolved – po transakcijos.
 */
class CloseComplaint
{
    public function __construct(private readonly HideReview $hideReview) {}

    public function handle(Complaint $complaint, User $admin, ComplaintStatus $outcome, string $note, bool $hideContent = false): Complaint
    {
        if (! in_array($outcome, [ComplaintStatus::Resolved, ComplaintStatus::Rejected], true)) {
            throw new InvalidArgumentException('Skundą galima tik išspręsti arba atmesti.');
        }

        DB::transaction(function () use ($complaint, $admin, $outcome, $note, $hideContent): void {
            $locked = Complaint::query()->whereKey($complaint->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->isOpen()) {
                throw ComplaintAlreadyHandledException::make();
            }

            $complaint->forceFill([
                'status' => $outcome,
                'resolution_note' => $note,
                'resolved_at' => now(),
            ])->handledBy()->associate($admin)->save();

            if ($outcome === ComplaintStatus::Resolved && $hideContent) {
                $this->hide($complaint);
            }
        });

        $complaint->loadMissing('reporter')->reporter?->notify(new ComplaintResolved($complaint));

        return $complaint;
    }

    /**
     * Paslėpti galima atsiliepimą ir žinutę; kitam turiniui (užklausai, profiliui) – savi įrankiai
     * (užklausos atšaukimas, profilio blokavimas – Etapas 8).
     */
    private function hide(Complaint $complaint): void
    {
        $reportable = $complaint->loadMissing('reportable')->reportable;

        if ($reportable instanceof Review) {
            $this->hideReview->handle($reportable);
        } elseif ($reportable instanceof Message) {
            $reportable->delete();
        }
    }
}
