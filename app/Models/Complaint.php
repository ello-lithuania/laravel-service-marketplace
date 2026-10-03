<?php

namespace App\Models;

use App\Enums\ComplaintReason;
use App\Enums\ComplaintStatus;
use Database\Factories\ComplaintFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Skundas dėl užklausos, pasiūlymo, atsiliepimo, žinutės, profilio ar vartotojo (polimorfinis).
 */
#[Fillable(['reason', 'description'])]
class Complaint extends Model
{
    /** @use HasFactory<ComplaintFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'reason' => ComplaintReason::class,
            'status' => ComplaintStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /** @return BelongsTo<User, $this> */
    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by_id');
    }

    /** @return MorphTo<Model, $this> */
    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }
}
