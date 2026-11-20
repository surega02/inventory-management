<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SharedPropsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string, string, array{bool, bool, bool}}> [factory state, role, [manage_assets, manage_users, view_audit]] */
    public static function roles(): array
    {
        return [
            'admin' => ['admin', 'admin', [true, true, true]],
            'staff' => ['staff', 'warehouse_staff', [true, false, false]],
            'employee' => ['employee', 'employee', [false, false, false]],
        ];
    }

    /** @param array{bool, bool, bool} $can */
    #[DataProvider('roles')]
    public function test_shared_props_per_role(string $state, string $role, array $can): void
    {
        $user = User::factory()->{$state}()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('auth.user', ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $role])
            ->where('can', ['manage_assets' => $can[0], 'manage_users' => $can[1], 'view_audit' => $can[2]])
        );
    }
}
