<?php

namespace App\Models;

use App\Enums\AssetCondition;
use App\Models\Concerns\Audited;
use Database\Factories\LoanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only: created by LoanService, later closed (returned_*), never deleted.
 *
 * @property int $id
 * @property int $asset_id
 * @property int $borrower_id
 * @property Carbon $loaned_at
 * @property Carbon|null $due_date
 * @property Carbon|null $returned_at
 * @property AssetCondition|null $return_condition
 * @property string|null $notes
 * @property int $recorded_by
 * @property int|null $returned_recorded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['due_date', 'notes'])]
class Loan extends Model
{
    /** @use HasFactory<LoanFactory> */
    use Audited, HasFactory;

    protected function casts(): array
    {
        return [
            'loaned_at' => 'datetime',
            'due_date' => 'date',
            'returned_at' => 'datetime',
            'return_condition' => AssetCondition::class,
        ];
    }

    /**
     * Rows $actor may see: everything for admin/staff, only their own for employees.
     *
     * @param  Builder<Loan>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $actor): void
    {
        if ($actor->isEmployee()) {
            $query->where('borrower_id', $actor->id);
        }
    }

    /** @param Builder<Loan> $query */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('returned_at');
    }

    /** @param Builder<Loan> $query */
    #[Scope]
    protected function overdue(Builder $query): void
    {
        $query->whereNull('returned_at')->whereDate('due_date', '<', today());
    }

    /** Overdue is derived from due_date, never stored. */
    public function isOverdue(): bool
    {
        return $this->returned_at === null && $this->due_date !== null && $this->due_date->lt(today());
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function borrower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'borrower_id')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by')->withTrashed();
    }
}
