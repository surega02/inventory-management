<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

/** Store and update (password optional on update). */
class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->route('user');

        return $user instanceof User
            ? $this->user()?->can('update', $user) ?? false
            : $this->user()?->can('create', User::class) ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            // unique across soft-deleted users too, matching the DB unique index
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'password' => [$user instanceof User ? 'nullable' : 'required', 'string', Password::defaults()],
            'role' => ['required', Rule::enum(Role::class)],
        ];
    }

    /**
     * An admin cannot change their own role: the last admin could lock everyone out.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            $user = $this->route('user');

            if ($user instanceof User && $user->is($this->user()) && $this->input('role') !== $user->getRoleNames()->first()) {
                $validator->errors()->add('role', 'You cannot change your own role.');
            }
        }];
    }

    /** @return array{name: string, email: string, password: string|null} */
    public function account(): array
    {
        return [
            'name' => $this->string('name')->value(),
            'email' => $this->string('email')->value(),
            'password' => $this->filled('password') ? $this->string('password')->value() : null,
        ];
    }

    public function role(): Role
    {
        return Role::from($this->string('role')->value());
    }
}
