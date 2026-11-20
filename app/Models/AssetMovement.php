<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only location change log, written by the movement service.
 *
 * @property int $id
 * @property int $asset_id
 * @property int $from_location_id
 * @property int $to_location_id
 * @property Carbon $moved_at
 * @property string|null $reason
 * @property int $recorded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['reason'])]
class AssetMovement extends Model
{
    use Audited;

    protected function casts(): array
    {
        return ['moved_at' => 'datetime'];
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class)->withTrashed();
    }

    /** @return BelongsTo<Location, $this> */
    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id')->withTrashed();
    }

    /** @return BelongsTo<Location, $this> */
    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by')->withTrashed();
    }
}
