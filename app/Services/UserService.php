<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** User writes. Role lives in spatie's pivot (no model event), so role changes are audited here. */
class UserService
{
    /** @param array{name: string, email: string, password: string|null} $data password is required by the request on create */
    public function create(array $data, Role $role): User
    {
        return DB::transaction(function () use ($data, $role) {
            $user = User::create($data);
            $user->assignRole($role);
            $this->logRole($user, null, $role);

            return $user;
        });
    }

    /** @param array{name: string, email: string, password: string|null} $data null password = keep the current one */
    public function update(User $user, array $data, Role $role): User
    {
        return DB::transaction(function () use ($user, $data, $role) {
            $user->update(array_filter($data, fn ($v) => $v !== null));

            $old = $user->getRoleNames()->first();
            if ($old !== $role->value) {
                $user->syncRoles([$role]);
                $this->logRole($user, $old, $role);
            }

            return $user;
        });
    }

    public function toggleActive(User $user): User
    {
        $user->update(['is_active' => ! $user->is_active]);

        return $user;
    }

    private function logRole(User $user, ?string $old, Role $new): void
    {
        activity()->performedOn($user)->event('role_changed')
            ->withProperties(['old' => $old, 'new' => $new->value])
            ->log($old ? "Role changed from {$old} to {$new->value}" : "Role set to {$new->value}");
    }
}
