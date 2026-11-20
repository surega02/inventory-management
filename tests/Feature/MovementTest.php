<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\Loan;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class MovementTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string}> */
    public static function managers(): array
    {
        return ['admin' => ['admin'], 'staff' => ['staff']];
    }

    #[DataProvider('managers')]
    public function test_managers_move_an_asset(string $role): void
    {
        $actor = User::factory()->{$role}()->create();
        $asset = Asset::factory()->create(['status' => AssetStatus::Broken]); // broken assets can still be moved
        $from = $asset->location_id;
        $to = Location::factory()->create();

        $this->actingAs($actor)->from(route('assets.show', $asset))->post(route('assets.movements.store', $asset), [
            'to_location_id' => $to->id, 'reason' => 'Team relocation',
            'from_location_id' => $to->id, 'recorded_by' => User::factory()->create()->id, // not accepted from the request
        ])->assertRedirect(route('assets.show', $asset))->assertSessionHasNoErrors();

        $movement = AssetMovement::sole();
        $this->assertSame([$from, $to->id, $actor->id, 'Team relocation'], [$movement->from_location_id, $movement->to_location_id, $movement->recorded_by, $movement->reason]);
        $this->assertTrue($movement->moved_at->isToday());
        $this->assertSame($to->id, $asset->fresh()->location_id);
        $this->assertTrue(Activity::whereMorphedTo('subject', $movement)->where('event', 'created')->where('causer_id', $actor->id)->exists());
        $this->assertTrue(Activity::whereMorphedTo('subject', $asset)->where('event', 'updated')->exists());
    }

    public function test_moved_at_can_be_backdated_but_not_in_the_future(): void
    {
        $asset = Asset::factory()->create();
        $to = Location::factory()->create();
        $this->actingAs(User::factory()->staff()->create());

        $this->post(route('assets.movements.store', $asset), ['to_location_id' => $to->id, 'moved_at' => today()->addDay()->toDateString()])
            ->assertSessionHasErrors('moved_at');
        $this->post(route('assets.movements.store', $asset), ['to_location_id' => $to->id, 'moved_at' => today()->subDays(3)->toDateString()])
            ->assertSessionHasNoErrors();

        $this->assertTrue(AssetMovement::sole()->moved_at->isSameDay(today()->subDays(3)));
    }

    public function test_move_validation(): void
    {
        $asset = Asset::factory()->create();
        $deleted = Location::factory()->create();
        $deleted->delete();
        $this->actingAs(User::factory()->staff()->create());

        $this->post(route('assets.movements.store', $asset), [])->assertSessionHasErrors('to_location_id');
        $this->post(route('assets.movements.store', $asset), ['to_location_id' => $deleted->id, 'reason' => str_repeat('x', 256)])
            ->assertSessionHasErrors(['to_location_id', 'reason']);

        $this->assertSame(0, AssetMovement::count());
    }

    /** @return array<string, array{AssetStatus}> */
    public static function immovable(): array
    {
        return ['borrowed' => [AssetStatus::Borrowed], 'disposed' => [AssetStatus::Disposed]];
    }

    #[DataProvider('immovable')]
    public function test_borrowed_or_disposed_assets_cannot_be_moved(AssetStatus $status): void
    {
        $asset = Asset::factory()->create(['status' => $status]);
        $location = $asset->location_id;

        $this->actingAs(User::factory()->staff()->create())
            ->postJson(route('assets.movements.store', $asset), ['to_location_id' => Location::factory()->create()->id])
            ->assertStatus(409);

        $this->assertSame(0, AssetMovement::count());
        $this->assertSame($location, $asset->fresh()->location_id);
    }

    /** Confirmed by Ega (20 Nov 2026): only borrowed and disposed assets are blocked. */
    public function test_an_asset_under_maintenance_can_be_moved(): void
    {
        $asset = Asset::factory()->create(['status' => AssetStatus::Maintenance]);
        $to = Location::factory()->create();

        $this->actingAs(User::factory()->staff()->create())
            ->post(route('assets.movements.store', $asset), ['to_location_id' => $to->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($to->id, $asset->fresh()->location_id);
        $this->assertSame(AssetStatus::Maintenance, $asset->fresh()->status);
    }

    public function test_moving_to_the_current_location_is_a_conflict(): void
    {
        $asset = Asset::factory()->create();

        $this->actingAs(User::factory()->staff()->create())
            ->postJson(route('assets.movements.store', $asset), ['to_location_id' => $asset->location_id])
            ->assertStatus(409);

        $this->assertSame(0, AssetMovement::count());
    }

    public function test_employee_cannot_move_even_their_own_borrowed_asset(): void
    {
        $employee = User::factory()->employee()->create();
        $loan = Loan::factory()->create(['borrower_id' => $employee->id]);
        $asset = Asset::factory()->create();

        $this->actingAs($employee);
        foreach ([$loan->asset, $asset] as $target) {
            $this->post(route('assets.movements.store', $target), ['to_location_id' => Location::factory()->create()->id])->assertForbidden();
        }

        $this->assertSame(0, AssetMovement::count());
    }

    public function test_asset_detail_shows_location_history_to_managers_only(): void
    {
        $employee = User::factory()->employee()->create();
        $asset = Asset::factory()->create();
        $to = Location::factory()->create(['name' => 'Branch Bandung']);
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('assets.movements.store', $asset), ['to_location_id' => $to->id, 'reason' => 'Branch request']);
        $asset->forceFill(['status' => AssetStatus::Borrowed])->save();
        Loan::factory()->create(['asset_id' => $asset->id, 'borrower_id' => $employee->id]);

        foreach ([User::factory()->admin()->create(), User::factory()->staff()->create()] as $manager) {
            $this->actingAs($manager)->get(route('assets.show', $asset))->assertInertia(fn (Assert $p) => $p
                ->has('movements', 1)->where('movements.0.to', 'Branch Bandung')->where('movements.0.reason', 'Branch request')
                ->where('abilities.move', true)->has('locations'));
        }

        $this->actingAs($employee)->get(route('assets.show', $asset))->assertInertia(fn (Assert $p) => $p
            ->where('movements', [])->where('locations', [])->where('abilities.move', false));
    }
}
