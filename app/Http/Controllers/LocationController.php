<?php

namespace App\Http\Controllers;

use App\Exceptions\ConflictException;
use App\Http\Requests\LocationRequest;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Master data is not row-restricted (admin/staff see all), so no Service layer is needed here. */
class LocationController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Location::class);

        return Inertia::render('locations/index', [
            'locations' => Location::withCount('assets')->orderBy('code')->paginate(20)
                ->through(fn (Location $l) => [
                    'id' => $l->id,
                    'code' => $l->code,
                    'name' => $l->name,
                    'description' => $l->description,
                    'assets_count' => $l->assets_count,
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

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('locations.index');
    }
}
