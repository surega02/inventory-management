<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class); // the app always has all three roles; factories only create the one they use
        $this->admin = User::factory()->admin()->create();
    }

    public function test_admin_lists_and_searches_users_without_leaking_secrets(): void
    {
        User::factory()->employee()->create(['name' => 'Budi Santoso']);

        $this->actingAs($this->admin)->get(route('users.index', ['q' => 'budi']))
            ->assertInertia(fn (Assert $page) => $page->component('users/index')
                ->has('users.data', 1)
                ->where('users.data.0.name', 'Budi Santoso')
                ->where('users.data.0.role', 'employee')
                ->missing('users.data.0.password'));
    }

    public function test_admin_creates_user_with_role_and_it_is_audited(): void
    {
        $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'New Staff', 'email' => 'new@demo.test', 'password' => 'secret-pass', 'role' => 'warehouse_staff',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $user = User::where('email', 'new@demo.test')->sole();
        $this->assertTrue($user->hasRole('warehouse_staff'));
        $this->assertTrue($user->is_active);
        $this->assertTrue(Activity::where('event', 'role_changed')->whereMorphedTo('subject', $user)->exists());
    }

    public function test_admin_updates_role_and_blank_password_keeps_the_old_one(): void
    {
        $user = User::factory()->employee()->create();
        $hash = $user->password;

        $this->actingAs($this->admin)->put(route('users.update', $user), [
            'name' => 'Renamed', 'email' => $user->email, 'password' => '', 'role' => 'warehouse_staff',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('Renamed', $user->name);
        $this->assertSame(['warehouse_staff'], $user->getRoleNames()->all());
        $this->assertSame($hash, $user->password);
        $this->assertSame(
            ['old' => 'employee', 'new' => 'warehouse_staff'],
            Activity::where('event', 'role_changed')->whereMorphedTo('subject', $user)->latest('id')->first()->properties->all(),
        );
    }

    public function test_admin_can_set_a_new_password(): void
    {
        $user = User::factory()->employee()->create();

        $this->actingAs($this->admin)->put(route('users.update', $user), [
            'name' => $user->name, 'email' => $user->email, 'password' => 'brand-new-pass', 'role' => 'employee',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('brand-new-pass', $user->fresh()->password));
    }

    public function test_admin_cannot_change_own_role_or_deactivate_self(): void
    {
        $this->actingAs($this->admin)->put(route('users.update', $this->admin), [
            'name' => $this->admin->name, 'email' => $this->admin->email, 'role' => 'employee',
        ])->assertSessionHasErrors('role');

        $this->post(route('users.toggle-active', $this->admin))->assertForbidden();

        $this->assertTrue($this->admin->fresh()->hasRole('admin'));
        $this->assertTrue($this->admin->fresh()->is_active);
    }

    public function test_deactivated_user_is_kicked_out_and_can_be_reactivated(): void
    {
        $user = User::factory()->employee()->create();

        $this->actingAs($this->admin)->post(route('users.toggle-active', $user))->assertRedirect();
        $this->assertFalse($user->fresh()->is_active);

        $this->actingAs($user->fresh())->get(route('dashboard'))->assertRedirect(route('login'));

        $this->actingAs($this->admin)->post(route('users.toggle-active', $user));
        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_emails_stay_unique(): void
    {
        $taken = User::factory()->create();

        $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'Dup', 'email' => $taken->email, 'password' => 'secret-pass', 'role' => 'employee',
        ])->assertSessionHasErrors('email');
    }

    /** @return array<string, array{string}> */
    public static function nonAdmins(): array
    {
        return ['staff' => ['staff'], 'employee' => ['employee']];
    }

    #[DataProvider('nonAdmins')]
    public function test_non_admins_are_forbidden(string $role): void
    {
        $actor = User::factory()->{$role}()->create();
        $target = User::factory()->employee()->create();

        $this->actingAs($actor)->get(route('users.index'))->assertForbidden();
        $this->post(route('users.store'), ['name' => 'X', 'email' => 'x@demo.test', 'password' => 'secret-pass', 'role' => 'admin'])->assertForbidden();
        $this->put(route('users.update', $target), ['name' => 'X', 'email' => $target->email, 'role' => 'admin'])->assertForbidden();
        $this->post(route('users.toggle-active', $target))->assertForbidden();

        $this->assertFalse($target->fresh()->hasRole('admin'));
        $this->assertTrue($target->fresh()->is_active);
        $this->assertSame(0, User::where('email', 'x@demo.test')->count());
    }
}
