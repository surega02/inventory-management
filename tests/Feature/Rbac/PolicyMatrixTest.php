<?php

namespace Tests\Feature\Rbac;

use App\Models\Asset;
use App\Models\Category;
use App\Models\Loan;
use App\Models\Location;
use App\Models\Maintenance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Action permissions per role, straight from the role table in CLAUDE.md / Kontrak API. */
class PolicyMatrixTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string, string, array{bool, bool, bool}}> [ability, subject, [admin, staff, employee]] */
    public static function matrix(): array
    {
        return [
            'list assets page' => ['viewAny', Asset::class, [true, true, false]],
            'create asset' => ['create', Asset::class, [true, true, false]],
            'update asset' => ['update', 'asset', [true, true, false]],
            'dispose asset' => ['dispose', 'asset', [true, true, false]],
            'move asset' => ['move', 'asset', [true, true, false]],
            'soft delete asset' => ['delete', 'asset', [true, false, false]],
            'list loans' => ['viewAny', Loan::class, [true, true, true]],
            'record loan' => ['create', Loan::class, [true, true, false]],
            'record return' => ['return', 'loan', [true, true, false]],
            'start maintenance' => ['create', Maintenance::class, [true, true, false]],
            'manage categories' => ['create', Category::class, [true, true, false]],
            'delete category' => ['delete', 'category', [true, true, false]],
            'manage locations' => ['update', 'location', [true, true, false]],
            'list users' => ['viewAny', User::class, [true, false, false]],
            'create user' => ['create', User::class, [true, false, false]],
            'toggle another user' => ['toggleActive', 'user', [true, false, false]],
        ];
    }

    /** @param array{bool, bool, bool} $expected */
    #[DataProvider('matrix')]
    public function test_role_matrix(string $ability, string $subject, array $expected): void
    {
        $actors = [
            'admin' => User::factory()->admin()->create(),
            'staff' => User::factory()->staff()->create(),
            'employee' => User::factory()->employee()->create(),
        ];

        $argument = match ($subject) {
            'asset' => Asset::factory()->create(),
            'loan' => Loan::factory()->create(),
            'category' => Category::factory()->create(),
            'location' => Location::factory()->create(),
            'user' => User::factory()->create(),
            default => $subject,
        };

        foreach (array_keys($actors) as $i => $role) {
            $this->assertSame(
                $expected[$i],
                Gate::forUser($actors[$role])->allows($ability, $argument),
                "{$role} → {$ability}",
            );
        }
    }

    public function test_admin_cannot_deactivate_themselves(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertFalse(Gate::forUser($admin)->allows('toggleActive', $admin));
    }
}
