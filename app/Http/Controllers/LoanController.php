<?php

namespace App\Http\Controllers;

use App\Enums\AssetCondition;
use App\Models\Asset;
use App\Models\Loan;
use App\Models\User;
use App\Services\AssetService;
use App\Services\LoanService;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** All loan reads go through LoanService (visibleTo); writes are lend/return only (loans are append-only). */
class LoanController extends Controller
{
    public function __construct(private LoanService $loans) {}

    public function index(Request $request, AssetService $assets, UserService $users): Response
    {
        Gate::authorize('viewAny', Loan::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(LoanService::STATUSES)],
        ]);
        $user = $request->user();
        $canLend = $user->can('create', Loan::class);

        return Inertia::render('loans/index', [
            'filters' => $filters,
            'loans' => $this->loans->list($user, $filters)->through(fn (Loan $l) => [
                ...$this->row($l),
                'asset' => ['id' => $l->asset->id, 'code' => $l->asset->code, 'name' => $l->asset->name],
                'borrower' => $l->borrower->name,
            ]),
            'conditions' => array_column(AssetCondition::cases(), 'value'),
            'abilities' => ['lend' => $canLend, 'return' => $canLend],
            // lend form comboboxes: only sent on partial reloads that ask for them (search runs server-side)
            'assetOptions' => Inertia::optional(fn () => $canLend
                ? $assets->lendable($user, $request->string('asset_q')->limit(100, '')->value() ?: null)
                    ->map(fn (Asset $a) => ['id' => $a->id, 'label' => "{$a->code} · {$a->name}"])
                : []),
            'borrowerOptions' => Inertia::optional(fn () => $canLend
                ? $users->borrowerOptions($request->string('borrower_q')->limit(100, '')->value() ?: null)
                    ->map(fn (User $u) => ['id' => $u->id, 'label' => "{$u->name} ({$u->email})"])
                : []),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Loan::class);

        $data = $request->validate([
            'asset_id' => ['required', 'integer', Rule::exists('assets', 'id')->whereNull('deleted_at')],
            'borrower_id' => ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'due_date' => ['nullable', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $loan = $this->loans->lend($request->user(), $data['asset_id'], $data['borrower_id'], $data['due_date'] ?? null, $data['notes'] ?? null);

        return $this->done("{$loan->asset->code} lent to {$loan->borrower->name}.");
    }

    public function return(Request $request, Loan $loan): RedirectResponse
    {
        Gate::authorize('return', $loan);

        $data = $request->validate([
            'return_condition' => ['required', Rule::enum(AssetCondition::class)],
            'mark_broken' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->loans->return($request->user(), $loan, AssetCondition::from($data['return_condition']), $request->boolean('mark_broken'), $data['notes'] ?? null);

        return $this->done("{$loan->asset->code} returned.");
    }

    /** @return array<string, mixed> fields shared by the loans list and the asset detail history */
    public static function row(Loan $loan): array
    {
        return [
            'id' => $loan->id,
            'borrower' => $loan->borrower->name,
            'loaned_at' => $loan->loaned_at->toIso8601String(),
            'due_date' => $loan->due_date?->toDateString(),
            'returned_at' => $loan->returned_at?->toIso8601String(),
            'return_condition' => $loan->return_condition,
            'is_overdue' => $loan->isOverdue(),
            'notes' => $loan->notes,
        ];
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
