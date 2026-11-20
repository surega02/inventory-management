<?php

namespace Tests\Feature\Chat;

use App\Chat\Tools\ChatTool;
use App\Chat\Tools\GetMaintenanceHistory;
use App\Chat\Tools\ToolRegistry;
use App\Enums\AssetStatus;
use App\Enums\MaintenanceStatus;
use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\Category;
use App\Models\Loan;
use App\Models\Location;
use App\Models\Maintenance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Scope of every chatbot tool for the three roles. The fixture:
 * - "me" (employee) borrows LAP-0001 (overdue) and earlier returned MON-0002;
 * - "other" (employee) borrows LAP-0002;
 * - MON-0001 is available in the IT room (L2), had one maintenance job and one move.
 */
class ToolsTest extends TestCase
{
    use RefreshDatabase;

    private User $me;

    private User $other;

    protected function setUp(): void
    {
        parent::setUp();

        $laptop = Category::factory()->create(['code' => 'LAP', 'name' => 'Laptop']);
        $monitor = Category::factory()->create(['code' => 'MON', 'name' => 'Monitor']);
        $wh = Location::factory()->create(['code' => 'HO-L1-WH', 'name' => 'Head Office L1 â€“ Warehouse']);
        $it = Location::factory()->create(['code' => 'HO-L2-IT', 'name' => 'Head Office L2 â€“ IT Room']);

        $this->me = User::factory()->employee()->create(['name' => 'Mia Me', 'email' => 'me@demo.test']);
        $this->other = User::factory()->employee()->create(['name' => 'Oscar Other', 'email' => 'other@demo.test']);
        $staff = User::factory()->staff()->create();

        $asset = fn (string $code, Category $c, Location $l, AssetStatus $s = AssetStatus::Available) => Asset::factory()->create([
            'code' => $code, 'name' => "{$c->name} {$code}", 'category_id' => $c->id, 'location_id' => $l->id, 'status' => $s,
            'purchase_price' => '12345000.00',
        ]);

        $lap1 = $asset('LAP-0001', $laptop, $wh, AssetStatus::Borrowed);
        $lap2 = $asset('LAP-0002', $laptop, $wh, AssetStatus::Borrowed);
        $mon1 = $asset('MON-0001', $monitor, $it);
        $mon2 = $asset('MON-0002', $monitor, $it);

        Loan::factory()->create(['asset_id' => $lap1->id, 'borrower_id' => $this->me->id, 'recorded_by' => $staff->id,
            'loaned_at' => now()->subDays(10), 'due_date' => today()->subDays(2)]);
        Loan::factory()->create(['asset_id' => $lap2->id, 'borrower_id' => $this->other->id, 'recorded_by' => $staff->id,
            'loaned_at' => now()->subDays(3), 'due_date' => today()->addWeek()]);
        Loan::factory()->returned()->create(['asset_id' => $mon2->id, 'borrower_id' => $this->me->id, 'recorded_by' => $staff->id,
            'loaned_at' => '2026-10-05 09:00', 'due_date' => '2026-10-12', 'returned_at' => '2026-10-10 15:00']);

        (new Maintenance(['description' => 'Replace backlight', 'vendor' => 'PT Servis', 'cost' => '450000']))->forceFill([
            'asset_id' => $mon1->id, 'started_at' => '2026-09-01', 'completed_at' => '2026-09-05',
            'status' => MaintenanceStatus::Done, 'recorded_by' => $staff->id,
        ])->save();
        (new AssetMovement(['reason' => 'Desk swap']))->forceFill([
            'asset_id' => $mon1->id, 'from_location_id' => $wh->id, 'to_location_id' => $it->id,
            'moved_at' => '2026-09-10 10:00', 'recorded_by' => $staff->id,
        ])->save();
    }

    /** @param array<string, mixed> $args */
    private function tool(User $user, string $tool, array $args = []): array
    {
        $found = collect(app(ToolRegistry::class)->for($user))->first(fn (ChatTool $t) => $t->name() === $tool);
        $this->assertNotNull($found, "{$tool} is not offered to this user");

        // what the LLM would actually receive
        return json_decode((string) json_encode($found->handle($args)), true);
    }

    private function user(string $role): User
    {
        return match ($role) {
            'employee' => $this->me,
            default => User::factory()->{$role}()->create(),
        };
    }

    /** @return array<string, array{string}> */
    public static function managers(): array
    {
        return ['admin' => ['admin'], 'staff' => ['staff']];
    }

