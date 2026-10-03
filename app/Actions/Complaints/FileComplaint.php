<?php

namespace App\Actions\Complaints;

use App\Enums\ComplaintReason;
use App\Enums\ComplaintStatus;
use App\Models\Complaint;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Vartotojas praneša apie netinkamą turinį. reportable – polimorfinis ryšys (morphTo): reportable_type saugo
 * trumpą vardą iš morph map („review", „message"…), reportable_id – įrašo ID.
 * https://laravel.com/docs/13.x/eloquent-relationships#polymorphic-relationships
 *
 * Tas pats žmogus apie tą patį įrašą gali turėti tik vieną neužbaigtą skundą (open / in_review) –
 * kitaip vienas vartotojas galėtų „užtvindyti" administratoriaus eilę.
 */
class FileComplaint
{
    public function handle(User $reporter, Model $reportable, ComplaintReason $reason, ?string $description): Complaint
    {
        return DB::transaction(function () use ($reporter, $reportable, $reason, $description): Complaint {
            // Autoriaus eilutės užraktas: du vienu metu išsiųsti skundai eis po vieną, ir antrasis pamatys pirmąjį
            User::query()->whereKey($reporter->id)->lockForUpdate()->first();

            $duplicate = Complaint::query()
                ->where('reporter_id', $reporter->id)
                ->whereMorphedTo('reportable', $reportable)
                ->whereIn('status', [ComplaintStatus::Open, ComplaintStatus::InReview])
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages(['complaint' => __('complaints.errors.duplicate')]);
            }

            $complaint = new Complaint(['reason' => $reason, 'description' => $description]);
            $complaint->reporter()->associate($reporter);
            $complaint->reportable()->associate($reportable);
            $complaint->forceFill(['status' => ComplaintStatus::Open])->save();

            return $complaint;
        });
    }
}
