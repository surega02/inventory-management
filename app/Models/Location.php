<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Database\Factories\LocationFactory;
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
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['code', 'name', 'description'])]
class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use Audited, HasFactory, SoftDeletes;

    /** Why a permanent delete is refused (FK RESTRICT), or null when it is safe. */
    public function purgeBlocker(): ?string
    {
        if ($this->assets()->withTrashed()->exists()) {
            return 'Used by deleted assets, so it can only be restored.';
        }

        $moved = AssetMovement::where('from_location_id', $this->id)->orWhere('to_location_id', $this->id)->exists();

        return $moved ? 'Kept for asset movement history, so it can only be restored.' : null;
    }

    /** @return HasMany<Asset, $this> */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }
}
