<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/** Categories and locations: admin + staff manage them, employees get 403 everywhere. */
class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string}> */
    public static function managers(): array
    {
        return ['admin' => ['admin'], 'staff' => ['staff']];
    }

    #[DataProvider('managers')]
    public function test_managers_can_list_create_update_and_delete(string $role): void
    {
        $this->actingAs(User::factory()->{$role}()->create());

        $this->get(route('categories.index'))->assertInertia(fn (Assert $page) => $page->component('categories/index'));
        $this->get(route('locations.index'))->assertInertia(fn (Assert $page) => $page->component('locations/index'));

        $this->post(route('categories.store'), ['code' => ' lap ', 'name' => 'Laptop', 'default_useful_life_years' => 4])
            ->assertRedirect(route('categories.index'))->assertSessionHasNoErrors();
        $category = Category::where('code', 'LAP')->sole();

        $this->put(route('categories.update', $category), ['code' => 'NB', 'name' => 'Notebook'])->assertSessionHasNoErrors();
        $this->assertSame(['NB', 'Notebook'], [$category->fresh()->code, $category->fresh()->name]);

        $this->post(route('locations.store'), ['code' => 'ho-l2-it', 'name' => 'IT Room'])->assertSessionHasNoErrors();
        $location = Location::where('code', 'HO-L2-IT')->sole();
        $this->put(route('locations.update', $location), ['code' => 'HO-L2-IT', 'name' => 'IT Room 2'])->assertSessionHasNoErrors();

        $this->delete(route('categories.destroy', $category))->assertRedirect(route('categories.index'));
        $this->delete(route('locations.destroy', $location))->assertRedirect(route('locations.index'));
        $this->assertSoftDeleted($category);
        $this->assertSoftDeleted($location);

        $this->assertSame(6, Activity::whereIn('subject_type', [$category->getMorphClass(), $location->getMorphClass()])->count(), 'every write is audited');
    }

    public function test_employee_is_forbidden_everywhere(): void
    {
        $this->actingAs(User::factory()->employee()->create());
        $category = Category::factory()->create();
        $location = Location::factory()->create();

        $this->get(route('categories.index'))->assertForbidden();
        $this->get(route('locations.index'))->assertForbidden();
        $this->post(route('categories.store'), ['code' => 'XYZ', 'name' => 'X'])->assertForbidden();
        $this->put(route('categories.update', $category), ['code' => $category->code, 'name' => 'X'])->assertForbidden();
        $this->delete(route('categories.destroy', $category))->assertForbidden();
        $this->post(route('locations.store'), ['code' => 'XYZ', 'name' => 'X'])->assertForbidden();
        $this->delete(route('locations.destroy', $location))->assertForbidden();

        $this->assertNotSoftDeleted($category);
        $this->assertSame(0, Category::where('code', 'XYZ')->count());
    }

    public function test_deleting_master_data_still_in_use_is_refused(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $asset = Asset::factory()->create();

        $this->from(route('categories.index'))->delete(route('categories.destroy', $asset->category))
            ->assertRedirect(route('categories.index'));
        $this->from(route('locations.index'))->delete(route('locations.destroy', $asset->location))
            ->assertRedirect(route('locations.index'));

        $this->assertNotSoftDeleted($asset->category);
        $this->assertNotSoftDeleted($asset->location);

        $this->deleteJson(route('categories.destroy', $asset->category))->assertStatus(409)
            ->assertJsonPath('error.code', 'conflict');
    }

    public function test_category_code_locks_once_an_asset_code_was_issued(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        $category = Category::factory()->create(['code' => 'LAP']);
        $category->forceFill(['last_sequence' => 1])->save();

        $this->put(route('categories.update', $category), ['code' => 'NB', 'name' => 'Renamed'])->assertSessionHasErrors('code');
        $this->put(route('categories.update', $category), ['code' => 'LAP', 'name' => 'Renamed'])->assertSessionHasNoErrors();

        $this->assertSame(['LAP', 'Renamed'], [$category->fresh()->code, $category->fresh()->name]);
    }

    public function test_codes_are_unique_even_against_deleted_rows(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        Category::factory()->create(['code' => 'OLD'])->delete();

        $this->post(route('categories.store'), ['code' => 'OLD', 'name' => 'Again'])
            ->assertSessionHasErrors(['code' => 'A deleted category uses this code. Restore it from the Deleted tab instead.']);
        $this->post(route('categories.store'), ['code' => 'A B', 'name' => 'Bad'])->assertSessionHasErrors('code');
    }

    #[DataProvider('managers')]
    public function test_managers_can_list_restore_and_purge_deleted_categories(string $role): void
    {
        $this->actingAs(User::factory()->{$role}()->create());
        $kept = Category::factory()->create(['code' => 'KEEP']);
        $kept->delete();
        $purged = Category::factory()->create(['code' => 'GONE']);
        $purged->delete();
        Category::factory()->create(['code' => 'LIVE']);

        $this->get(route('categories.index', ['view' => 'deleted']))->assertInertia(fn (Assert $page) => $page
            ->where('view', 'deleted')->where('deleted_count', 2)
            ->has('categories.data', 2)->where('categories.data.0.code', 'GONE'));
        $this->get(route('categories.index'))->assertInertia(fn (Assert $page) => $page
            ->where('view', 'active')->has('categories.data', 1)->where('categories.data.0.code', 'LIVE'));

        $this->post(route('categories.restore', $kept))->assertRedirect(route('categories.index'));
        $this->assertNotSoftDeleted($kept);

        $this->delete(route('categories.force-delete', $purged))->assertRedirect(route('categories.index', ['view' => 'deleted']));
        $this->assertModelMissing($purged);

        // the code is free again once the old row is gone
        $this->post(route('categories.store'), ['code' => 'GONE', 'name' => 'Fresh'])->assertSessionHasNoErrors();

        $this->assertSame(1, Activity::where('subject_type', $kept->getMorphClass())->where('subject_id', $kept->id)->where('event', 'restored')->count());
        $this->assertSame(2, Activity::where('subject_type', $purged->getMorphClass())->where('subject_id', $purged->id)->where('event', 'deleted')->count(), 'soft delete and purge are both audited');
    }

    public function test_employee_cannot_see_restore_or_purge_deleted_categories(): void
    {
        $this->actingAs(User::factory()->employee()->create());
        $category = Category::factory()->create();
        $category->delete();

        $this->get(route('categories.index', ['view' => 'deleted']))->assertForbidden();
        $this->post(route('categories.restore', $category))->assertForbidden();
        $this->delete(route('categories.force-delete', $category))->assertForbidden();

        $this->assertSoftDeleted($category);
    }

    public function test_purge_is_refused_while_deleted_assets_reference_the_category(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $asset = Asset::factory()->create();
        $category = $asset->category;
        $asset->delete();
        $category->delete();

        $this->get(route('categories.index', ['view' => 'deleted']))
            ->assertInertia(fn (Assert $page) => $page->where('categories.data.0.assets_count', 1)
                ->where('categories.data.0.purge_blocker', 'Used by deleted assets, so it can only be restored.'));
        $this->deleteJson(route('categories.force-delete', $category))->assertStatus(409);
        $this->assertSoftDeleted($category);
    }

    public function test_restore_and_purge_only_apply_to_deleted_categories(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $category = Category::factory()->create();

        $this->post(route('categories.restore', $category))->assertNotFound();
        $this->delete(route('categories.force-delete', $category))->assertNotFound();
        $this->assertNotSoftDeleted($category);
    }

    #[DataProvider('managers')]
    public function test_managers_can_list_restore_and_purge_deleted_locations(string $role): void
    {
        $this->actingAs(User::factory()->{$role}()->create());
        $kept = Location::factory()->create(['code' => 'KEEP']);
        $kept->delete();
        $purged = Location::factory()->create(['code' => 'GONE']);
        $purged->delete();

        $this->get(route('locations.index', ['view' => 'deleted']))->assertInertia(fn (Assert $page) => $page
            ->where('view', 'deleted')->where('deleted_count', 2)->has('locations.data', 2)
            ->where('locations.data.0.code', 'GONE')->where('locations.data.0.purge_blocker', null));

        $this->post(route('locations.store'), ['code' => 'GONE', 'name' => 'Again'])
            ->assertSessionHasErrors(['code' => 'A deleted location uses this code. Restore it from the Deleted tab instead.']);

        $this->post(route('locations.restore', $kept))->assertRedirect(route('locations.index'));
        $this->assertNotSoftDeleted($kept);

        $this->delete(route('locations.force-delete', $purged))->assertRedirect(route('locations.index', ['view' => 'deleted']));
        $this->assertModelMissing($purged);
        $this->post(route('locations.store'), ['code' => 'GONE', 'name' => 'Fresh'])->assertSessionHasNoErrors();

        $this->assertSame(1, Activity::where('subject_type', $kept->getMorphClass())->where('subject_id', $kept->id)->where('event', 'restored')->count());
        $this->assertSame(2, Activity::where('subject_type', $purged->getMorphClass())->where('subject_id', $purged->id)->where('event', 'deleted')->count());
    }

    public function test_employee_cannot_see_restore_or_purge_deleted_locations(): void
    {
        $this->actingAs(User::factory()->employee()->create());
        $location = Location::factory()->create();
        $location->delete();

        $this->get(route('locations.index', ['view' => 'deleted']))->assertForbidden();
        $this->post(route('locations.restore', $location))->assertForbidden();
        $this->delete(route('locations.force-delete', $location))->assertForbidden();

        $this->assertSoftDeleted($location);
    }

    public function test_location_purge_is_refused_while_movement_history_references_it(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $old = Location::factory()->create();
        $asset = Asset::factory()->create(['location_id' => $old->id]);
        $new = Location::factory()->create();

        $this->post(route('assets.movements.store', $asset), ['to_location_id' => $new->id])->assertSessionHasNoErrors();
        $old->delete();

        $this->get(route('locations.index', ['view' => 'deleted']))->assertInertia(fn (Assert $page) => $page
            ->where('locations.data.0.purge_blocker', 'Kept for asset movement history, so it can only be restored.'));
        $this->deleteJson(route('locations.force-delete', $old))->assertStatus(409);
        $this->assertSoftDeleted($old);
    }

    public function test_location_restore_and_purge_only_apply_to_deleted_rows(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $location = Location::factory()->create();

        $this->post(route('locations.restore', $location))->assertNotFound();
        $this->delete(route('locations.force-delete', $location))->assertNotFound();
        $this->assertNotSoftDeleted($location);
    }
}
