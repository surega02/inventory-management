<?php

namespace App\Http\Requests;

use App\Models\Location;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $location = $this->route('location');

        return $location instanceof Location
            ? $this->user()?->can('update', $location) ?? false
            : $this->user()?->can('create', Location::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $location = $this->route('location');
        $location = $location instanceof Location ? $location : null;

        return [
            // unique across soft-deleted rows too, matching the DB unique index; a deleted
            // owner gets its own message because the fix is to restore it, not to retry
            'code' => ['bail', 'required', 'regex:/^[A-Z0-9-]{2,20}$/', $this->notTakenByDeleted($location), Rule::unique('locations', 'code')->ignore($location)],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    private function notTakenByDeleted(?Location $location): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($location): void {
            $taken = Location::onlyTrashed()->where('code', $value)
                ->when($location, fn ($q) => $q->whereKeyNot($location->id))->exists();

            if ($taken) {
                $fail('A deleted location uses this code. Restore it from the Deleted tab instead.');
            }
        };
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['code.regex' => 'Use 2–20 uppercase letters, digits or dashes, e.g. HO-L2-IT.'];
    }
}
