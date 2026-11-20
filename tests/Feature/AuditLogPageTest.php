<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AuditLogPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_open_the_audit_log(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('audit-logs.index'))
            ->assertOk()->assertInertia(fn (Assert $p) => $p->component('audit-logs/index')->where('can.view_audit', true));

        foreach ([User::factory()->staff()->create(), User::factory()->employee()->create()] as $user) {
            $this->actingAs($user)->get(route('audit-logs.index'))->assertForbidden();
        }
    }

    public function test_entries_show_who_did_what_to_which_record(): void
    {
        $staff = User::factory()->staff()->create(['name' => 'Siti Staff']);
        $asset = Asset::factory()->create(['code' => 'LAP-0042']);
        $to = Location::factory()->create();
        $this->actingAs($staff)->post(route('assets.movements.store', $asset), ['to_location_id' => $to->id]);
        $this->post(route('assets.dispose', $asset), ['reason' => 'Beyond repair']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('audit-logs.index', ['causer_id' => $staff->id, 'subject_type' => 'movement']))
            ->assertInertia(fn (Assert $p) => $p->where('entries.total', 1)
                ->where('entries.data.0.causer', 'Siti Staff')
                ->where('entries.data.0.event', 'created')
                ->where('entries.data.0.subject', ['type' => 'movement', 'label' => 'LAP-0042', 'asset_id' => $asset->id])
                ->where('entries.data.0.changes.new.to_location_id', $to->id));

        $this->get(route('audit-logs.index', ['event' => 'disposed']))
            ->assertInertia(fn (Assert $p) => $p->where('entries.total', 1)
                ->where('entries.data.0.properties.reason', 'Beyond repair')
                ->where('entries.data.0.subject.label', 'LAP-0042'));

        $this->get(route('audit-logs.index', ['subject_type' => 'asset', 'event' => 'updated']))
            ->assertInertia(fn (Assert $p) => $p->where('entries.total', 2) // location change + status change
                ->where('entries.data.0.changes.old.status', 'available')->where('entries.data.0.changes.new.status', 'disposed'));
    }

    public function test_date_filters_and_validation(): void
    {
        $admin = User::factory()->admin()->create();
        Activity::query()->update(['created_at' => now()->subDays(10)]);
        Asset::factory()->create(); // logged today

        $this->actingAs($admin);
        $total = Activity::count();
        $this->get(route('audit-logs.index', ['date_from' => today()->toDateString()]))
            ->assertInertia(fn (Assert $p) => $p->where('entries.total', fn (int $n) => $n > 0 && $n < $total));
        $this->get(route('audit-logs.index', ['date_to' => today()->subDays(5)->toDateString()]))
            ->assertInertia(fn (Assert $p) => $p->where('entries.total', fn (int $n) => $n > 0 && $n < $total));

        $this->get(route('audit-logs.index', ['subject_type' => 'App\\Models\\User', 'event' => 'hacked', 'date_from' => '2026-02-02', 'date_to' => '2026-01-01']))
            ->assertSessionHasErrors(['subject_type', 'event', 'date_to']);
    }

    public function test_causer_filter_lists_only_users_who_did_something(): void
    {
        $actor = User::factory()->staff()->create(['name' => 'Actor']);
        User::factory()->employee()->create(['name' => 'Idle']);
        $this->actingAs($actor);
        Location::factory()->create();

        $this->actingAs(User::factory()->admin()->create())->get(route('audit-logs.index'))
            ->assertInertia(fn (Assert $p) => $p->where('causers', fn ($causers) => collect($causers)->pluck('name')->all() === ['Actor']));
    }
}
