<?php

namespace Database\Seeders\Demo;

use App\Enums\PriceUnit;
use App\Enums\ProviderStatus;
use App\Enums\ProviderType;
use App\Enums\UserRole;
use Generator;

/**
 * Teikėjai: paskyros, profiliai, kategorijos, zonos ir portfolio (docs/SEEDING.md 4 sk. „Teikėjai").
 */
final class ProviderGenerator
{
    public function __construct(private readonly DemoContext $ctx) {}

    /**
     * Sudėlioja teikėjus atmintyje. Datos (created_at) paskaičiuojamos vėliau, kai žinomi pasiūlymai.
     */
    public function plan(): void
    {
        $c = $this->ctx;
        $statuses = [ProviderStatus::Active->value, ProviderStatus::Pending->value, ProviderStatus::Hidden->value, ProviderStatus::Suspended->value];

        $rootPicker = new WeightedPicker($c->rootIds, array_map(fn (int $id) => $c->categoryWeight[$id], $c->rootIds));
        $groupPickers = [];
        $leafPickers = [];

        foreach ($c->rootIds as $rootId) {
            $groups = $c->childrenOf[$rootId] ?? [];
            $groupPickers[$rootId] = new WeightedPicker($groups, array_map(fn (int $id) => $c->categoryWeight[$id], $groups));
            $leaves = array_merge(...array_map(fn (int $id) => $c->childrenOf[$id] ?? [], $groups));
            $leafPickers[$rootId] = new WeightedPicker($leaves, array_map(fn (int $id) => $c->categoryWeight[$id], $leaves));
        }

        for ($p = 0; $p < $c->counts['providers']; $p++) {
            $status = $statuses[$c->pickIndex([0.92, 0.04, 0.03, 0.01])];
            $city = $c->cityPicker->pick();
            $whole = $c->chance(0.10);
            $zones = [];

            if (! $whole) {
                $neighbours = array_values(array_diff($c->regionCities[$c->cityRegion[$city]], [$city]));
                shuffle($neighbours);
                $zones = [$city, ...array_slice($neighbours, 0, $c->between(0, 6))];
            }

            $rootId = $rootPicker->pick();

            if ($c->chance(0.15)) {
                // Visa 2 lygio kategorija – pivot'e viena eilutė, bet tinka visiems jos lapams
                $group = $groupPickers[$rootId]->pick();
                $categories = [$group];
                $leaves = $c->childrenOf[$group] ?? [];
            } else {
                $leaves = $leafPickers[$rootId]->pickUnique($c->between(2, 10));
                $categories = $leaves;
            }

            $c->provStatus[] = $status;
            $c->provCompany[] = $c->chance(0.35);
            $c->provCity[] = $city;
            $c->provWhole[] = $whole;
            $c->provZones[] = $zones;
            $c->provCategories[] = $categories;
            $c->provLeaves[] = $leaves;
            // Pareto: 20 % aktyviausių teikėjų gauna ≈ 60 % viso „aktyvumo" svorio (0,2^(1−0,683) ≈ 0,6)
            $c->provActivity[] = max($c->rand01(), 0.001) ** -0.683;

            // Pasiūlymus siunčia tik aktyvūs teikėjai – tokia pati taisyklė kaip programoje
            if ($status === ProviderStatus::Active->value) {
                foreach ($leaves as $leaf) {
                    $c->leafProviders[$leaf][] = $p;
                }

                foreach ($zones as $zone) {
                    $c->cityProviders[$zone][$p] = true;
                }
            }
        }
    }

