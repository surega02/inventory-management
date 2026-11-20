<?php

namespace App\Http\Controllers;

use App\Exceptions\ConflictException;
use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Master data is not row-restricted (admin/staff see all), so no Service layer is needed here. */
class CategoryController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Category::class);

        return Inertia::render('categories/index', [
            'categories' => Category::withCount('assets')->orderBy('code')->paginate(20)
                ->through(fn (Category $c) => [
                    'id' => $c->id,
                    'code' => $c->code,
                    'name' => $c->name,
                    'default_useful_life_years' => $c->default_useful_life_years,
                    'assets_count' => $c->assets_count,
                    'code_locked' => $c->isCodeLocked(),
                ]),
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        Category::create($request->validated());

        return $this->done('Category created.');
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        return $this->done('Category updated.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        Gate::authorize('delete', $category);

        throw_if($category->assets()->exists(), ConflictException::class,
            "Category {$category->code} is still used by {$category->assets()->count()} asset(s).");

        $category->delete();

        return $this->done('Category deleted.');
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('categories.index');
    }
}
