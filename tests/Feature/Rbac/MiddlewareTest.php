<?php

namespace Tests\Feature\Rbac;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_user_with_open_session_is_logged_out(): void
    {
        $user = User::factory()->employee()->inactive()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $user = User::factory()->employee()->inactive()->create();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_active_user_can_log_in(): void
    {
        $user = User::factory()->employee()->create();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
        $this->assertAuthenticatedAs($user);
    }

    public function test_role_middleware_allows_listed_roles_only(): void
    {
        Route::middleware(['web', 'auth', 'active', 'role:admin|warehouse_staff'])->get('/_rbac-probe', fn () => 'ok');

        $this->actingAs(User::factory()->admin()->create())->get('/_rbac-probe')->assertOk();
        $this->actingAs(User::factory()->staff()->create())->get('/_rbac-probe')->assertOk();
        $this->actingAs(User::factory()->employee()->create())->get('/_rbac-probe')->assertForbidden();
    }
}
