<?php

namespace Database\Factories;

use App\Enums\ServiceRequestStatus;
use App\Enums\StartPreference;
use App\Models\Category;
use App\Models\City;
use App\Models\Offer;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Numatytoji būsena – atvira (open) užklausa 3 lygio kategorijoje.
 *
 * @extends Factory<ServiceRequest>
 */
class ServiceRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->randomElement([
            'Plytelių klijavimas vonioje', 'Reikia pakeisti maišytuvą', 'Buto dažymas',
            'Laminato klojimas', 'Elektros instaliacijos keitimas', 'Langų valymas',
        ]);

        return [
            'client_id' => User::factory(),
            'category_id' => Category::factory()->leaf(),
            'city_id' => City::factory(),
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'title' => $title,
            'description' => 'Ieškau patikimo meistro. Darbus norėčiau pradėti per artimiausias dvi savaites.',
            'address' => null,
            'budget_min_cents' => null,
            'budget_max_cents' => null,
            'start_preference' => StartPreference::Flexible,
            'start_date' => null,
            'status' => ServiceRequestStatus::Open,
            'published_at' => now()->subDays(2),
            'expires_at' => now()->addDays(28),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ServiceRequestStatus::Pending,
            'published_at' => null,
            'expires_at' => null,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ServiceRequestStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ServiceRequestStatus::Expired,
            'published_at' => now()->subDays(40),
            'expires_at' => now()->subDays(10),
        ]);
    }

    /**
     * Vykdoma: sukuriamas priimtas pasiūlymas ir užpildomas accepted_offer_id.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ServiceRequestStatus::InProgress])
            ->withAcceptedOffer();
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ServiceRequestStatus::Completed,
            'completed_at' => now(),
        ])->withAcceptedOffer();
    }

    /**
     * Žiedinė nuoroda: pasiūlymas gali atsirasti tik po užklausos, todėl – afterCreating.
     */
    protected function withAcceptedOffer(): static
    {
        return $this->afterCreating(function (ServiceRequest $request) {
            $offer = Offer::factory()->accepted()->for($request)->create();

            $request->forceFill(['accepted_offer_id' => $offer->id])->save();
        });
    }
}
