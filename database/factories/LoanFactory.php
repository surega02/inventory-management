<?php

namespace Database\Factories;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Active loan by default (returned_at null, asset borrowed).
 *
 * @extends Factory<Loan>
 */
class LoanFactory extends Factory
{
    public function definition(): array
    {
        $loanedAt = fake()->dateTimeBetween('-30 days', '-1 day');

        return [
            'asset_id' => Asset::factory()->state(['status' => AssetStatus::Borrowed]),
            'borrower_id' => User::factory(),
            'loaned_at' => $loanedAt,
            'due_date' => (clone $loanedAt)->modify('+14 days'),
            'recorded_by' => User::factory(),
        ];
    }

    public function returned(): static
    {
        return $this->state(fn (array $attributes) => [
            'asset_id' => Asset::factory(),
            'returned_at' => now(),
            'return_condition' => AssetCondition::Good,
            'returned_recorded_by' => $attributes['recorded_by'],
        ]);
    }
}
