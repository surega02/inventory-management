<?php

namespace App\Services;

use App\Enums\AssetStatus;
use App\Enums\MaintenanceStatus;
use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\Category;
use App\Models\Loan;
use App\Models\Location;
use App\Models\Maintenance;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Read-only aggregates for the dashboard and the chatbot's get_inventory_summary.
 * Every count goes through visibleTo; employees only get their own loan counts.
 */
class DashboardService
{
    /**
     * @return array{
     *     active_loans: int,
     *     overdue_loans: int,
     *     by_status?: array<string, int>,
     *     by_category?: list<array{code: string, name: string, count: int}>,
     *     by_location?: list<array{code: string, name: string, count: int}>,
     * }
     */
    public function summary(User $actor): array
    {
        $counts = [
            'active_loans' => Loan::visibleTo($actor)->active()->count(),
            'overdue_loans' => Loan::visibleTo($actor)->overdue()->count(),
        ];

        if ($actor->isEmployee()) {
            return $counts;
        }

        $byStatus = Asset::visibleTo($actor)->toBase()
            ->selectRaw('status, count(*) as aggregate')->groupBy('status')
            ->pluck('aggregate', 'status');

        // disposed assets are gone for good, so they are not counted against a category or location
        $inService = fn (Builder $q) => $q->whereIn('assets.id', Asset::visibleTo($actor)->select('assets.id'))
            ->whereNot('status', AssetStatus::Disposed);
        $grouped = fn (string $model) => $model::query()
            ->withCount(['assets as count' => $inService])
            ->orderByDesc('count')->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(fn (Category|Location $m) => ['code' => $m->code, 'name' => $m->name, 'count' => (int) $m->getAttribute('count')])
            ->values()->all();

        return [
            ...$counts,
            'by_status' => collect(AssetStatus::cases())->mapWithKeys(fn (AssetStatus $s) => [$s->value => (int) ($byStatus[$s->value] ?? 0)])->all(),
            'by_category' => $grouped(Category::class),
            'by_location' => $grouped(Location::class),
        ];
    }

    /**
     * Latest loan, movement and maintenance events, newest first. Employees only
     * get events of their own loans (movements and maintenance are operational data).
     *
     * @return list<array{kind: string, at: string, asset: array{id: int, code: string, name: string}, detail: string|null}>
     */
    public function recentActivity(User $actor, int $limit = 8): array
    {
        $events = Loan::visibleTo($actor)
            ->with(['asset:id,code,name', 'borrower:id,name'])
            ->orderByRaw('coalesce(returned_at, loaned_at) desc')->orderByDesc('id')
            ->limit($limit)->get()
            ->map(fn (Loan $l) => $this->event(
                $l->returned_at ? 'returned' : 'lent', $l->returned_at ?? $l->loaned_at, $l->asset, $l->borrower->name,
            ));

        if ($actor->canManageInventory()) {
            $events = $events
                ->concat(AssetMovement::with(['asset:id,code,name', 'fromLocation:id,name', 'toLocation:id,name'])
                    ->orderByDesc('moved_at')->orderByDesc('id')->limit($limit)->get()
                    ->map(fn (AssetMovement $m) => $this->event('moved', $m->moved_at, $m->asset, "{$m->fromLocation->name} → {$m->toLocation->name}")))
                ->concat(Maintenance::with('asset:id,code,name')
                    ->orderByRaw('coalesce(completed_at, started_at) desc')->orderByDesc('id')->limit($limit)->get()
                    ->map(fn (Maintenance $m) => $m->status === MaintenanceStatus::Done
                        ? $this->event('maintenance_completed', $m->completed_at ?? $m->started_at, $m->asset, $m->description)
                        : $this->event('maintenance_started', $m->started_at, $m->asset, $m->description)));
        }

        return array_values($events->sortByDesc('at')->take($limit)->all());
    }

    /** @return array{kind: string, at: string, asset: array{id: int, code: string, name: string}, detail: string|null} */
    private function event(string $kind, DateTimeInterface $at, Asset $asset, ?string $detail): array
    {
        return [
            'kind' => $kind,
            'at' => Carbon::instance($at)->toIso8601String(),
            'asset' => ['id' => $asset->id, 'code' => $asset->code, 'name' => $asset->name],
            'detail' => $detail,
        ];
    }
}
