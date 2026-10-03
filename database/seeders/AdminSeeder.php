<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * 3 administratoriai: admin1@example.test … (slaptažodis „password", tik dev!).
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        foreach (range(1, 3) as $n) {
            $admin = User::query()->firstOrNew(['email' => "admin{$n}@example.test"]);

            // forceFill, nes role sąmoningai nėra Fillable – per updateOrCreate ji būtų tyliai ignoruota
            $admin->forceFill([
                'role' => UserRole::Admin,
                'first_name' => 'Administratorius',
                'last_name' => (string) $n,
                'password' => 'password',
                'email_verified_at' => now(),
            ])->save();
        }
    }
}
