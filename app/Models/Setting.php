<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/** One site setting (PLAN §6.7), e.g. key "tax_rate_bp" → 1025. Read through the cached settings service (3.4). */
#[Fillable(['key', 'value', 'group'])]
#[Hidden(['id'])]
class Setting extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['value' => 'json'];
    }
}
