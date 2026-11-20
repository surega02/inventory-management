<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Requests\UserRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(private UserService $users) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $q = trim($request->string('q')->value());

        return Inertia::render('users/index', [
            'filters' => ['q' => $q],
            'roles' => array_column(Role::cases(), 'value'),
            'users' => User::with('roles')
                ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                    ->whereLike('name', "%{$q}%")->orWhereLike('email', "%{$q}%")))
                ->orderBy('name')->paginate(20)->withQueryString()
                ->through(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'role' => $u->getRoleNames()->first(),
                    'is_active' => $u->is_active,
                    'last_login_at' => $u->last_login_at?->toIso8601String(),
                    'is_self' => $u->is($request->user()),
                ]),
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $this->users->create($request->account(), $request->role());

        return $this->done('User created.');
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $this->users->update($user, $request->account(), $request->role());

        return $this->done('User updated.');
    }

    public function toggleActive(User $user): RedirectResponse
    {
        Gate::authorize('toggleActive', $user);

        $this->users->toggleActive($user);

        return $this->done($user->is_active ? "{$user->name} activated." : "{$user->name} deactivated.");
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
