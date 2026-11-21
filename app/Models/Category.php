<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int|null $default_useful_life_years
 * @property int $last_sequence asset-code counter, only bumped by the asset-code service under a row lock
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['code', 'name', 'default_useful_life_years'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use Audited, HasFactory, SoftDeletes;

    /** Once an asset code was issued with this prefix, changing it would orphan those codes. */
    public function isCodeLocked(): bool
    {
        return $this->last_sequence > 0;
    }

    /** Why a permanent delete is refused (FK RESTRICT), or null when it is safe. */
    public function purgeBlocker(): ?string
    {
        return $this->assets()->withTrashed()->exists()
            ? 'Used by deleted assets, so it can only be restored.'
            : null;
    }

    /** @return HasMany<Asset, $this> */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }
}