    /** @return array<string, array{string}> */
    public static function roles(): array
    {
        return [...self::managers(), 'employee' => ['employee']];
    }

    // --- registry -----------------------------------------------------------

    #[DataProvider('managers')]
    public function test_managers_get_all_six_tools(string $role): void
    {
        $this->assertSame(
            ['search_assets', 'get_asset_detail', 'list_active_loans', 'get_loan_history', 'get_maintenance_history', 'get_inventory_summary'],
            array_map(fn (ChatTool $t) => $t->name(), app(ToolRegistry::class)->for($this->user($role))),
        );
    }

    public function test_employees_never_get_the_maintenance_tool(): void
    {
        $this->assertSame(
            ['search_assets', 'get_asset_detail', 'list_active_loans', 'get_loan_history', 'get_inventory_summary'],
            array_map(fn (ChatTool $t) => $t->name(), app(ToolRegistry::class)->for($this->me)),
        );
    }

    // --- search_assets ------------------------------------------------------

    #[DataProvider('managers')]
    public function test_managers_search_every_asset(string $role): void
    {
        $result = $this->tool($this->user($role), 'search_assets');

        $this->assertSame(4, $result['total']);
        $this->assertSame(['LAP-0001', 'LAP-0002', 'MON-0001', 'MON-0002'], array_column($result['items'], 'code'));
        $this->assertSame(['code' => 'MON-0001', 'name' => 'Monitor MON-0001', 'category' => 'Monitor', 'location' => 'Head Office L2 â€“ IT Room', 'status' => 'available'], $result['items'][2]);
    }

    public function test_employees_only_find_what_they_borrow(): void
    {
        $this->assertSame(['LAP-0001'], array_column($this->tool($this->me, 'search_assets')['items'], 'code'));
        $this->assertSame(0, $this->tool($this->me, 'search_assets', ['query' => 'LAP-0002'])['total']);
        $this->assertSame(0, $this->tool($this->me, 'search_assets', ['category' => 'monitor'])['total']);
    }

    public function test_category_and_location_match_loosely(): void
    {
        $staff = $this->user('staff');

        $this->assertSame(2, $this->tool($staff, 'search_assets', ['category' => 'Laptops'])['total']);
        $this->assertSame(2, $this->tool($staff, 'search_assets', ['category' => 'mon'])['total']);
        $this->assertSame(['MON-0001', 'MON-0002'], array_column($this->tool($staff, 'search_assets', ['location' => 'L2', 'status' => 'available'])['items'], 'code'));
    }

    public function test_unknown_filters_come_back_as_invalid_argument(): void
    {
        $staff = $this->user('staff');

        $category = $this->tool($staff, 'search_assets', ['category' => 'Spaceship']);
        $this->assertSame('invalid_argument', $category['error']);
        $this->assertStringContainsString('Laptop (LAP)', $category['detail']);

        $this->assertSame('invalid_argument', $this->tool($staff, 'search_assets', ['status' => 'lost'])['error']);
        $this->assertSame('invalid_argument', $this->tool($staff, 'search_assets', ['query' => ['nested' => 'array']])['error']);
    }

    // --- get_asset_detail ---------------------------------------------------

    #[DataProvider('managers')]
    public function test_managers_get_any_asset_with_history(string $role): void
    {
        $lap = $this->tool($this->user($role), 'get_asset_detail', ['code' => 'lap-0002']);
        $this->assertSame('LAP-0002', $lap['code']);
        $this->assertSame('Oscar Other', $lap['active_loan']['borrower_name']);
        $this->assertFalse($lap['active_loan']['is_overdue']);

        $mon = $this->tool($this->user($role), 'get_asset_detail', ['code' => 'MON-0001']);
        $this->assertNull($mon['active_loan']);
        $this->assertSame([['from' => 'Head Office L1 â€“ Warehouse', 'to' => 'Head Office L2 â€“ IT Room', 'moved_at' => '2026-09-10', 'reason' => 'Desk swap']], $mon['recent_movements']);
        $this->assertSame('Replace backlight', $mon['recent_maintenances'][0]['description']);
    }

    public function test_employees_get_their_own_asset_without_operational_history(): void
    {
        $lap = $this->tool($this->me, 'get_asset_detail', ['code' => 'LAP-0001']);

        $this->assertSame('Mia Me', $lap['active_loan']['borrower_name']);
        $this->assertTrue($lap['active_loan']['is_overdue']);
        $this->assertArrayNotHasKey('recent_movements', $lap);
        $this->assertArrayNotHasKey('recent_maintenances', $lap);
    }

