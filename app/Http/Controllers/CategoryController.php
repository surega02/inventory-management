<?php

namespace App\Http\Controllers;

use App\Exceptions\ConflictException;
use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Master data is not row-restricted (admin/staff see all), so no Service layer is needed here. */
class CategoryController extends Controller
{
    /** `?view=deleted` lists soft-deleted categories so they can be restored or purged. */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Category::class);

        $deleted = $request->query('view') === 'deleted';

        return Inertia::render('categories/index', [
            'view' => $deleted ? 'deleted' : 'active',
            'deleted_count' => Category::onlyTrashed()->count(),
            'categories' => Category::query()
                ->when($deleted, fn ($q) => $q->onlyTrashed()->withCount(['assets' => fn ($a) => $a->withTrashed()]))
                ->unless($deleted, fn ($q) => $q->withCount('assets'))
                ->orderBy('code')->paginate(20)->withQueryString()
                ->through(fn (Category $c) => [
                    'id' => $c->id,
                    'code' => $c->code,
                    'name' => $c->name,
                    'default_useful_life_years' => $c->default_useful_life_years,
                    'assets_count' => $c->assets_count,
                    'code_locked' => $c->isCodeLocked(),
                    'deleted_at' => $c->deleted_at?->toIso8601String(),
                    'purge_blocker' => $deleted ? $c->purgeBlocker() : null,
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

    public function restore(Category $category): RedirectResponse
    {
        Gate::authorize('restore', $category);
        abort_unless($category->trashed(), 404);

        $category->restore();

        return $this->done("Category {$category->code} restored.");
    }

    /** Permanent delete, only when nothing references it any more (Category::purgeBlocker). */
    public function forceDelete(Category $category): RedirectResponse
    {
        Gate::authorize('forceDelete', $category);
        abort_unless($category->trashed(), 404);

        $blocker = $category->purgeBlocker();
        throw_if($blocker !== null, ConflictException::class, "Category {$category->code}: {$blocker}");

        $category->forceDelete();

        return $this->done("Category {$category->code} permanently deleted.", deleted: true);
    }

    private function done(string $message, bool $deleted = false): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('categories.index', $deleted ? ['view' => 'deleted'] : []);
    }
}
