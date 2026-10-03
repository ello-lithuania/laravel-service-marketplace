<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * Registruojantis galima pasirinkti tik kliento arba teikėjo rolę. Administratoriaus rolės
     * validacija nepraleis, net jei kas nors ją „įrašytų" į formą naršyklės įrankiais.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'role' => ['required', Rule::enum(UserRole::class)->only([UserRole::Client, UserRole::Provider])],
        ], [
            'role.required' => 'Pasirinkite, kaip naudosite platformą.',
            'role.enum' => 'Pasirinkite, kaip naudosite platformą.',
        ])->validate();

        $user = new User([
            'first_name' => $input['first_name'],
            'last_name' => $input['last_name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);

        // role nėra Fillable (mass assignment apsauga), todėl nustatoma aiškiai per forceFill
        $user->forceFill(['role' => UserRole::from($input['role'])])->save();

        return $user;
    }
}
