<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Services\DashboardService;
use App\Services\LoanService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Same page for every role; DashboardService and LoanService decide what each role may count and list. */
class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard, LoanService $loans): Response
    {
        $user = $request->user();
        $manages = $user->canManageInventory();
        // managers: the 5 longest-overdue loans; employees: all their loans still out
        $list = $loans->active($user, overdueOnly: $manages, limit: $manages ? 5 : 20);

        return Inertia::render('dashboard', [
            'summary' => $dashboard->summary($user),
            'loans' => [
                'kind' => $manages ? 'overdue' : 'mine',
                'total' => $list['total'],
                'items' => $list['items']->map(fn (Loan $l) => [
                    ...LoanController::row($l),
                    'asset' => ['id' => $l->asset->id, 'code' => $l->asset->code, 'name' => $l->asset->name],
                ])->values(),
            ],
            'recent' => $dashboard->recentActivity($user),
        ]);
    }
}
