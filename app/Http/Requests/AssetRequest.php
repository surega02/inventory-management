<?php

namespace App\Http\Requests;

use App\Enums\AssetCondition;
use App\Models\Asset;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Store and update. Category and location are only taken on create: the code
 * prefix is tied to the category, and location changes go through movements.
 */
class AssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $asset = $this->route('asset');

        return $asset instanceof Asset
            ? $this->user()?->can('update', $asset) ?? false
            : $this->user()?->can('create', Asset::class) ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $asset = $this->route('asset');

        return [
            ...($asset instanceof Asset ? [] : [
                'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
                'location_id' => ['required', 'integer', Rule::exists('locations', 'id')->whereNull('deleted_at')],
            ]),
            'name' => ['required', 'string', 'max:150'],
            'condition' => ['required', Rule::enum(AssetCondition::class)],
            'brand' => ['nullable', 'string', 'max:100'],
            // unique across soft-deleted assets too, matching the partial unique index
            'serial_number' => ['nullable', 'string', 'max:100', Rule::unique('assets', 'serial_number')->ignore($asset)],
            'purchase_date' => ['nullable', 'date', 'before_or_equal:today'],
            'purchase_price' => ['nullable', 'decimal:0,2', 'min:0', 'max:999999999999.99'],
            'useful_life_years' => ['nullable', 'integer', 'min:1', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, mixed> the fillable attributes only */
    public function assetAttributes(): array
    {
        return $this->safe()->except(['category_id', 'location_id']);
    }
}
