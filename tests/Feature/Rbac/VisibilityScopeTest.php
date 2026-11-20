<?php

namespace Tests\Feature\Rbac;

use App\Models\Asset;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/** Row-level scope (visibleTo) and the 404-not-403 rule, for all three roles. */
class VisibilityScopeTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    private Loan $ownActive;

    private Loan $ownReturned;

    private Loan $othersActive;

    private Asset $unborrowed;

    protected function setUp(): void
    {
        parent::setUp();

        $this->employee = User::factory()->employee()->create();
        $other = User::factory()->employee()->create();

        $this->ownActive = Loan::factory()->create(['borrower_id' => $this->employee->id]);
        $this->ownReturned = Loan::factory()->returned()->create(['borrower_id' => $this->employee->id]);
        $this->othersActive = Loan::factory()->create(['borrower_id' => $other->id]);
        $this->unborrowed = Asset::factory()->create();
    }

    public function test_admin_and_staff_see_every_asset_and_loan(): void
    {
        foreach ([User::factory()->admin()->create(), User::factory()->staff()->create()] as $actor) {
            $this->assertSame(Asset::count(), Asset::visibleTo($actor)->count());
            $this->assertSame(3, Loan::visibleTo($actor)->count());
        }
    }

    public function test_employee_sees_only_assets_they_currently_borrow(): void
    {
        $this->assertSame(
            [$this->ownActive->asset_id],
            Asset::visibleTo($this->employee)->pluck('id')->all(),
        );
    }

    public function test_employee_sees_only_their_own_loans_including_returned(): void
    {
        $this->assertEqualsCanonicalizing(
            [$this->ownActive->id, $this->ownReturned->id],
            Loan::visibleTo($this->employee)->pluck('id')->all(),
        );
    }

    /** Services must group OR-filters in a closure; a bare ->orWhere() after visibleTo() would escape the scope. */
    public function test_grouped_or_filters_stay_inside_employee_scope(): void
    {
        $ids = Asset::visibleTo($this->employee)
            ->where(fn ($q) => $q->where('id', $this->othersActive->asset_id)->orWhere('id', $this->ownActive->asset_id))
            ->pluck('id')->all();

        $this->assertSame([$this->ownActive->asset_id], $ids);
    }

    public function test_viewing_an_asset_outside_employee_scope_is_not_found_not_forbidden(): void
    {
        $gate = Gate::forUser($this->employee);

        $this->assertTrue($gate->inspect('view', $this->ownActive->asset)->allowed());

        foreach ([$this->othersActive->asset, $this->ownReturned->asset, $this->unborrowed] as $asset) {
            $response = $gate->inspect('view', $asset);
            $this->assertTrue($response->denied());
            $this->assertSame(404, $response->status());
        }
    }

    public function test_admin_and_staff_may_view_any_asset(): void
    {
        foreach ([User::factory()->admin()->create(), User::factory()->staff()->create()] as $actor) {
            $this->assertTrue(Gate::forUser($actor)->allows('view', $this->unborrowed));
        }
    }
}
