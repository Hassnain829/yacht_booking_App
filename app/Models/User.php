<?php

namespace App\Models;

use App\Enums\Role;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * One account for everyone (PLAN §4.1). The public id is `ulid`; the numeric id never leaves the server.
 *
 * `role` and `status` are NOT fillable: only the system changes them — sign-up makes a customer,
 * UserAccountService makes owners and changes status, `app:create-admin` creates the admin.
 */
#[Fillable(['first_name', 'last_name', 'email', 'phone', 'password', 'marketing_opt_in'])]
#[Hidden(['id', 'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'last_login_ip'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUlids, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    /** New models start as active customers (the same defaults as the database). */
    protected $attributes = [
        'role' => 'customer',
        'status' => 'active',
    ];

    /** ULIDs fill the `ulid` column only — the primary key stays an auto-increment id. */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isOwner(): bool
    {
        return $this->role === Role::Owner;
    }

    public function isCustomer(): bool
    {
        return $this->role === Role::Customer;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    /** "Jane Doe" (read-only; first_name / last_name are stored). */
    protected function name(): Attribute
    {
        return Attribute::get(fn () => trim($this->first_name.' '.$this->last_name));
    }

    protected function initials(): Attribute
    {
        return Attribute::get(fn () => strtoupper(mb_substr($this->first_name, 0, 1).mb_substr($this->last_name, 0, 1)));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'marketing_opt_in' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
            'role' => Role::class,
            'status' => UserStatus::class,
            'last_login_at' => 'datetime',
        ];
    }
}
