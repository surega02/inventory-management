<?php

namespace App\Http\Controllers;

use App\Exceptions\ConflictException;
use App\Http\Requests\LocationRequest;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Master data is not row-restricted (admin/staff see all), so no Service layer is needed here. */
class LocationController extends Controller
{
    /** `?view=deleted` lists soft-deleted locations so they can be restored or purged. */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Location::class);

        $deleted = $request->query('view') === 'deleted';

        return Inertia::render('locations/index', [
            'view' => $deleted ? 'deleted' : 'active',
            'deleted_count' => Location::onlyTrashed()->count(),
            'locations' => Location::query()
                ->when($deleted, fn ($q) => $q->onlyTrashed()->withCount(['assets' => fn ($a) => $a->withTrashed()]))
                ->unless($deleted, fn ($q) => $q->withCount('assets'))
                ->orderBy('code')->paginate(20)->withQueryString()
                ->through(fn (Location $l) => [
                    'id' => $l->id,
                    'code' => $l->code,
                    'name' => $l->name,
                    'description' => $l->description,
                    'assets_count' => $l->assets_count,
                    'deleted_at' => $l->deleted_at?->toIso8601String(),
                    'purge_blocker' => $deleted ? $l->purgeBlocker() : null,
                ]),
        ]);
    }

    public function store(LocationRequest $request): RedirectResponse
    {
        Location::create($request->validated());

        return $this->done('Location created.');
    }

    public function update(LocationRequest $request, Location $location): RedirectResponse
    {
        $location->update($request->validated());

        return $this->done('Location updated.');
    }

    public function destroy(Location $location): RedirectResponse
    {
        Gate::authorize('delete', $location);

        throw_if($location->assets()->exists(), ConflictException::class,
            "Location {$location->code} still holds {$location->assets()->count()} asset(s).");

        $location->delete();

        return $this->done('Location deleted.');
    }

    public function restore(Location $location): RedirectResponse
    {
        Gate::authorize('restore', $location);
        abort_unless($location->trashed(), 404);

        $location->restore();

        return $this->done("Location {$location->code} restored.");
    }

    /** Permanent delete, only when nothing references it any more (Location::purgeBlocker). */
    public function forceDelete(Location $location): RedirectResponse
    {
        Gate::authorize('forceDelete', $location);
        abort_unless($location->trashed(), 404);

        $blocker = $location->purgeBlocker();
        throw_if($blocker !== null, ConflictException::class, "Location {$location->code}: {$blocker}");

        $location->forceDelete();

        return $this->done("Location {$location->code} permanently deleted.", deleted: true);
    }

    private function done(string $message, bool $deleted = false): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('locations.index', $deleted ? ['view' => 'deleted'] : []);
    }
}
