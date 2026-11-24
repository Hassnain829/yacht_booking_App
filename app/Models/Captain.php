<?php

namespace App\Models;

use App\Enums\LicenseType;
use App\Models\Contracts\Trashable;
use Carbon\CarbonInterface;
use Database\Factories\CaptainFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

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
class Captain extends Model implements Trashable
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

    /** License documents on the private disk (opened through /files/{uuid}). @return MorphMany<Media, $this> */
    public function documents(): MorphMany
    {
        return $this->morphMany(Media::class, 'model')->where('collection', 'license')->orderBy('sort')->orderBy('id');
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path === null ? null : Storage::disk('public')->url($this->photo_path);
    }

    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim($this->name)) ?: [])->filter()->take(2)->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
    }

    /** "•••• 1902" — the admin screens never show the whole license number. */
    public function maskedLicense(): ?string
    {
        return blank($this->license_number) ? null : '•••• '.mb_substr((string) $this->license_number, -4);
    }

    public function licenseExpired(): bool
    {
        return $this->license_expires_on !== null && $this->license_expires_on->endOfDay()->isPast();
    }

    public function licenseExpiresSoon(int $days = 30): bool
    {
        return $this->license_expires_on !== null && ! $this->licenseExpired() && $this->license_expires_on->lte(now()->addDays($days));
    }

    public function trashLabel(): string
    {
        return 'captain';
    }

    public function trashTitle(): string
    {
        return $this->name;
    }

    /** Phase 8 adds: upcoming trips assigned to this captain (reassign them first). */
    public function trashBlockers(): array
    {
        return [];
    }

    /** Trips this captain drove or is down for are kept for the books (pay records follow in Phase 8). */
    public function permanentDeleteBlockers(): array
    {
        $trips = Booking::query()->where('captain_id', $this->getKey())->count();

        return $trips === 0 ? [] : [trans_choice(
            '{1} 1 trip has this captain and keeps the profile for the records.|[2,*] :count trips have this captain and keep the profile for the records.',
            $trips,
        )];
    }

    /** The photo (public disk); license documents are `media` rows, deleted by TrashService. */
    public function ownFiles(): array
    {
        return $this->photo_path === null ? [] : ['public' => [$this->photo_path]];
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
