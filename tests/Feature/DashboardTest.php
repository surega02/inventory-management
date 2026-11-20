<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_inactive_users_are_turned_away(): void
    {
        $this->actingAs(User::factory()->staff()->create(['is_active' => false]))
            ->get(route('dashboard'))->assertRedirect(route('login'));
    }

    /** Two loans out (one overdue), one returned, one available and one disposed asset. */
    private function inventory(User $borrower): void
    {
        $laptops = Category::factory()->create(['code' => 'LAP', 'name' => 'Laptop']);
        Loan::factory()->create(['borrower_id' => $borrower->id, 'due_date' => today()->subDays(3),
            'asset_id' => Asset::factory()->create(['category_id' => $laptops->id, 'status' => AssetStatus::Borrowed])->id]);
        Loan::factory()->create(['due_date' => today()->addWeek()]);
        Loan::factory()->returned()->create(['borrower_id' => $borrower->id]);
        Asset::factory()->create(['category_id' => $laptops->id]);
        Asset::factory()->create(['category_id' => $laptops->id, 'status' => AssetStatus::Disposed]);
    }

    /** @return array<string, array{string}> */
    public static function managers(): array
    {
        return ['admin' => ['admin'], 'staff' => ['staff']];
    }

    #[DataProvider('managers')]
    public function test_managers_see_counts_for_the_whole_inventory(string $role): void
    {
        $this->inventory(User::factory()->employee()->create());

        $this->actingAs(User::factory()->{$role}()->create())->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('summary.active_loans', 2)
                ->where('summary.overdue_loans', 1)
                ->where('summary.by_status', ['available' => 2, 'borrowed' => 2, 'maintenance' => 0, 'broken' => 0, 'disposed' => 1])
                // disposed assets are not counted in a category: 2 borrowed-or-available laptops
                ->where('summary.by_category.0', ['code' => 'LAP', 'name' => 'Laptop', 'count' => 2])
                ->has('summary.by_location')
                ->where('loans.kind', 'overdue')
                ->where('loans.total', 1)
                ->has('loans.items', 1, fn (Assert $l) => $l->where('is_overdue', true)->etc())
                ->has('recent', 3));
    }

    public function test_employees_only_see_their_own_loans(): void
    {
        $me = User::factory()->employee()->create();
        $this->inventory($me);

        $this->actingAs($me)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary', ['active_loans' => 1, 'overdue_loans' => 1])
                ->missing('summary.by_status')
                ->missing('summary.by_category')
                ->missing('summary.by_location')
                ->where('loans.kind', 'mine')
                ->has('loans.items', 1, fn (Assert $l) => $l->where('borrower', $me->name)->etc())
                // own active + own returned loan; nobody else's loan, no movements or maintenance
                ->has('recent', 2)
                ->where('recent', fn ($events) => collect($events)->pluck('kind')->sort()->values()->all() === ['lent', 'returned']));
    }

    public function test_employee_without_loans_sees_zeros(): void
    {
        $this->inventory(User::factory()->employee()->create());

        $this->actingAs(User::factory()->employee()->create())->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary', ['active_loans' => 0, 'overdue_loans' => 0])
                ->where('loans.total', 0)
                ->has('loans.items', 0)
                ->has('recent', 0));
    }
}
