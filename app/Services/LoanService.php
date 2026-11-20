<?php

namespace App\Services;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Exceptions\ConflictException;
use App\Models\Asset;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Single place for loan reads (always through visibleTo) and the lend/return
 * writes, which change assets.status in the same transaction as the loan row.
 */
class LoanService
{
    public const STATUSES = ['active', 'overdue', 'returned'];

    /**
     * @param  array{q?: string|null, status?: string|null}  $filters
     * @return LengthAwarePaginator<int, Loan>
     */
    public function list(User $actor, array $filters): LengthAwarePaginator
    {
        return Loan::visibleTo($actor)
            ->with(['asset:id,code,name', 'borrower:id,name'])
            // OR filters stay inside one group so they can never widen visibleTo
            ->when($filters['q'] ?? null, fn (Builder $q, string $term) => $q->where(fn (Builder $w) => $w
                ->whereHas('asset', fn (Builder $a) => $a->withTrashed()->whereLike('code', "%{$term}%")->orWhereLike('name', "%{$term}%"))
                ->orWhereHas('borrower', fn (Builder $b) => $b->withTrashed()->whereLike('name', "%{$term}%"))))
            ->when($filters['status'] ?? null, fn (Builder $q, string $status) => match ($status) {
                'active' => $q->active(),
                'overdue' => $q->overdue(),
                default => $q->whereNotNull('returned_at'),
            })
            ->orderByDesc('loaned_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();
    }

    /**
     * Latest loans of one asset the actor may see (employees: only their own).
     *
     * @return Collection<int, Loan>
     */
    public function forAsset(User $actor, Asset $asset): Collection
    {
        return Loan::visibleTo($actor)
            ->where('asset_id', $asset->id)
            ->with('borrower:id,name')
            ->orderByDesc('loaned_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get();
    }

    /**
     * Loans not yet returned that the actor may see, earliest due date first
     * (no due date last). Used by the dashboard and the chatbot.
     *
     * @param  list<int>|null  $categoryIds
     * @return array{total: int, items: Collection<int, Loan>}
     */
    public function active(User $actor, bool $overdueOnly = false, ?array $categoryIds = null, int $limit = 20): array
    {
        $query = Loan::visibleTo($actor)
            ->when($overdueOnly, fn (Builder $q) => $q->overdue(), fn (Builder $q) => $q->active())
            ->when($categoryIds, fn (Builder $q, array $ids) => $q->whereHas('asset', fn (Builder $a) => $a->withTrashed()->whereIn('category_id', $ids)));

        return [
            'total' => (clone $query)->count(),
            'items' => $query->with(['asset:id,code,name', 'borrower:id,name'])
                ->orderByRaw('due_date asc nulls last')
                ->orderBy('loaned_at')
                ->orderBy('id')
                ->limit(min(max($limit, 1), 20))
                ->get(),
        ];
    }

    /**
     * Loan history (active and returned) the actor may see, newest first. Employees
     * only ever get their own loans: visibleTo wins over any borrower filter.
     *
     * @param  array{asset_code?: string|null, borrower?: string|null, date_from?: string|null, date_to?: string|null}  $filters  dates filter loaned_at (Y-m-d, inclusive)
     * @return array{total: int, items: Collection<int, Loan>}
     */
    public function history(User $actor, array $filters, int $limit = 20): array
    {
        $query = Loan::visibleTo($actor)
            ->when($filters['asset_code'] ?? null, fn (Builder $q, string $code) => $q->whereHas('asset', fn (Builder $a) => $a->withTrashed()->whereRaw('upper(code) = ?', [strtoupper($code)])))
            ->when(! $actor->isEmployee() ? ($filters['borrower'] ?? null) : null, fn (Builder $q, string $term) => $q->whereHas('borrower', fn (Builder $b) => $b->withTrashed()->where(fn (Builder $w) => $w
                ->whereLike('name', "%{$term}%")
                ->orWhereRaw('lower(email) = ?', [strtolower($term)]))))
            ->when($filters['date_from'] ?? null, fn (Builder $q, string $date) => $q->whereDate('loaned_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $q, string $date) => $q->whereDate('loaned_at', '<=', $date));

        return [
            'total' => (clone $query)->count(),
            'items' => $query->with(['asset:id,code,name', 'borrower:id,name'])
                ->orderByDesc('loaned_at')
                ->orderByDesc('id')
                ->limit(min(max($limit, 1), 20))
                ->get(),
        ];
    }

    /** available → borrowed. The asset row is locked so two lends of one asset queue up; the loser gets a conflict. */
    public function lend(User $actor, int $assetId, int $borrowerId, ?string $dueDate, ?string $notes): Loan
    {
        return DB::transaction(function () use ($actor, $assetId, $borrowerId, $dueDate, $notes) {
            $asset = Asset::whereKey($assetId)->lockForUpdate()->firstOrFail();

            throw_unless(
                $asset->status === AssetStatus::Available,
                ConflictException::class,
                "{$asset->code} is {$asset->status->value} and cannot be lent.",
            );

            $loan = new Loan(['due_date' => $dueDate, 'notes' => $notes]);
            $loan->forceFill([
                'asset_id' => $asset->id,
                'borrower_id' => $borrowerId,
                'loaned_at' => now(),
                'recorded_by' => $actor->id,
            ])->save();

            $asset->forceFill(['status' => AssetStatus::Borrowed])->save();

            return $loan;
        });
    }

    /** borrowed → available, or broken when $markBroken. The asset takes the condition it came back in. */
    public function return(User $actor, Loan $loan, AssetCondition $condition, bool $markBroken, ?string $notes): Loan
    {
        return DB::transaction(function () use ($actor, $loan, $condition, $markBroken, $notes) {
            $loan = Loan::whereKey($loan->id)->lockForUpdate()->firstOrFail();
            $asset = Asset::withTrashed()->whereKey($loan->asset_id)->lockForUpdate()->firstOrFail();

            throw_if($loan->returned_at !== null, ConflictException::class, "The loan of {$asset->code} was already returned.");

            $loan->forceFill([
                'returned_at' => now(),
                'return_condition' => $condition,
                'returned_recorded_by' => $actor->id,
                'notes' => filled($notes) ? trim("{$loan->notes}\nReturn: {$notes}") : $loan->notes,
            ])->save();

            $asset->forceFill([
                'status' => $markBroken ? AssetStatus::Broken : AssetStatus::Available,
                'condition' => $condition,
            ])->save();

            return $loan;
        });
    }
}
