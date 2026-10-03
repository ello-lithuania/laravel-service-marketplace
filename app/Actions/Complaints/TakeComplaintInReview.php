<?php

namespace App\Actions\Complaints;

use App\Enums\ComplaintStatus;
use App\Exceptions\ComplaintAlreadyHandledException;
use App\Models\Complaint;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Administratorius ima skundą nagrinėti: open → in_review, handled_by_id = jis.
 * Taip kiti administratoriai mato, kad skundu jau rūpinamasi, ir to paties darbo nedaro dukart.
 */
class TakeComplaintInReview
{
    public function handle(Complaint $complaint, User $admin): Complaint
    {
        DB::transaction(function () use ($complaint, $admin): void {
            $locked = Complaint::query()->whereKey($complaint->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== ComplaintStatus::Open) {
                throw ComplaintAlreadyHandledException::make();
            }

            $complaint->forceFill(['status' => ComplaintStatus::InReview])->handledBy()->associate($admin)->save();
        });

        return $complaint;
    }
}
