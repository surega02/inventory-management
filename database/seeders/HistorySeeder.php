<?php

namespace Database\Seeders;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\MaintenanceStatus;
use App\Enums\Role;
use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\Loan;
use App\Models\Maintenance;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * A year of history on top of DemoSeeder, dated relative to today so the demo
 * always has active, overdue and returned loans. Keeps the DB invariants:
 * one active loan / in-progress maintenance per asset, asset.status matching
 * the open record, movement chains ending at asset.location_id.
 */
class HistorySeeder extends Seeder
{
    private CarbonImmutable $today;

    /** @var Collection<int, User> */
    private Collection $recorders;

    public function run(): void
    {
        $this->today = CarbonImmutable::today();
        $this->recorders = User::role([Role::Admin, Role::WarehouseStaff])->get();

        $employees = User::role(Role::Employee)->where('is_active', true)->where('email', '!=', 'employee@demo.test')->get();
        $demo = User::where('email', 'employee@demo.test')->firstOrFail();

        $pool = Asset::where('status', AssetStatus::Available)->whereHas('category', fn ($q) => $q->where('code', '!=', 'FUR'))->get()->shuffle();

        // Returned loans: every window ends at least 45 days ago, so they never overlap the active loans below.
        foreach ($pool->take(260) as $asset) {
            $end = $this->today->subDays(fake()->numberBetween(45, 120));
            foreach (range(1, fake()->numberBetween(1, 2)) as $ignored) {
                $loanedAt = $end->subDays(fake()->numberBetween(5, 25));
                $this->returnedLoan($asset, $employees->random(), $loanedAt, $end);
                $end = $loanedAt->subDays(fake()->numberBetween(10, 60));
            }
        }

        // Demo employee: one overdue laptop, one monitor due soon, a few returned loans.
        $laptop = $pool->first(fn (Asset $a) => str_starts_with($a->code, 'LAP-'));
        $monitor = $pool->first(fn (Asset $a) => str_starts_with($a->code, 'MON-'));
        $this->activeLoan($laptop, $demo, $this->today->subDays(20), $this->today->subDays(6));
        $this->activeLoan($monitor, $demo, $this->today->subDays(5), $this->today->addDays(9));
        foreach ($pool->slice(300, 3) as $asset) {
            $loanedAt = $this->today->subDays(fake()->numberBetween(130, 300));
            $this->returnedLoan($asset, $demo, $loanedAt, $loanedAt->addDays(fake()->numberBetween(3, 14)));
        }

        // ~70 more active loans, roughly 1 in 4 overdue.
        foreach ($pool->reject(fn (Asset $a) => $a->status === AssetStatus::Borrowed)->take(70) as $asset) {
            $loanedAt = $this->today->subDays(fake()->numberBetween(1, 30));
            $this->activeLoan($asset, $employees->random(), $loanedAt, $loanedAt->addDays(fake()->randomElement([14, 21, 30, 30])));
        }

        $this->seedMaintenance();
        $this->seedMovements();
    }

    private function activeLoan(Asset $asset, User $borrower, CarbonImmutable $loanedAt, CarbonImmutable $due): void
    {
        (new Loan(['due_date' => $due, 'notes' => null]))->forceFill([
            'asset_id' => $asset->id,
            'borrower_id' => $borrower->id,
            'loaned_at' => $loanedAt->setTime(fake()->numberBetween(8, 16), fake()->numberBetween(0, 59)),
            'recorded_by' => $this->recorders->random()->id,
            'created_at' => $loanedAt,
        ])->save();

        $asset->forceFill(['status' => AssetStatus::Borrowed])->save();
    }

