<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * Sign-up (resources/views/auth/register.blade.php). Every new account is a customer;
 * role and status are set by the system only (Phase 2.2), never from the request.
 * Only validated fields are used — anything else in $input is ignored.
 */
class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        $data = Validator::make($input, [
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'email' => ['required', 'string', 'email:rfc', 'max:254', Rule::unique(User::class)],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+().\s-]+$/'],
            'password' => $this->passwordRules(),
            'terms' => ['accepted'],
            'marketing' => ['nullable', 'boolean'],
        ], [
            'terms.accepted' => 'Please accept the Terms of Service and Privacy Policy.',
            'phone.regex' => 'Use digits, spaces and + ( ) - only.',
        ])->validate();

        return User::create([
            'first_name' => trim($data['first_name']),
            'last_name' => trim($data['last_name']),
            'email' => Str::lower($data['email']),
            'phone' => isset($data['phone']) ? trim($data['phone']) : null,
            'password' => $data['password'], // hashed by the model cast
            'marketing_opt_in' => (bool) ($data['marketing'] ?? false),
        ]);
    }
}
