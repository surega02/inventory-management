<?php

namespace App\Models;

use App\Enums\MaintenanceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only: started, later completed, never deleted.
 *
 * @property int $id
 * @property int $asset_id
 * @property string $description
 * @property string|null $vendor
 * @property Carbon $started_at
 * @property Carbon|null $completed_at
 * @property string|null $cost numeric(14,2) Rupiah
 * @property MaintenanceStatus $status
 * @property int $recorded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['description', 'vendor', 'cost'])]
class Maintenance extends Model
{
    protected function casts(): array
    {
        return [
            'started_at' => 'date',
            'completed_at' => 'date',
            'cost' => 'decimal:2',
            'status' => MaintenanceStatus::class,
        ];
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by')->withTrashed();
    }
}
