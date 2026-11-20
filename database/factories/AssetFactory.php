<?php

namespace Database\Factories;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test/seed shortcut: sets code and status directly. Real assets get their
 * code from the asset-code service (row lock on the category).
 *
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('TST-#####'),
            'name' => fake()->words(3, true),
            'category_id' => Category::factory(),
            'location_id' => Location::factory(),
            'status' => AssetStatus::Available,
            'condition' => AssetCondition::Good,
            'brand' => fake()->company(),
            'serial_number' => null,
            'purchase_date' => fake()->dateTimeBetween('-4 years', '-1 month'),
            'purchase_price' => fake()->numberBetween(500, 30000) * 1000,
            'useful_life_years' => 4,
        ];
    }
}
