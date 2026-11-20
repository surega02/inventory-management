<?php

namespace App\Models;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * code, category_id, location_id and status are deliberately not fillable:
 * only Services set them (forceFill), inside a transaction with their record.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int $category_id
 * @property int $location_id
 * @property AssetStatus $status
 * @property AssetCondition $condition
 * @property string|null $brand
 * @property string|null $serial_number
 * @property Carbon|null $purchase_date
 * @property string|null $purchase_price numeric(14,2) Rupiah, kept as string to avoid float
 * @property int|null $useful_life_years
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['name', 'condition', 'brand', 'serial_number', 'purchase_date', 'purchase_price', 'useful_life_years', 'notes'])]
class Asset extends Model
{
    /** @use HasFactory<AssetFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => AssetStatus::class,
            'condition' => AssetCondition::class,
            'purchase_date' => 'date',
            'purchase_price' => 'decimal:2',
        ];
    }

    /**
     * Rows $actor may see: everything for admin/staff, only assets the
     * employee currently borrows.
     *
     * @param  Builder<Asset>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $actor): void
    {
        if ($actor->isEmployee()) {
            $query->whereHas('loans', fn (Builder $loans) => $loans
                ->where('borrower_id', $actor->id)
                ->whereNull('returned_at'));
        }
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class)->withTrashed();
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class)->withTrashed();
    }

    /** @return HasMany<Loan, $this> */
    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    /** @return HasMany<AssetMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(AssetMovement::class);
    }

    /** @return HasMany<Maintenance, $this> */
    public function maintenances(): HasMany
    {
        return $this->hasMany(Maintenance::class);
    }
}
