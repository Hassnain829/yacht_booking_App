<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\RecoveryCode;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('(312) 555-####'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /** A boat owner. role is not fillable, so it is set after making the model. */
    public function owner(): static
    {
        return $this->afterMaking(fn (User $user) => $user->forceFill(['role' => Role::Owner]));
    }

    public function admin(): static
    {
        return $this->afterMaking(fn (User $user) => $user->forceFill(['role' => Role::Admin]));
    }

    public function suspended(string $reason = 'Test suspension'): static
    {
        return $this->afterMaking(fn (User $user) => $user->forceFill(['status' => UserStatus::Suspended, 'status_reason' => $reason]));
    }

    public function banned(): static
    {
        return $this->afterMaking(fn (User $user) => $user->forceFill(['status' => UserStatus::Banned]));
    }

    /** Two-factor login turned on and confirmed. Tests read the secret back with Fortify::currentEncrypter(). */
    public function withTwoFactor(): static
    {
        return $this->afterMaking(fn (User $user) => $user->forceFill([
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt(app(TwoFactorAuthenticationProvider::class)->generateSecretKey()),
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode(Collection::times(8, fn () => RecoveryCode::generate())->all())),
            'two_factor_confirmed_at' => now(),
        ]));
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