    public function test_someone_elses_asset_looks_exactly_like_a_missing_one(): void
    {
        $others = $this->tool($this->me, 'get_asset_detail', ['code' => 'LAP-0002']);
        $missing = $this->tool($this->me, 'get_asset_detail', ['code' => 'LAP-9999']);

        $this->assertSame('not_found', $others['error']);
        $this->assertSame(str_replace('LAP-0002', 'X', $others['detail']), str_replace('LAP-9999', 'X', $missing['detail']));
        // returned assets are no longer "hers" either
        $this->assertSame('not_found', $this->tool($this->me, 'get_asset_detail', ['code' => 'MON-0002'])['error']);
    }

    public function test_asset_code_is_required(): void
    {
        $this->assertSame('invalid_argument', $this->tool($this->me, 'get_asset_detail')['error']);
    }

    // --- list_active_loans --------------------------------------------------

    #[DataProvider('managers')]
    public function test_managers_list_every_active_loan_most_overdue_first(string $role): void
    {
        $all = $this->tool($this->user($role), 'list_active_loans');
        $this->assertSame(2, $all['total']);
        $this->assertSame(['LAP-0001', 'LAP-0002'], array_column($all['items'], 'asset_code'));

        $overdue = $this->tool($this->user($role), 'list_active_loans', ['overdue_only' => true, 'category' => 'laptop']);
        $this->assertSame(1, $overdue['total']);
        $this->assertSame('Mia Me', $overdue['items'][0]['borrower_name']);

        $this->assertSame(0, $this->tool($this->user($role), 'list_active_loans', ['category' => 'Monitors'])['total']);
    }

    public function test_employees_only_list_their_own_active_loans(): void
    {
        $mine = $this->tool($this->me, 'list_active_loans');

        $this->assertSame(1, $mine['total']);
        $this->assertSame(['LAP-0001'], array_column($mine['items'], 'asset_code'));
    }

    // --- get_loan_history ---------------------------------------------------

    #[DataProvider('managers')]
    public function test_managers_filter_history_by_borrower_asset_and_dates(string $role): void
    {
        $user = $this->user($role);

        $this->assertSame(3, $this->tool($user, 'get_loan_history')['total']);
        $this->assertSame(['LAP-0002'], array_column($this->tool($user, 'get_loan_history', ['borrower' => 'oscar'])['items'], 'asset_code'));
        $this->assertSame(1, $this->tool($user, 'get_loan_history', ['borrower' => 'OTHER@demo.test'])['total']);

        $october = $this->tool($user, 'get_loan_history', ['date_from' => '2026-10-01', 'date_to' => '2026-10-31']);
        $this->assertSame([['asset_code' => 'MON-0002', 'asset_name' => 'Monitor MON-0002', 'borrower_name' => 'Mia Me', 'loaned_at' => '2026-10-05',
            'due_date' => '2026-10-12', 'returned_at' => '2026-10-10', 'return_condition' => 'good']], $october['items']);
    }

    public function test_employees_only_get_their_own_history_whatever_they_ask(): void
    {
        $mine = $this->tool($this->me, 'get_loan_history');
        $this->assertSame(['LAP-0001', 'MON-0002'], array_column($mine['items'], 'asset_code'));

        // borrower and smuggled identity arguments change nothing
        $asked = $this->tool($this->me, 'get_loan_history', ['borrower' => 'Oscar Other', 'user_id' => $this->other->id, 'borrower_id' => $this->other->id]);
        $this->assertSame($mine, $asked);
        $this->assertSame(0, $this->tool($this->me, 'get_loan_history', ['asset_code' => 'LAP-0002'])['total']);
    }

    public function test_bad_dates_come_back_as_invalid_argument(): void
    {
        $this->assertSame('invalid_argument', $this->tool($this->me, 'get_loan_history', ['date_from' => 'last month'])['error']);
        $this->assertSame('invalid_argument', $this->tool($this->me, 'get_loan_history', ['date_from' => '2026-02-30'])['error']);
        $this->assertSame('invalid_argument', $this->tool($this->me, 'get_loan_history', ['date_from' => '2026-11-02', 'date_to' => '2026-11-01'])['error']);
    }

    // --- get_maintenance_history --------------------------------------------

