<?php

namespace App\Models;

use App\Enums\LicenseType;
use Carbon\CarbonInterface;
use Database\Factories\CaptainFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A captain (PLAN §6.4). The license number is encrypted in the database; it and the internal pay rate are
 * hidden from arrays / JSON. Assignments are emailed to `email` (never with customer personal data).
 */
#[Fillable([
    'name', 'slug', 'photo_path', 'bio', 'email', 'phone',
    'license_type', 'license_number', 'license_expires_on', 'max_passengers_licensed',
    'years_experience', 'languages', 'pay_rate_cents', 'is_active', 'is_public', 'sort',
])]
#[Hidden(['id', 'license_number', 'pay_rate_cents'])]
class Captain extends Model
{
    /** @use HasFactory<CaptainFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort')->orderBy('name');
    }

    /** Licensed for this many guests, with a license valid on the trip day (PLAN §7.8). */
    public function canTake(int $guests, CarbonInterface $tripDay): bool
    {
        return $guests <= $this->max_passengers_licensed
            && ($this->license_expires_on === null || $this->license_expires_on->endOfDay()->gte($tripDay));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'license_type' => LicenseType::class,
            'license_number' => 'encrypted',
            'license_expires_on' => 'date',
            'languages' => 'array',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
        ];
    }
}
