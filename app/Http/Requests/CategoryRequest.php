<?php

namespace App\Http\Requests;

use App\Models\Category;
use Closure;
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
        $category = $category instanceof Category ? $category : null;

        return [
            // unique across soft-deleted rows too, matching the DB unique index; a deleted
            // owner gets its own message because the fix is to restore it, not to retry
            'code' => $category && $category->isCodeLocked()
                ? ['required', Rule::in([$category->code])]
                : ['bail', 'required', 'regex:/^[A-Z0-9]{2,10}$/', $this->notTakenByDeleted($category), Rule::unique('categories', 'code')->ignore($category)],
            'name' => ['required', 'string', 'max:100'],
            'default_useful_life_years' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    private function notTakenByDeleted(?Category $category): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($category): void {
            $taken = Category::onlyTrashed()->where('code', $value)
                ->when($category, fn ($q) => $q->whereKeyNot($category->id))->exists();

            if ($taken) {
                $fail('A deleted category uses this code. Restore it from the Deleted tab instead.');
            }
        };
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