    #[DataProvider('managers')]
    public function test_managers_get_maintenance_history(string $role): void
    {
        $result = $this->tool($this->user($role), 'get_maintenance_history', ['asset_code' => 'mon-0001', 'status' => 'done']);

        $this->assertSame(1, $result['total']);
        $this->assertSame(['asset_code' => 'MON-0001', 'asset_name' => 'Monitor MON-0001', 'description' => 'Replace backlight', 'vendor' => 'PT Servis',
            'started_at' => '2026-09-01', 'completed_at' => '2026-09-05', 'cost' => '450000.00', 'status' => 'done'], $result['items'][0]);
        $this->assertSame('invalid_argument', $this->tool($this->user($role), 'get_maintenance_history', ['status' => 'broken'])['error']);
    }

    public function test_maintenance_service_returns_nothing_to_an_employee_even_if_the_tool_leaks_through(): void
    {
        $result = app()->make(GetMaintenanceHistory::class, ['actor' => $this->me])->handle([]);

        $this->assertSame(['total' => 0, 'truncated' => false, 'items' => []], $result);
    }

    // --- get_inventory_summary ----------------------------------------------

    #[DataProvider('managers')]
    public function test_managers_get_inventory_counts(string $role): void
    {
        $summary = $this->tool($this->user($role), 'get_inventory_summary');

        $this->assertSame(['available' => 2, 'borrowed' => 2, 'maintenance' => 0, 'broken' => 0, 'disposed' => 0], $summary['by_status']);
        $this->assertSame([2, 1], [$summary['active_loans'], $summary['overdue_loans']]);
        $this->assertSame(['code' => 'LAP', 'name' => 'Laptop', 'count' => 2], $summary['by_category'][0]);
    }

    public function test_employees_only_get_their_own_loan_counts(): void
    {
        $this->assertSame(['active_loans' => 1, 'overdue_loans' => 1], $this->tool($this->me, 'get_inventory_summary'));
    }

    // --- sanitizing & row cap -----------------------------------------------

    /** @return array<string, array{string, string, array<string, mixed>}> */
    public static function everyCall(): array
    {
        $calls = [
            'search_assets' => [],
            'get_asset_detail' => ['code' => 'LAP-0001'],
            'list_active_loans' => [],
            'get_loan_history' => [],
            'get_maintenance_history' => [],
            'get_inventory_summary' => [],
        ];

        $cases = [];
        foreach (array_keys(self::roles()) as $role) {
            foreach ($calls as $tool => $args) {
                if ($role !== 'employee' || $tool !== 'get_maintenance_history') {
                    $cases["{$role} {$tool}"] = [$role, $tool, $args];
                }
            }
        }

        return $cases;
    }

    /** @param array<string, mixed> $args */
    #[DataProvider('everyCall')]
    public function test_tool_output_never_carries_ids_emails_secrets_or_prices(string $role, string $tool, array $args): void
    {
        $user = $this->user($role);
        $result = $this->tool($user, $tool, $args);
        $json = (string) json_encode($result);

        $keys = [];
        array_walk_recursive($result, function ($v, $k) use (&$keys) {
            $keys[] = $k;
        });
        $keys = array_filter($keys, 'is_string');

        $this->assertEmpty(array_filter($keys, fn (string $k) => $k === 'id' || str_ends_with($k, '_id')), "internal id in {$json}");
        $this->assertEmpty(array_intersect($keys, ['email', 'password', 'remember_token', 'purchase_price']), $json);
        $this->assertStringNotContainsString('@demo.test', $json);
        $this->assertStringNotContainsString('12345000', $json);

        if ($role === 'employee') {
            $this->assertStringNotContainsString('Oscar Other', $json);
            $this->assertStringNotContainsString('LAP-0002', $json);
        }
    }

    public function test_lists_are_capped_at_twenty_rows_and_say_so(): void
    {
        Asset::factory()->count(25)->create();
        $staff = $this->user('staff');

        $default = $this->tool($staff, 'search_assets');
        $this->assertCount(10, $default['items']);
        $this->assertSame(29, $default['total']);
        $this->assertTrue($default['truncated']);

        $this->assertCount(20, $this->tool($staff, 'search_assets', ['limit' => 500])['items']);
        $this->assertCount(1, $this->tool($staff, 'search_assets', ['limit' => -3])['items']);
        $this->assertCount(10, $this->tool($staff, 'search_assets', ['limit' => 'all'])['items']);
        $this->assertFalse($this->tool($staff, 'list_active_loans', ['limit' => 20])['truncated']);
    }

    public function test_long_free_text_is_clipped(): void
    {
        Maintenance::query()->update(['description' => str_repeat('very long text ', 100)]);

        $item = $this->tool($this->user('admin'), 'get_maintenance_history')['items'][0];

        $this->assertLessThanOrEqual(203, mb_strlen($item['description']));
    }
}
