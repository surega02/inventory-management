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

    /** @return HasMany<Asset, $this> */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }
}
