<?php

namespace App\Http\Requests;

use App\Models\Location;
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
        return [
            // unique across soft-deleted rows too, matching the DB unique index
            'code' => ['required', 'regex:/^[A-Z0-9-]{2,20}$/', Rule::unique('locations', 'code')->ignore($this->route('location'))],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['code.regex' => 'Use 2–20 uppercase letters, digits or dashes, e.g. HO-L2-IT.'];
    }
}
