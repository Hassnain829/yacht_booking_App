<?php

namespace App\Models;

use App\Enums\AmenityGroup;
use Database\Factories\AmenityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** A boat feature: a highlight ("Flybridge sun lounge") or an item of the wizard's lists (PLAN §6.2). */
#[Fillable(['name', 'group', 'icon', 'sort'])]
#[Hidden(['id'])]
class Amenity extends Model
{
    /** @use HasFactory<AmenityFactory> */
    use HasFactory;

    /** @return BelongsToMany<Boat, $this> */
    public function boats(): BelongsToMany
    {
        return $this->belongsToMany(Boat::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['group' => AmenityGroup::class];
    }
}
