<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Services\MovementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class MovementController extends Controller
{
    public function store(Request $request, Asset $asset, MovementService $movements): RedirectResponse
    {
        Gate::authorize('move', $asset);

        $data = $request->validate([
            'to_location_id' => ['required', 'integer', Rule::exists('locations', 'id')->whereNull('deleted_at')],
            'moved_at' => ['nullable', 'date', 'before_or_equal:now'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $movement = $movements->move($request->user(), $asset, $data['to_location_id'], $data['moved_at'] ?? null, $data['reason'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$asset->code} moved to {$movement->toLocation->name}."]);

        return back();
    }
}
