<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\MaintenanceStatus;
use App\Models\Asset;
use App\Models\Loan;
use App\Models\Maintenance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class MaintenanceTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string}> */
    public static function managers(): array
    {
        return ['admin' => ['admin'], 'staff' => ['staff']];
    }

    /** @return array<string, array{AssetStatus}> */
    public static function serviceable(): array
    {
        return ['available' => [AssetStatus::Available], 'broken' => [AssetStatus::Broken]];
    }

    /** @return array<string, array{AssetStatus}> */
    public static function unserviceable(): array
    {
        return ['borrowed' => [AssetStatus::Borrowed], 'maintenance' => [AssetStatus::Maintenance], 'disposed' => [AssetStatus::Disposed]];
    }

    #[DataProvider('managers')]
    public function test_managers_start_and_complete_maintenance(string $role): void
    {
        $actor = User::factory()->{$role}()->create();
        $asset = Asset::factory()->create();
        $this->actingAs($actor);

        $this->post(route('assets.maintenances.store', $asset), [
            'description' => 'Battery replacement', 'started_at' => today()->subDays(2)->toDateString(),
            'vendor' => 'PT Servis Jaya', 'cost' => '250000',
            'status' => 'done', 'recorded_by' => User::factory()->create()->id, // not accepted from the request
        ])->assertRedirect()->assertSessionHasNoErrors();

        $maintenance = Maintenance::sole();
        $this->assertSame([MaintenanceStatus::InProgress, $actor->id, '250000.00', null], [$maintenance->status, $maintenance->recorded_by, $maintenance->cost, $maintenance->completed_at]);
        $this->assertSame(AssetStatus::Maintenance, $asset->fresh()->status);
        $this->assertTrue(Activity::whereMorphedTo('subject', $maintenance)->where('event', 'created')->where('causer_id', $actor->id)->exists());

        $this->post(route('maintenances.complete', $maintenance), ['completed_at' => today()->toDateString(), 'cost' => '300000.50'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $maintenance->refresh();
        $this->assertSame([MaintenanceStatus::Done, '300000.50'], [$maintenance->status, $maintenance->cost]);
        $this->assertTrue($maintenance->completed_at->isToday());
        $this->assertSame(AssetStatus::Available, $asset->fresh()->status);
        $this->assertTrue(Activity::whereMorphedTo('subject', $maintenance)->where('event', 'updated')->where('causer_id', $actor->id)->exists());
    }

    #[DataProvider('serviceable')]
    public function test_available_and_broken_assets_can_go_into_maintenance(AssetStatus $status): void
    {
        $asset = Asset::factory()->create(['status' => $status]);

        $this->actingAs(User::factory()->staff()->create())
            ->post(route('assets.maintenances.store', $asset), ['description' => 'Fix', 'started_at' => today()->toDateString()])
            ->assertSessionHasNoErrors();

        $this->assertSame(AssetStatus::Maintenance, $asset->fresh()->status);
    }

    #[DataProvider('unserviceable')]
    public function test_other_statuses_cannot_go_into_maintenance(AssetStatus $status): void
    {
        $asset = Asset::factory()->create(['status' => $status]);

        $this->actingAs(User::factory()->staff()->create())
            ->postJson(route('assets.maintenances.store', $asset), ['description' => 'Fix', 'started_at' => today()->toDateString()])
            ->assertStatus(409);

        $this->assertSame(0, Maintenance::count());
        $this->assertSame($status, $asset->fresh()->status);
    }

    public function test_completing_twice_is_a_conflict_and_keeps_the_estimate_when_no_cost_is_given(): void
    {
        $maintenance = $this->openMaintenance(['cost' => '100000']);
        $this->actingAs(User::factory()->staff()->create());

        $this->post(route('maintenances.complete', $maintenance), ['completed_at' => today()->toDateString()])->assertSessionHasNoErrors();
        $this->assertSame('100000.00', $maintenance->fresh()->cost);

        $maintenance->asset->forceFill(['status' => AssetStatus::Broken])->save();
        $this->postJson(route('maintenances.complete', $maintenance), ['completed_at' => today()->toDateString()])->assertStatus(409);
        $this->assertSame(AssetStatus::Broken, $maintenance->asset->fresh()->status);
    }

    public function test_maintenance_validation(): void
    {
        $asset = Asset::factory()->create();
        $this->actingAs(User::factory()->staff()->create());

        $this->post(route('assets.maintenances.store', $asset), [])->assertSessionHasErrors(['description', 'started_at']);
        $this->post(route('assets.maintenances.store', $asset), [
            'description' => 'Fix', 'started_at' => today()->addDay()->toDateString(), 'cost' => '-1', 'vendor' => str_repeat('x', 151),
        ])->assertSessionHasErrors(['started_at', 'cost', 'vendor']);
        $this->assertSame(0, Maintenance::count());

        $open = $this->openMaintenance(['started_at' => today()->subDays(5)]);
        $this->post(route('maintenances.complete', $open), ['completed_at' => today()->subDays(6)->toDateString()])->assertSessionHasErrors('completed_at');
        $this->post(route('maintenances.complete', $open), ['completed_at' => today()->addDay()->toDateString()])->assertSessionHasErrors('completed_at');
        $this->post(route('maintenances.complete', $open), ['completed_at' => today()->toDateString(), 'cost' => '1.234'])->assertSessionHasErrors('cost');
        $this->assertSame(MaintenanceStatus::InProgress, $open->fresh()->status);
    }

    public function test_employee_cannot_start_or_complete_maintenance(): void
    {
        $employee = User::factory()->employee()->create();
        $own = Loan::factory()->create(['borrower_id' => $employee->id])->asset;
        $open = $this->openMaintenance();
        $this->actingAs($employee);

        $this->post(route('assets.maintenances.store', $own), ['description' => 'Fix', 'started_at' => today()->toDateString()])->assertForbidden();
        $this->post(route('maintenances.complete', $open), ['completed_at' => today()->toDateString()])->assertForbidden();

        $this->assertSame(1, Maintenance::count());
        $this->assertSame(MaintenanceStatus::InProgress, $open->fresh()->status);
    }

    public function test_asset_detail_shows_maintenance_history_to_managers_only(): void
    {
        $employee = User::factory()->employee()->create();
        $asset = Asset::factory()->create(['status' => AssetStatus::Borrowed]);
        (new Maintenance(['description' => 'Screen flicker', 'cost' => '75000']))->forceFill([
            'asset_id' => $asset->id, 'started_at' => today()->subMonth(), 'completed_at' => today()->subMonth()->addDay(),
            'status' => MaintenanceStatus::Done, 'recorded_by' => User::factory()->staff()->create()->id,
        ])->save();
        Loan::factory()->create(['asset_id' => $asset->id, 'borrower_id' => $employee->id]);

        foreach ([User::factory()->admin()->create(), User::factory()->staff()->create()] as $manager) {
            $this->actingAs($manager)->get(route('assets.show', $asset))->assertInertia(fn (Assert $p) => $p
                ->has('maintenances', 1)->where('maintenances.0.description', 'Screen flicker')->where('maintenances.0.cost', '75000.00')
                ->where('abilities.maintain', true));
        }

        // costs are operational/financial data, like purchase_price
        $this->actingAs($employee)->get(route('assets.show', $asset))->assertInertia(fn (Assert $p) => $p
            ->where('maintenances', [])->where('abilities.maintain', false));
    }

    /** @param array<string, mixed> $attributes */
    private function openMaintenance(array $attributes = []): Maintenance
    {
        $asset = Asset::factory()->create(['status' => AssetStatus::Maintenance]);
        $maintenance = new Maintenance(['description' => 'Fan noise', 'cost' => $attributes['cost'] ?? null]);
        $maintenance->forceFill([
            'asset_id' => $asset->id,
            'started_at' => $attributes['started_at'] ?? today()->subDay(),
            'status' => MaintenanceStatus::InProgress,
            'recorded_by' => User::factory()->staff()->create()->id,
        ])->save();

        return $maintenance;
    }
}
