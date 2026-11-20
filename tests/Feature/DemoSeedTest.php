<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** The full demo seed must keep the data invariants the Services will rely on. */
class DemoSeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_history_is_consistent(): void
    {
        // explicit: RefreshDatabase's $seed only seeds on the suite's first migration
        $this->seed();

        $violations = [
            'borrowed asset without active loan' => "select count(*) from assets a where status = 'borrowed' and not exists (select 1 from loans l where l.asset_id = a.id and l.returned_at is null)",
            'active loan on non-borrowed asset' => "select count(*) from loans l join assets a on a.id = l.asset_id where l.returned_at is null and a.status <> 'borrowed'",
            'maintenance status without open job' => "select count(*) from assets a where status = 'maintenance' and not exists (select 1 from maintenances m where m.asset_id = a.id and m.status = 'in_progress')",
            'last movement not at current location' => 'select count(*) from assets a join lateral (select to_location_id from asset_movements m where m.asset_id = a.id order by moved_at desc limit 1) lm on true where lm.to_location_id <> a.location_id',
            'overlapping loans on one asset' => "select count(*) from loans x join loans y on x.asset_id = y.asset_id and x.id < y.id and tstzrange(x.loaned_at, coalesce(x.returned_at, 'infinity')) && tstzrange(y.loaned_at, coalesce(y.returned_at, 'infinity'))",
            'category counter behind its codes' => "select count(*) from categories c where exists (select 1 from assets a where a.category_id = c.id and a.code > c.code || '-' || lpad(c.last_sequence::text, 4, '0'))",
        ];

        foreach ($violations as $label => $sql) {
            $this->assertSame(0, (int) DB::scalar($sql), $label);
        }

        $this->assertGreaterThan(0, (int) DB::scalar('select count(*) from loans where returned_at is null and due_date < current_date'),
            'demo needs overdue loans; '.json_encode(DB::selectOne('select current_date as today, count(*) filter (where returned_at is null) as active, min(due_date) filter (where returned_at is null) as min_due from loans')));
        $this->assertSame(2, (int) DB::scalar("select count(*) from loans l join users u on u.id = l.borrower_id where u.email = 'employee@demo.test' and l.returned_at is null"));
    }
}