    private function returnedLoan(Asset $asset, User $borrower, CarbonImmutable $loanedAt, CarbonImmutable $returnedAt): void
    {
        (new Loan(['due_date' => $loanedAt->addDays(14), 'notes' => null]))->forceFill([
            'asset_id' => $asset->id,
            'borrower_id' => $borrower->id,
            'loaned_at' => $loanedAt->setTime(9, 0),
            'returned_at' => $returnedAt->setTime(15, 0),
            'return_condition' => fake()->randomElement([AssetCondition::Good, AssetCondition::Good, AssetCondition::Good, AssetCondition::Fair]),
            'recorded_by' => $this->recorders->random()->id,
            'returned_recorded_by' => $this->recorders->random()->id,
            'created_at' => $loanedAt,
        ])->save();
    }

    private function seedMaintenance(): void
    {
        $vendors = ['PT Servis Jaya', 'CV Teknik Mandiri', 'Authorized Service Center', null];
        $issues = ['Battery replacement', 'Screen flicker', 'Keyboard keys stuck', 'Fan noise / overheating', 'Lamp replacement', 'Paper jam mechanism', 'Firmware update', 'Broken hinge', 'Routine cleaning'];

        // Past, completed jobs on assets that are not out on loan right now.
        foreach (Asset::whereIn('status', [AssetStatus::Available, AssetStatus::Broken])->inRandomOrder()->take(90)->get() as $asset) {
            $started = $this->today->subDays(fake()->numberBetween(30, 360));
            $this->maintenance($asset, fake()->randomElement($issues), fake()->randomElement($vendors), $started, $started->addDays(fake()->numberBetween(1, 10)));
        }

        // Open jobs: half from broken assets, half from available ones; the asset moves to `maintenance`.
        $open = Asset::where('status', AssetStatus::Broken)->inRandomOrder()->take(8)->get()
            ->merge(Asset::where('status', AssetStatus::Available)->inRandomOrder()->take(8)->get());

        foreach ($open as $asset) {
            $this->maintenance($asset, fake()->randomElement($issues), fake()->randomElement($vendors), $this->today->subDays(fake()->numberBetween(0, 12)), null);
            $asset->forceFill(['status' => AssetStatus::Maintenance])->save();
        }
    }

    private function maintenance(Asset $asset, string $description, ?string $vendor, CarbonImmutable $started, ?CarbonImmutable $completed): void
    {
        (new Maintenance([
            'description' => $description,
            'vendor' => $vendor,
            'cost' => $completed && $vendor ? fake()->numberBetween(1, 30) * 50_000 : null,
        ]))->forceFill([
            'asset_id' => $asset->id,
            'started_at' => $started,
            'completed_at' => $completed,
            'status' => $completed ? MaintenanceStatus::Done : MaintenanceStatus::InProgress,
            'recorded_by' => $this->recorders->random()->id,
            'created_at' => $started,
        ])->save();
    }

    /** Builds each chain backwards from the current location, so the last move lands where the asset is now. */
    private function seedMovements(): void
    {
        $locationIds = Asset::query()->distinct()->pluck('location_id');
        $reasons = ['Team relocation', 'New hire setup', 'Returned to warehouse', 'Branch request', 'Meeting room refresh', null];

        foreach (Asset::where('status', '!=', AssetStatus::Disposed)->inRandomOrder()->take(150)->get() as $asset) {
            $to = $asset->location_id;
            $movedAt = $this->today->subDays(fake()->numberBetween(50, 120));

            foreach (range(1, fake()->numberBetween(1, 3)) as $ignored) {
                $from = $locationIds->reject(fn ($id) => $id === $to)->random();

                (new AssetMovement(['reason' => fake()->randomElement($reasons)]))->forceFill([
                    'asset_id' => $asset->id,
                    'from_location_id' => $from,
                    'to_location_id' => $to,
                    'moved_at' => $movedAt->setTime(10, 0),
                    'recorded_by' => $this->recorders->random()->id,
                    'created_at' => $movedAt,
                ])->save();

                [$to, $movedAt] = [$from, $movedAt->subDays(fake()->numberBetween(20, 120))];
            }
        }
    }
}
