<?php

namespace Database\Seeders;

use App\Models\CreditPackage;
use Database\Seeders\Support\ReferenceData;
use Illuminate\Database\Seeder;

class CreditPackageSeeder extends Seeder
{
    public function run(): void
    {
        foreach (ReferenceData::monetization()['credit_packages'] as $sort => $package) {
            CreditPackage::query()->updateOrCreate(
                ['name' => $package['name']],
                [...$package, 'is_active' => true, 'sort_order' => $sort],
            );
        }
    }
}
