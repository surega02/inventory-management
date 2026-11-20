<?php

namespace App\Http\Controllers;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Http\Requests\AssetRequest;
use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\Category;
use App\Models\Loan;
use App\Models\Location;
use App\Models\Maintenance;
use App\Services\AssetService;
use App\Services\LoanService;
use App\Services\MaintenanceService;
use App\Services\MovementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** All reads go through AssetService (visibleTo); this controller never queries assets itself. */
class AssetController extends Controller
{
    public function __construct(private AssetService $assets) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Asset::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer'],
            'location_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(AssetStatus::class)],
            'sort' => ['nullable', Rule::in([...AssetService::SORTS, ...array_map(fn ($s) => "-{$s}", AssetService::SORTS)])],
        ]);

        return Inertia::render('assets/index', [
            'filters' => $filters,
            'assets' => $this->assets->list($request->user(), $filters)->through(fn (Asset $a) => [
                'id' => $a->id,
                'code' => $a->code,
                'name' => $a->name,
                'category' => $a->category->name,
                'location' => $a->location->name,
                'status' => $a->status,
            ]),
            ...$this->options(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Asset::class);

        return Inertia::render('assets/form', ['asset' => null, ...$this->options()]);
    }

    public function store(AssetRequest $request): RedirectResponse
    {
        $asset = $this->assets->create(
            $request->user(),
            $request->integer('category_id'),
            $request->integer('location_id'),
            $request->assetAttributes(),
        );

        return $this->done("Asset {$asset->code} created.", $asset);
    }

    public function show(Request $request, int $asset, LoanService $loans, MovementService $movements, MaintenanceService $maintenances): Response
    {
        $asset = $this->assets->findVisible($request->user(), $asset); // 404 outside the actor's scope
        $user = $request->user();
        $manages = $user->canManageInventory();

        return Inertia::render('assets/show', [
            'asset' => [
                ...$this->detail($asset),
                // employees never see prices (same rule as the chatbot tools)
                'purchase_price' => $manages ? $asset->purchase_price : null,
            ],
            // employees only get their own loans of this asset (LoanService uses visibleTo)
            'loans' => $loans->forAsset($user, $asset)->map(fn (Loan $l) => LoanController::row($l)),
            // movement and maintenance history are operational data: managers only (employees get empty lists)
            'movements' => $manages ? $movements->forAsset($asset)->map(fn (AssetMovement $m) => [
                'id' => $m->id,
                'from' => $m->fromLocation->name,
                'to' => $m->toLocation->name,
                'moved_at' => $m->moved_at->toIso8601String(),
                'reason' => $m->reason,
                'recorded_by' => $m->recorder->name,
            ]) : [],
            'maintenances' => $manages ? $maintenances->forAsset($asset)->map(fn (Maintenance $m) => [
                'id' => $m->id,
                'description' => $m->description,
                'vendor' => $m->vendor,
                'started_at' => $m->started_at->toDateString(),
                'completed_at' => $m->completed_at?->toDateString(),
                'cost' => $m->cost,
                'status' => $m->status,
                'recorded_by' => $m->recorder->name,
            ]) : [],
            'conditions' => array_column(AssetCondition::cases(), 'value'),
            'locations' => $manages ? Location::orderBy('name')->get(['id', 'code', 'name']) : [],
            'abilities' => [
                'update' => $user->can('update', $asset),
                'dispose' => $user->can('dispose', $asset),
                'delete' => $user->can('delete', $asset) && ! $this->assets->hasHistory($asset),
                'return' => $user->can('create', Loan::class),
                'move' => $user->can('move', $asset),
                'maintain' => $user->can('create', Maintenance::class),
            ],
        ]);
    }

    public function edit(Asset $asset): Response
    {
        Gate::authorize('update', $asset);

        return Inertia::render('assets/form', [
            'asset' => [...$this->detail($asset->load(['category', 'location'])), 'purchase_price' => $asset->purchase_price],
            ...$this->options(),
        ]);
    }

    public function update(AssetRequest $request, Asset $asset): RedirectResponse
    {
        $this->assets->update($asset, $request->assetAttributes());

        return $this->done("Asset {$asset->code} updated.", $asset);
    }

    public function dispose(Request $request, Asset $asset): RedirectResponse
    {
        Gate::authorize('dispose', $asset);
        $reason = $request->validate(['reason' => ['required', 'string', 'max:500']])['reason'];

        $this->assets->dispose($asset, $reason);

        return $this->done("Asset {$asset->code} disposed.", $asset);
    }

    public function destroy(Asset $asset): RedirectResponse
    {
        Gate::authorize('delete', $asset);

        $this->assets->delete($asset);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Asset {$asset->code} deleted."]);

        return to_route('assets.index');
    }

    /** @return array<string, mixed> */
    private function detail(Asset $asset): array
    {
        return [
            'id' => $asset->id,
            'code' => $asset->code,
            'name' => $asset->name,
            'category' => $asset->category->name,
            'location' => $asset->location->name,
            'status' => $asset->status,
            'condition' => $asset->condition,
            'brand' => $asset->brand,
            'serial_number' => $asset->serial_number,
            'purchase_date' => $asset->purchase_date?->toDateString(),
            'useful_life_years' => $asset->useful_life_years,
            'notes' => $asset->notes,
        ];
    }

    /** @return array<string, mixed> select options shared by the list filters and the form */
    private function options(): array
    {
        return [
            'categories' => Category::orderBy('name')->get(['id', 'code', 'name', 'default_useful_life_years']),
            'locations' => Location::orderBy('name')->get(['id', 'code', 'name']),
            'statuses' => array_column(AssetStatus::cases(), 'value'),
            'conditions' => array_column(AssetCondition::cases(), 'value'),
        ];
    }

    private function done(string $message, Asset $asset): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('assets.show', $asset);
    }
}
