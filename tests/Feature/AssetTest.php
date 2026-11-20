<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\MaintenanceStatus;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Loan;
use App\Models\Location;
use App\Models\Maintenance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AssetTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string}> */
    public static function managers(): array
    {
        return ['admin' => ['admin'], 'staff' => ['staff']];
    }

    #[DataProvider('managers')]
    public function test_managers_list_search_filter_and_sort(string $role): void
    {
        $laptops = Category::factory()->create(['name' => 'Laptop']);
        Asset::factory()->create(['code' => 'LAP-0002', 'name' => 'ThinkPad', 'category_id' => $laptops->id]);
        Asset::factory()->create(['code' => 'LAP-0001', 'name' => 'MacBook', 'category_id' => $laptops->id, 'status' => AssetStatus::Broken]);
        Asset::factory()->create(['code' => 'MON-0001', 'name' => 'Dell monitor']);

        $this->actingAs(User::factory()->{$role}()->create());

        $this->get(route('assets.index'))->assertInertia(fn (Assert $p) => $p->component('assets/index')
            ->where('assets.total', 3)->where('assets.data.0.code', 'LAP-0001'));
        $this->get(route('assets.index', ['q' => 'think']))->assertInertia(fn (Assert $p) => $p->where('assets.total', 1));
        $this->get(route('assets.index', ['category_id' => $laptops->id, 'status' => 'broken']))
            ->assertInertia(fn (Assert $p) => $p->where('assets.total', 1)->where('assets.data.0.code', 'LAP-0001'));
        $this->get(route('assets.index', ['sort' => '-code']))->assertInertia(fn (Assert $p) => $p->where('assets.data.0.code', 'MON-0001'));
        $this->get(route('assets.index', ['sort' => 'password']))->assertSessionHasErrors('sort');
    }

    #[DataProvider('managers')]
    public function test_managers_create_with_generated_code_and_audit(string $role): void
    {
        $actor = User::factory()->{$role}()->create();
        $category = Category::factory()->create(['code' => 'LAP', 'default_useful_life_years' => 4]);
        $location = Location::factory()->create();

        $this->actingAs($actor)->post(route('assets.store'), [
            'name' => 'ThinkPad T14', 'category_id' => $category->id, 'location_id' => $location->id,
            'condition' => 'good', 'purchase_price' => '15000000.50',
            'status' => 'disposed', 'code' => 'HACK-1', // not accepted from the request
        ])->assertRedirect();

        $asset = Asset::sole();
        $this->assertSame(['LAP-0001', AssetStatus::Available, 4, $actor->id], [$asset->code, $asset->status, $asset->useful_life_years, $asset->created_by]);
        $this->assertSame('15000000.50', $asset->purchase_price);
        $this->assertTrue(Activity::whereMorphedTo('subject', $asset)->where('event', 'created')->exists());
    }

    #[DataProvider('managers')]
    public function test_managers_update_but_never_code_category_location_or_status(string $role): void
    {
        $asset = Asset::factory()->create(['code' => 'LAP-0001']);
        $original = $asset->only(['code', 'category_id', 'location_id', 'status']);

        $this->actingAs(User::factory()->{$role}()->create())->put(route('assets.update', $asset), [
            'name' => 'Renamed', 'condition' => 'fair',
            'code' => 'X-1', 'category_id' => 999, 'location_id' => 999, 'status' => 'disposed',
        ])->assertRedirect(route('assets.show', $asset))->assertSessionHasNoErrors();

        $asset->refresh();
        $this->assertSame('Renamed', $asset->name);
        $this->assertSame($original, $asset->only(['code', 'category_id', 'location_id', 'status']));
    }

    public function test_detail_scope_per_role(): void
    {
        $employee = User::factory()->employee()->create();
        $own = Loan::factory()->create(['borrower_id' => $employee->id])->asset;
        $other = Asset::factory()->create();

        foreach (['admin', 'staff'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create())->get(route('assets.show', $other))
                ->assertInertia(fn (Assert $p) => $p->component('assets/show')->whereNot('asset.purchase_price', null)->where('can.manage_assets', true));
        }

        $this->actingAs($employee)->get(route('assets.show', $own))
            ->assertInertia(fn (Assert $p) => $p->where('asset.code', $own->code)
                ->where('asset.purchase_price', null) // employees never see prices
                ->where('abilities', ['update' => false, 'dispose' => false, 'delete' => false, 'return' => false, 'move' => false, 'maintain' => false]));
        $this->get(route('assets.show', $other))->assertNotFound(); // 404, not 403: no hint it exists
        $this->get(route('assets.show', 999999))->assertNotFound();
    }

    public function test_employee_is_forbidden_from_every_management_route(): void
    {
        $asset = Asset::factory()->create();
        $this->actingAs(User::factory()->employee()->create());

        $this->get(route('assets.index'))->assertForbidden();
        $this->get(route('assets.create'))->assertForbidden();
        $this->get(route('assets.edit', $asset))->assertForbidden();
        $this->post(route('assets.store'), [])->assertForbidden();
        $this->put(route('assets.update', $asset), ['name' => 'X', 'condition' => 'good'])->assertForbidden();
        $this->post(route('assets.dispose', $asset), ['reason' => 'x'])->assertForbidden();
        $this->delete(route('assets.destroy', $asset))->assertForbidden();

        $this->assertSame(AssetStatus::Available, $asset->fresh()->status);
        $this->assertNotSoftDeleted($asset);
    }

    #[DataProvider('managers')]
    public function test_dispose_records_reason_and_is_final(string $role): void
    {
        $asset = Asset::factory()->create(['status' => AssetStatus::Broken]);
        $this->actingAs(User::factory()->{$role}()->create());

        $this->post(route('assets.dispose', $asset), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->post(route('assets.dispose', $asset), ['reason' => 'Beyond repair'])->assertRedirect(route('assets.show', $asset));

        $this->assertSame(AssetStatus::Disposed, $asset->fresh()->status);
        $this->assertSame('Beyond repair', Activity::whereMorphedTo('subject', $asset)->where('event', 'disposed')->sole()->properties['reason']);

        $this->postJson(route('assets.dispose', $asset), ['reason' => 'again'])->assertStatus(409);
    }

    public function test_dispose_is_refused_while_borrowed_or_under_maintenance(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        $borrowed = Loan::factory()->create()->asset;
        $serviced = Asset::factory()->create(['status' => AssetStatus::Maintenance]);
        (new Maintenance(['description' => 'Fix']))->forceFill([
            'asset_id' => $serviced->id, 'started_at' => today(), 'status' => MaintenanceStatus::InProgress, 'recorded_by' => User::factory()->create()->id,
        ])->save();

        $this->postJson(route('assets.dispose', $borrowed), ['reason' => 'x'])->assertStatus(409);
        $this->postJson(route('assets.dispose', $serviced), ['reason' => 'x'])->assertStatus(409);

        $this->assertSame(AssetStatus::Borrowed, $borrowed->fresh()->status);
        $this->assertSame(AssetStatus::Maintenance, $serviced->fresh()->status);
    }

    public function test_only_admin_soft_deletes_and_only_without_history(): void
    {
        $clean = Asset::factory()->create();
        $withHistory = Loan::factory()->returned()->create()->asset;

        $this->actingAs(User::factory()->staff()->create())->delete(route('assets.destroy', $clean))->assertForbidden();

        $this->actingAs(User::factory()->admin()->create());
        $this->delete(route('assets.destroy', $clean))->assertRedirect(route('assets.index'));
        $this->deleteJson(route('assets.destroy', $withHistory))->assertStatus(409);

        $this->assertSoftDeleted($clean);
        $this->assertNotSoftDeleted($withHistory);
    }

    public function test_validation_rejects_bad_input(): void
    {
        Asset::factory()->create(['serial_number' => 'SN-1']);
        $this->actingAs(User::factory()->staff()->create());

        $this->post(route('assets.store'), [
            'name' => '', 'category_id' => 999, 'location_id' => 999, 'condition' => 'shiny',
            'serial_number' => 'SN-1', 'purchase_date' => now()->addDay()->toDateString(), 'purchase_price' => '-5',
        ])->assertSessionHasErrors(['name', 'category_id', 'location_id', 'condition', 'serial_number', 'purchase_date', 'purchase_price']);
    }
}
