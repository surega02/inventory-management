<?php

namespace App\Services;

use App\Enums\AssetStatus;
use App\Enums\MaintenanceStatus;
use App\Exceptions\ConflictException;
use App\Models\Asset;
use App\Models\Maintenance;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Start (available/broken → maintenance) and complete (maintenance → available).
 * assets.status changes in the same transaction as the maintenance row.
 */
class MaintenanceService
{
    /**
     * Latest maintenance jobs of one asset. Callers only reach this for assets the actor may manage.
     *
     * @return Collection<int, Maintenance>
     */
    public function forAsset(Asset $asset): Collection
    {
        return $asset->maintenances()
            ->with('recorder:id,name')
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get();
    }

    /**
     * Maintenance jobs across assets, newest first. Operational data: employees get
     * nothing even if a caller forgets to hide this from them.
     *
     * @param  array{asset_code?: string|null, status?: string|null}  $filters
     * @return array{total: int, items: Collection<int, Maintenance>}
     */
    public function history(User $actor, array $filters, int $limit = 20): array
    {
        $query = Maintenance::query()
            ->when(! $actor->canManageInventory(), fn (Builder $q) => $q->whereRaw('1 = 0'))
            ->when($filters['asset_code'] ?? null, fn (Builder $q, string $code) => $q->whereHas('asset', fn (Builder $a) => $a->withTrashed()->whereRaw('upper(code) = ?', [strtoupper($code)])))
            ->when($filters['status'] ?? null, fn (Builder $q, string $status) => $q->where('status', $status));

        return [
            'total' => (clone $query)->count(),
            'items' => $query->with('asset:id,code,name')
                ->orderByDesc('started_at')
                ->orderByDesc('id')
                ->limit(min(max($limit, 1), 20))
                ->get(),
        ];
    }

    public function start(User $actor, Asset $asset, string $description, string $startedAt, ?string $vendor, ?string $cost): Maintenance
    {
        return DB::transaction(function () use ($actor, $asset, $description, $startedAt, $vendor, $cost) {
            $asset = Asset::whereKey($asset->id)->lockForUpdate()->firstOrFail();

            throw_unless(
                in_array($asset->status, [AssetStatus::Available, AssetStatus::Broken], true),
                ConflictException::class,
                "{$asset->code} is {$asset->status->value}; only available or broken assets can go into maintenance.",
            );

            $maintenance = new Maintenance(['description' => $description, 'vendor' => $vendor, 'cost' => $cost]);
            $maintenance->forceFill([
                'asset_id' => $asset->id,
                'started_at' => $startedAt,
                'status' => MaintenanceStatus::InProgress,
                'recorded_by' => $actor->id,
            ])->save();

            $asset->forceFill(['status' => AssetStatus::Maintenance])->save();

            return $maintenance;
        });
    }

    /** $cost replaces the estimate given at start when filled; otherwise the estimate stays. */
    public function complete(Maintenance $maintenance, string $completedAt, ?string $cost): Maintenance
    {
        return DB::transaction(function () use ($maintenance, $completedAt, $cost) {
            $maintenance = Maintenance::whereKey($maintenance->id)->lockForUpdate()->firstOrFail();
            $asset = Asset::withTrashed()->whereKey($maintenance->asset_id)->lockForUpdate()->firstOrFail();

            throw_if($maintenance->status === MaintenanceStatus::Done, ConflictException::class, "This maintenance of {$asset->code} is already completed.");

            $maintenance->forceFill([
                'completed_at' => $completedAt,
                'cost' => $cost ?? $maintenance->cost,
                'status' => MaintenanceStatus::Done,
            ])->save();

            $asset->forceFill(['status' => AssetStatus::Available])->save();

            return $maintenance;
        });
    }
}
