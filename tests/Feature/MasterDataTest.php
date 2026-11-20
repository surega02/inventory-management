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

        $this->post(route('categories.store'), ['code' => 'OLD', 'name' => 'Again'])->assertSessionHasErrors('code');
        $this->post(route('categories.store'), ['code' => 'A B', 'name' => 'Bad'])->assertSessionHasErrors('code');
    }
}
