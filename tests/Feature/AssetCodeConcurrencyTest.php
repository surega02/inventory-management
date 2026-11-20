<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use App\Services\AssetService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Two "requests" at once, made deterministic: a second DB connection holds the
 * category row lock (as a concurrent create would mid-transaction) while the
 * service tries to create. Needs committed rows, so this class truncates
 * instead of wrapping each test in a transaction.
 */
class AssetCodeConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    /** The trait truncates before each test; also clean up after, so committed rows never leak into other test classes. */
    protected function tearDown(): void
    {
        $this->truncateTablesForAllConnections();

        parent::tearDown();
    }

    public function test_a_concurrent_create_waits_for_the_category_lock_and_numbers_stay_unique(): void
    {
        $actor = User::factory()->create();
        $category = Category::factory()->create(['code' => 'LAP']);
        $location = Location::factory()->create();
        $service = app(AssetService::class);

        Config::set('database.connections.pgsql_other', config('database.connections.pgsql'));
        $other = DB::connection('pgsql_other');
        $other->beginTransaction();
        $other->select('select id from categories where id = ? for update', [$category->id]);

        // While "request A" holds the lock, "request B" must block, not read a stale sequence.
        DB::statement("set lock_timeout = '300ms'");
        try {
            $service->create($actor, $category->id, $location->id, ['name' => 'B']);
            $this->fail('create() did not wait for the category row lock.');
        } catch (QueryException $e) {
            $this->assertSame('55P03', $e->getCode(), 'expected lock_not_available');
            // must block on reading the counter; blocking only on the later UPDATE would still allow a duplicate number
            $this->assertStringContainsString('for update', strtolower($e->getSql()));
        } finally {
            $other->rollBack();
            DB::statement('set lock_timeout = 0');
        }

        $codes = collect(['A', 'B', 'C'])
            ->map(fn ($name) => $service->create($actor, $category->id, $location->id, ['name' => $name])->code);

        $this->assertSame(['LAP-0001', 'LAP-0002', 'LAP-0003'], $codes->all());
        $this->assertSame(3, $category->fresh()->last_sequence);
    }

    public function test_codes_keep_growing_past_9999(): void
    {
        $category = Category::factory()->create(['code' => 'MON']);
        $category->forceFill(['last_sequence' => 9999])->save();

        $asset = app(AssetService::class)->create(User::factory()->create(), $category->id, Location::factory()->create()->id, ['name' => 'X']);

        $this->assertSame('MON-10000', $asset->code);
    }
}
