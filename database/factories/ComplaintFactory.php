<?php

namespace Database\Factories;

use App\Enums\ComplaintReason;
use App\Enums\ComplaintStatus;
use App\Models\Complaint;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Numatytoji būsena – naujas skundas dėl atsiliepimo.
 *
 * @extends Factory<Complaint>
 */
class ComplaintFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reporter_id' => User::factory(),
            'reportable_type' => 'review',
            'reportable_id' => Review::factory(),
            'reason' => ComplaintReason::FakeReview,
            'description' => 'Šis atsiliepimas atrodo netikras.',
            'status' => ComplaintStatus::Open,
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ComplaintStatus::Resolved,
            'handled_by_id' => User::factory()->admin(),
            'resolution_note' => 'Atsiliepimas paslėptas.',
            'resolved_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ComplaintStatus::Rejected,
            'handled_by_id' => User::factory()->admin(),
            'resolution_note' => 'Pažeidimo nerasta.',
            'resolved_at' => now(),
        ]);
    }
}
