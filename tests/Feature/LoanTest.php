<?php

namespace Tests\Feature;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Asset;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class LoanTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string}> */
    public static function managers(): array
    {
        return ['admin' => ['admin'], 'staff' => ['staff']];
    }

    #[DataProvider('managers')]
    public function test_managers_lend_an_available_asset(string $role): void
    {
        $actor = User::factory()->{$role}()->create();
        $borrower = User::factory()->employee()->create();
        $asset = Asset::factory()->create();

        $this->actingAs($actor)->from(route('loans.index'))->post(route('loans.store'), [
            'asset_id' => $asset->id, 'borrower_id' => $borrower->id,
            'due_date' => today()->addWeek()->toDateString(), 'notes' => 'For the audit trip',
            'recorded_by' => $borrower->id, 'loaned_at' => '2020-01-01', // not accepted from the request
        ])->assertRedirect(route('loans.index'))->assertSessionHasNoErrors();

        $loan = Loan::sole();
        $this->assertSame([$asset->id, $borrower->id, $actor->id, null], [$loan->asset_id, $loan->borrower_id, $loan->recorded_by, $loan->returned_at]);
        $this->assertTrue($loan->loaned_at->isToday());
        $this->assertSame(AssetStatus::Borrowed, $asset->fresh()->status);
        $this->assertTrue(Activity::whereMorphedTo('subject', $loan)->where('event', 'created')->where('causer_id', $actor->id)->exists());
        $this->assertTrue(Activity::whereMorphedTo('subject', $asset)->where('event', 'updated')->exists());
    }

    /** @return array<string, array{AssetStatus}> */
    public static function unavailable(): array
    {
        return array_combine(
            ['borrowed', 'maintenance', 'broken', 'disposed'],
            [[AssetStatus::Borrowed], [AssetStatus::Maintenance], [AssetStatus::Broken], [AssetStatus::Disposed]],
        );
    }

    #[DataProvider('unavailable')]
    public function test_lending_an_asset_that_is_not_available_is_a_conflict(AssetStatus $status): void
    {
        $asset = Asset::factory()->create(['status' => $status]);

        $this->actingAs(User::factory()->staff()->create())
            ->postJson(route('loans.store'), ['asset_id' => $asset->id, 'borrower_id' => User::factory()->create()->id])
            ->assertStatus(409);

        $this->assertSame(0, Loan::count());
        $this->assertSame($status, $asset->fresh()->status);
    }

    public function test_second_lend_of_the_same_asset_is_a_conflict(): void
    {
        $asset = Asset::factory()->create();
        $this->actingAs(User::factory()->staff()->create());
        $payload = ['asset_id' => $asset->id, 'borrower_id' => User::factory()->create()->id];

        $this->post(route('loans.store'), $payload)->assertSessionHasNoErrors();
        $this->postJson(route('loans.store'), $payload)->assertStatus(409);

        $this->assertSame(1, Loan::count());
    }

    public function test_lend_validation(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        $deleted = Asset::factory()->create();
        $deleted->delete();

        $this->post(route('loans.store'), [
            'asset_id' => $deleted->id,
            'borrower_id' => User::factory()->inactive()->create()->id,
            'due_date' => today()->subDay()->toDateString(),
        ])->assertSessionHasErrors(['asset_id', 'borrower_id', 'due_date']);

        $this->post(route('loans.store'), [])->assertSessionHasErrors(['asset_id', 'borrower_id']);
        $this->assertSame(0, Loan::count());
    }

    #[DataProvider('managers')]
    public function test_managers_record_a_return(string $role): void
    {
        $actor = User::factory()->{$role}()->create();
        $loan = Loan::factory()->create(['notes' => 'Trip']);

        $this->actingAs($actor)->post(route('loans.return', $loan), ['return_condition' => 'fair', 'notes' => 'Scratched lid'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $loan->refresh();
        $this->assertNotNull($loan->returned_at);
        $this->assertSame([AssetCondition::Fair, $actor->id, "Trip\nReturn: Scratched lid"], [$loan->return_condition, $loan->returned_recorded_by, $loan->notes]);
        $this->assertSame(AssetStatus::Available, $loan->asset->status);
        $this->assertSame(AssetCondition::Fair, $loan->asset->condition);
        $this->assertTrue(Activity::whereMorphedTo('subject', $loan)->where('event', 'updated')->where('causer_id', $actor->id)->exists());
    }

    public function test_return_can_mark_the_asset_broken_and_cannot_happen_twice(): void
    {
        $loan = Loan::factory()->create();
        $this->actingAs(User::factory()->staff()->create());

        $this->post(route('loans.return', $loan), ['return_condition' => 'poor', 'mark_broken' => '1'])->assertSessionHasNoErrors();
        $this->assertSame(AssetStatus::Broken, $loan->asset->fresh()->status);

        $this->postJson(route('loans.return', $loan), ['return_condition' => 'good'])->assertStatus(409);
        $this->assertSame(AssetStatus::Broken, $loan->asset->fresh()->status);

        $this->post(route('loans.return', Loan::factory()->create()), ['return_condition' => 'shiny'])->assertSessionHasErrors('return_condition');
    }

    public function test_employee_cannot_lend_or_return(): void
    {
        $employee = User::factory()->employee()->create();
        $own = Loan::factory()->create(['borrower_id' => $employee->id]);
        $asset = Asset::factory()->create();
        $this->actingAs($employee);

        $this->post(route('loans.store'), ['asset_id' => $asset->id, 'borrower_id' => $employee->id])->assertForbidden();
        $this->post(route('loans.return', $own), ['return_condition' => 'good'])->assertForbidden();

        $this->assertSame(1, Loan::count());
        $this->assertNull($own->fresh()->returned_at);
        $this->assertSame(AssetStatus::Available, $asset->fresh()->status);
    }

    #[DataProvider('managers')]
    public function test_managers_list_all_loans_with_status_filter_and_search(string $role): void
    {
        $alice = User::factory()->create(['name' => 'Alice']);
        Loan::factory()->create(['borrower_id' => $alice->id, 'due_date' => today()->addDay()]);
        Loan::factory()->create(['due_date' => today()->subDay()]); // overdue
        Loan::factory()->returned()->create();

        $this->actingAs(User::factory()->{$role}()->create());

        $this->get(route('loans.index'))->assertInertia(fn (Assert $p) => $p->component('loans/index')
            ->where('loans.total', 3)->where('abilities', ['lend' => true, 'return' => true])->missing('assetOptions')
            ->where('can.manage_assets', true)); // shared sidebar prop must not be shadowed by page props
        $this->get(route('loans.index', ['status' => 'active']))->assertInertia(fn (Assert $p) => $p->where('loans.total', 2));
        $this->get(route('loans.index', ['status' => 'overdue']))->assertInertia(fn (Assert $p) => $p->where('loans.total', 1)->where('loans.data.0.is_overdue', true));
        $this->get(route('loans.index', ['status' => 'returned']))->assertInertia(fn (Assert $p) => $p->where('loans.total', 1));
        $this->get(route('loans.index', ['q' => 'alic']))->assertInertia(fn (Assert $p) => $p->where('loans.total', 1)->where('loans.data.0.borrower', 'Alice'));
        $this->get(route('loans.index', ['status' => 'lost']))->assertSessionHasErrors('status');
    }

    public function test_employee_lists_only_their_own_loans(): void
    {
        $employee = User::factory()->employee()->create();
        Loan::factory()->create(['borrower_id' => $employee->id]);
        Loan::factory()->returned()->create(['borrower_id' => $employee->id]);
        $other = Loan::factory()->create();

        $this->actingAs($employee)->get(route('loans.index'))->assertInertia(fn (Assert $p) => $p
            ->where('loans.total', 2)->where('abilities', ['lend' => false, 'return' => false]));
        // searching for someone else's asset cannot widen the scope
        $this->get(route('loans.index', ['q' => $other->asset->code]))->assertInertia(fn (Assert $p) => $p->where('loans.total', 0));
        $this->get(route('loans.index', ['status' => 'returned']))->assertInertia(fn (Assert $p) => $p->where('loans.total', 1));
        // lend form options are never filled for employees
        $this->get(route('loans.index'), $this->partial('assetOptions,borrowerOptions'))
            ->assertJsonPath('props.assetOptions', [])->assertJsonPath('props.borrowerOptions', []);
    }

    public function test_lend_form_options_are_searched_server_side(): void
    {
        Asset::factory()->create(['code' => 'LAP-0001', 'name' => 'ThinkPad']);
        Asset::factory()->create(['code' => 'LAP-0002', 'name' => 'ThinkPad X1', 'status' => AssetStatus::Broken]);
        Asset::factory()->create(['code' => 'MON-0001', 'name' => 'Monitor']);
        User::factory()->create(['name' => 'Budi Active']);
        User::factory()->inactive()->create(['name' => 'Budi Inactive']);

        $this->actingAs(User::factory()->staff()->create(['name' => 'Staff']));

        $this->get(route('loans.index', ['asset_q' => 'think', 'borrower_q' => 'budi']), $this->partial('assetOptions,borrowerOptions'))
            ->assertJsonCount(1, 'props.assetOptions')->assertJsonPath('props.assetOptions.0.label', 'LAP-0001 · ThinkPad')
            ->assertJsonCount(1, 'props.borrowerOptions')->assertJsonPath('props.borrowerOptions.0.label', fn (string $l) => str_starts_with($l, 'Budi Active'));
    }

    public function test_asset_detail_shows_loan_history_scoped_per_role(): void
    {
        $employee = User::factory()->employee()->create();
        $asset = Asset::factory()->create(['status' => AssetStatus::Borrowed]);
        Loan::factory()->returned()->create(['asset_id' => $asset->id, 'loaned_at' => now()->subMonth()]); // someone else's
        Loan::factory()->create(['asset_id' => $asset->id, 'borrower_id' => $employee->id]);

        $this->actingAs(User::factory()->staff()->create())->get(route('assets.show', $asset))
            ->assertInertia(fn (Assert $p) => $p->has('loans', 2)->where('abilities.return', true));

        $this->actingAs($employee)->get(route('assets.show', $asset))
            ->assertInertia(fn (Assert $p) => $p->has('loans', 1)->where('loans.0.borrower', $employee->name)->where('abilities.return', false));
    }

    /** @return array<string, string> headers for an Inertia partial reload of $only */
    private function partial(string $only): array
    {
        $version = app(HandleInertiaRequests::class)->version(request());

        return [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) $version,
            'X-Inertia-Partial-Component' => 'loans/index',
            'X-Inertia-Partial-Data' => $only,
        ];
    }
}
