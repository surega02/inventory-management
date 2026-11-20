<?php

namespace App\Services;

use App\Enums\AssetStatus;
use App\Enums\MaintenanceStatus;
use App\Exceptions\ConflictException;
use App\Models\Asset;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Single place for asset reads (always through visibleTo) and writes.
 * code, category_id, location_id and status are only ever set here.
 */
class AssetService
{
    /** Sortable columns; a leading "-" means descending. */
    public const SORTS = ['code', 'name', 'status', 'purchase_date'];

    /**
     * @param  array{q?: string|null, category_id?: int|null, location_id?: int|null, status?: string|null, sort?: string|null}  $filters
     * @return LengthAwarePaginator<int, Asset>
     */
    public function list(User $actor, array $filters): LengthAwarePaginator
    {
        $sort = $filters['sort'] ?? 'code';
        $column = ltrim($sort, '-');

        return Asset::visibleTo($actor)
            ->with(['category:id,code,name', 'location:id,code,name'])
            // OR filters stay inside one group so they can never widen visibleTo
            ->when($filters['q'] ?? null, fn (Builder $q, string $term) => $q->where(fn (Builder $w) => $w
                ->whereLike('code', "%{$term}%")
                ->orWhereLike('name', "%{$term}%")
                ->orWhereLike('serial_number', "%{$term}%")))
            ->when($filters['category_id'] ?? null, fn (Builder $q, int $id) => $q->where('category_id', $id))
            ->when($filters['location_id'] ?? null, fn (Builder $q, int $id) => $q->where('location_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $q, string $status) => $q->where('status', $status))
            ->orderBy(in_array($column, self::SORTS, true) ? $column : 'code', str_starts_with($sort, '-') ? 'desc' : 'asc')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();
    }

    /** 404 when the asset does not exist or is outside the actor's scope. */
    public function findVisible(User $actor, int $id): Asset
    {
        return Asset::visibleTo($actor)->with(['category', 'location'])->findOrFail($id);
    }

    /**
     * Next code = {CATEGORY}-{4+ digits}. The category row is locked FOR UPDATE so two
     * concurrent creates in one category queue up instead of taking the same number.
     *
     * @param  array<string, mixed>  $attributes  validated name, condition, brand, serial_number, purchase_*, useful_life_years, notes
     */
    public function create(User $actor, int $categoryId, int $locationId, array $attributes): Asset
    {
        return DB::transaction(function () use ($actor, $categoryId, $locationId, $attributes) {
            $category = Category::whereKey($categoryId)->lockForUpdate()->firstOrFail();
            $category->forceFill(['last_sequence' => $category->last_sequence + 1])->save();

            $asset = new Asset($attributes);
            $asset->useful_life_years ??= $category->default_useful_life_years;
            $asset->forceFill([
                'code' => sprintf('%s-%04d', $category->code, $category->last_sequence), // 9999 → LAP-10000
                'category_id' => $category->id,
                'location_id' => $locationId,
                'status' => AssetStatus::Available,
                'created_by' => $actor->id,
            ])->save();

            return $asset;
        });
    }

    /** @param array<string, mixed> $attributes only fillable fields: code, category, location and status never change here */
    public function update(Asset $asset, array $attributes): Asset
    {
        $asset->update($attributes);

        return $asset;
    }

    /** Final. Not while borrowed (return it first) or under maintenance (complete it first). */
    public function dispose(Asset $asset, string $reason): Asset
    {
        return DB::transaction(function () use ($asset, $reason) {
            $asset = Asset::whereKey($asset->id)->lockForUpdate()->firstOrFail();

            match (true) {
                $asset->status === AssetStatus::Disposed => throw new ConflictException("{$asset->code} is already disposed."),
                $asset->status === AssetStatus::Borrowed => throw new ConflictException("{$asset->code} is on loan; record the return first."),
                $asset->maintenances()->where('status', MaintenanceStatus::InProgress)->exists() => throw new ConflictException("{$asset->code} is under maintenance; complete it first."),
                default => null,
            };

            $asset->forceFill(['status' => AssetStatus::Disposed])->save();

            activity()->performedOn($asset)->event('disposed')
                ->withProperties(['reason' => $reason])
                ->log("Disposed {$asset->code}");

            return $asset;
        });
    }

    /** Soft delete for mistakes only: an asset with any loan, movement or maintenance must be disposed instead. */
    public function delete(Asset $asset): void
    {
        throw_if($this->hasHistory($asset), ConflictException::class, "{$asset->code} has history; dispose it instead of deleting.");

        $asset->delete();
    }

    public function hasHistory(Asset $asset): bool
    {
        return $asset->loans()->exists() || $asset->movements()->exists() || $asset->maintenances()->exists();
    }
}
