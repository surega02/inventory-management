<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_model_changes_are_logged_with_causer_and_old_values(): void
    {
        $staff = User::factory()->staff()->create();
        $asset = Asset::factory()->create(['name' => 'Old name']);
        $this->actingAs($staff);

        $asset->update(['name' => 'New name']);

        $activity = Activity::where('subject_type', $asset->getMorphClass())->where('subject_id', $asset->id)->where('event', 'updated')->sole();
        $this->assertTrue($activity->causer->is($staff));
        $this->assertSame(['name' => 'Old name'], $activity->attribute_changes['old']);
        $this->assertSame(['name' => 'New name'], $activity->attribute_changes['attributes']);
    }

    public function test_secrets_and_timestamps_are_never_logged(): void
    {
        $user = User::factory()->create();
        $user->update(['password' => 'something-new']);
        $user->forceFill(['last_login_at' => now()])->save();

        $logged = Activity::where('subject_type', $user->getMorphClass())->where('subject_id', $user->id)->get();

        $this->assertSame(['created'], $logged->pluck('event')->all(), 'password/last_login_at-only changes must not create entries');
        $this->assertStringNotContainsString('password', json_encode($logged->first()->toArray()));
    }

    public function test_logins_and_failed_logins_are_logged(): void
    {
        $user = User::factory()->employee()->create();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password']);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

        $this->assertSame(['login_failed', 'login'], Activity::inLog('auth')->orderBy('id')->pluck('event')->all());
        $this->assertNotNull($user->fresh()->last_login_at);
    }
}
