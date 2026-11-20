<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Store and update. The code prefixes asset codes, so it locks once one was issued (Category::isCodeLocked). */
class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('category');

        return $category instanceof Category
            ? $this->user()?->can('update', $category) ?? false
            : $this->user()?->can('create', Category::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $category = $this->route('category');

        return [
            // unique across soft-deleted rows too, matching the DB unique index
            'code' => $category instanceof Category && $category->isCodeLocked()
                ? ['required', Rule::in([$category->code])]
                : ['required', 'regex:/^[A-Z0-9]{2,10}$/', Rule::unique('categories', 'code')->ignore($category)],
            'name' => ['required', 'string', 'max:100'],
            'default_useful_life_years' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'code.in' => 'The code cannot change because assets already use it.',
            'code.regex' => 'Use 2–10 uppercase letters or digits, e.g. LAP.',
        ];
    }
}
