<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Maintenance;
use App\Services\MaintenanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/** Maintenance rows are append-only: started here, later completed, never edited or deleted. */
class MaintenanceController extends Controller
{
    private const COST = ['nullable', 'numeric', 'min:0', 'max:999999999999.99', 'decimal:0,2'];

    public function __construct(private MaintenanceService $maintenances) {}

    public function store(Request $request, Asset $asset): RedirectResponse
    {
        Gate::authorize('create', Maintenance::class);

        $data = $request->validate([
            'description' => ['required', 'string', 'max:1000'],
            'started_at' => ['required', 'date', 'before_or_equal:today'],
            'vendor' => ['nullable', 'string', 'max:150'],
            'cost' => self::COST,
        ]);

        $this->maintenances->start($request->user(), $asset, $data['description'], $data['started_at'], $data['vendor'] ?? null, $this->cost($data));

        return $this->done("Maintenance started for {$asset->code}.");
    }

    public function complete(Request $request, Maintenance $maintenance): RedirectResponse
    {
        Gate::authorize('complete', $maintenance);

        $data = $request->validate([
            'completed_at' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.$maintenance->started_at->toDateString()],
            'cost' => self::COST,
        ]);

        $this->maintenances->complete($maintenance, $data['completed_at'], $this->cost($data));

        return $this->done("Maintenance of {$maintenance->asset->code} completed.");
    }

    /** @param array<string, mixed> $data */
    private function cost(array $data): ?string
    {
        return isset($data['cost']) ? (string) $data['cost'] : null;
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
