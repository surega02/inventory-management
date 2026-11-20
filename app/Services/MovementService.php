<?php

namespace App\Services;

use App\Enums\AssetStatus;
use App\Exceptions\ConflictException;
use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/** Location changes: assets.location_id only changes here, together with its movement row. */
class MovementService
{
    /**
     * Latest movements of one asset. Callers only reach this for assets the actor may manage.
     *
     * @return Collection<int, AssetMovement>
     */
    public function forAsset(Asset $asset): Collection
    {
        return $asset->movements()
            ->with(['fromLocation:id,name', 'toLocation:id,name', 'recorder:id,name'])
            ->orderByDesc('moved_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get();
    }

    /** Blocked while borrowed (return it first) and once disposed. The asset row is locked against a concurrent lend. */
    public function move(User $actor, Asset $asset, int $toLocationId, ?string $movedAt, ?string $reason): AssetMovement
    {
        return DB::transaction(function () use ($actor, $asset, $toLocationId, $movedAt, $reason) {
            $asset = Asset::whereKey($asset->id)->lockForUpdate()->firstOrFail();

            match (true) {
                $asset->status === AssetStatus::Borrowed => throw new ConflictException("{$asset->code} is on loan; record the return before moving it."),
                $asset->status === AssetStatus::Disposed => throw new ConflictException("{$asset->code} is disposed and cannot be moved."),
                $asset->location_id === $toLocationId => throw new ConflictException("{$asset->code} is already at that location."),
                default => null,
            };

            $movement = new AssetMovement(['reason' => $reason]);
            $movement->forceFill([
                'asset_id' => $asset->id,
                'from_location_id' => $asset->location_id,
                'to_location_id' => $toLocationId,
                'moved_at' => $movedAt ?? now(),
                'recorded_by' => $actor->id,
            ])->save();

            $asset->forceFill(['location_id' => $toLocationId])->save();

            return $movement;
        });
    }
}