    /**
     * Teikėjų paskyrų (users) eilutės.
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function users(): Generator
    {
        $c = $this->ctx;

        foreach ($c->provStatus as $p => $status) {
            $id = $c->providerUserBase + $p;
            $gender = $c->chance(0.5) ? 'male' : 'female';
            $firstName = $c->faker->firstName($gender);
            $lastName = $c->faker->lastName($gender);
            $created = $c->provCreated[$p];

            $c->provDisplayName[$p] = $c->provCompany[$p] ? $c->faker->company() : $firstName.' '.$lastName;

            yield [
                'id' => $id,
                'role' => UserRole::Provider->value,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => 'teikejas'.($p + 1).'@example.test',
                'email_verified_at' => $c->date($created + $c->between(60, 6 * DemoContext::HOUR)),
                'phone' => sprintf('+3700%07d', $id),
                'password' => $c->passwordHash,
                'city_id' => $c->provCity[$p],
                'last_seen_at' => $c->date($c->provLastActive[$p]),
                'created_at' => $c->date($created),
                'updated_at' => $c->date($created),
            ];
        }
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    public function profiles(): Generator
    {
        $c = $this->ctx;

        foreach ($c->provStatus as $p => $status) {
            $id = $p + 1;
            $created = $c->provCreated[$p];
            $mainCategory = $c->categoryName[$c->provCategories[$p][0]];
            $years = $c->chance(0.7) ? $c->skewed(1, 30, 1.5) : null;
            $slug = $c->slug($c->provDisplayName[$p], $id, 150);
            $vars = [
                'paslauga' => $mainCategory,
                'paslauga_m' => $c->lowerFirst($mainCategory),
                'miestas' => $c->cityLocative[$c->provCity[$p]],
                'metai' => $years ?? $c->between(2, 15),
            ];
            $verified = $status !== ProviderStatus::Pending->value && $c->chance(0.3)
                ? min($c->now, $created + $c->between(DemoContext::DAY, 10 * DemoContext::DAY))
                : 0;

            yield [
                'id' => $id,
                'user_id' => $c->providerUserBase + $p,
                'type' => ($c->provCompany[$p] ? ProviderType::Company : ProviderType::Individual)->value,
                'display_name' => $c->provDisplayName[$p],
                'slug' => $slug,
                'headline' => $c->render($c->text('providers', 'headlines'), $vars),
                'description' => $c->sentences('providers', 'descriptions', $c->between(2, 4)),
                'city_id' => $c->provCity[$p],
                'company_code' => $c->provCompany[$p] ? sprintf('999%06d', $id) : null,
                'vat_code' => $c->provCompany[$p] && $c->chance(0.7) ? sprintf('LT999%06d', $id) : null,
                'website' => $c->provCompany[$p] && $c->chance(0.4) ? 'https://'.$slug.'.example.test' : null,
                'years_experience' => $years,
                'serves_whole_country' => $c->provWhole[$p],
                'status' => $status,
                'verified_at' => $c->nullableDate($verified),
                'credits_balance' => 0,
                'rating_avg' => 0,
                'reviews_count' => 0,
                'completed_jobs_count' => 0,
                'last_active_at' => $c->date($c->provLastActive[$p]),
                'created_at' => $c->date($created),
                'updated_at' => $c->date(max($created, $verified)),
            ];
        }
    }

    /**
     * Kategorijos su „kaina nuo" (60 % turi kainą).
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function categoryPivot(): Generator
    {
        $c = $this->ctx;
        $units = [PriceUnit::Hour, PriceUnit::Job, PriceUnit::SquareMeter, PriceUnit::Meter, PriceUnit::Unit];

        foreach ($c->provCategories as $p => $categories) {
            foreach ($categories as $categoryId) {
                $unit = null;
                $price = null;

                if ($c->chance(0.6)) {
                    $unit = $units[$c->pickIndex([0.35, 0.30, 0.20, 0.05, 0.10])];
                    [$min, $max] = match ($unit) {
                        PriceUnit::Hour => [10, 45],
                        PriceUnit::SquareMeter => [3, 40],
                        PriceUnit::Meter => [2, 25],
                        PriceUnit::Unit => [5, 60],
                        PriceUnit::Job => [20, 400],
                    };
                    // Sveiki eurai arba pusė euro – kaip tikrose kainose
                    $price = $c->between($min * 2, $max * 2) * 50;
                }

                yield [
                    'provider_profile_id' => $p + 1,
                    'category_id' => $categoryId,
                    'price_from_cents' => $price,
                    'price_unit' => $unit?->value,
                ];
            }
        }
    }

    /**
     * @return Generator<int, array<string, int>>
     */
    public function cityPivot(): Generator
    {
        foreach ($this->ctx->provZones as $p => $zones) {
            foreach ($zones as $cityId) {
                yield ['provider_profile_id' => $p + 1, 'city_id' => $cityId];
            }
        }
    }

    /**
     * 60 % teikėjų turi 1–8 atliktus darbus.
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function portfolio(): Generator
    {
        $c = $this->ctx;
        $id = 0;

        foreach ($c->provLeaves as $p => $leaves) {
            if (! $c->chance(0.6) || $leaves === []) {
                continue;
            }

            $count = $c->between(1, 8);

            for ($i = 0; $i < $count; $i++) {
                $leaf = $c->pickOne($leaves);
                $city = $c->provZones[$p] === [] ? $c->cityPicker->pick() : $c->pickOne($c->provZones[$p]);
                $created = $c->between($c->provCreated[$p], $c->now);
                $vars = [
                    'paslauga' => $c->categoryName[$leaf],
                    'paslauga_m' => $c->lowerFirst($c->categoryName[$leaf]),
                    'miestas' => $c->cityLocative[$city],
                ];

                yield [
                    'id' => ++$id,
                    'provider_profile_id' => $p + 1,
                    'category_id' => $leaf,
                    'city_id' => $city,
                    'title' => mb_substr($c->render($c->text('portfolio', 'titles'), $vars), 0, 150),
                    'description' => $c->sentences('portfolio', 'descriptions', $c->between(1, 3)),
                    'completed_date' => gmdate('Y-m-d', $created - $c->between(0, 2 * 365) * DemoContext::DAY),
                    'sort_order' => $i,
                    'created_at' => $c->date($created),
                    'updated_at' => $c->date($created),
                ];
            }
        }
    }
}
