<?php

namespace App\Chat\Tools;

use App\Models\AssetMovement;
use App\Models\Loan;
use App\Models\Maintenance;
use App\Models\User;
use App\Services\AssetService;
use App\Services\LoanService;
use App\Services\MaintenanceService;
use App\Services\MovementService;
use Illuminate\Contracts\JsonSchema\JsonSchema;

/**
 * Employees: only an asset they currently borrow; anything else is the same
 * not_found as a code that does not exist. Like the asset page, employees get
 * no movement/maintenance history and nobody gets the purchase price here.
 */
class GetAssetDetail extends Tool
{
    public function __construct(
        User $actor,
        private AssetService $assets,
        private LoanService $loans,
        private MovementService $movements,
        private MaintenanceService $maintenances,
    ) {
        parent::__construct($actor);
    }

    public function name(): string
    {
        return 'get_asset_detail';
    }

    public function description(): string
    {
        return 'Get one asset by its code (e.g. LAP-0012): name, category, location, status, condition, current loan, and for staff the last 3 movements and maintenance jobs.';
    }

    public function schema(JsonSchema $schema): array
    {
        return ['code' => $schema->string()->description('Asset code, e.g. LAP-0012')->required()];
    }

    protected function run(array $arguments): array
    {
        $code = (string) $this->string($arguments, 'code', required: true);
        $asset = $this->assets->findVisibleByCode($this->actor, $code);

        if ($asset === null) {
            return ['error' => 'not_found', 'detail' => "No asset with code {$code} was found."];
        }

        $loan = $this->loans->forAsset($this->actor, $asset)->first(fn (Loan $l) => $l->returned_at === null);
        $manages = $this->actor->canManageInventory();

        return [
            'code' => $asset->code,
            'name' => $asset->name,
            'category' => $asset->category->name,
            'location' => $asset->location->name,
            'status' => $asset->status->value,
            'condition' => $asset->condition->value,
            'active_loan' => $loan ? [
                'borrower_name' => $loan->borrower->name,
                'loaned_at' => $loan->loaned_at->toDateString(),
                'due_date' => $loan->due_date?->toDateString(),
                'is_overdue' => $loan->isOverdue(),
            ] : null,
            ...$manages ? [
                'recent_movements' => $this->movements->forAsset($asset)->take(3)->map(fn (AssetMovement $m) => [
                    'from' => $m->fromLocation->name,
                    'to' => $m->toLocation->name,
                    'moved_at' => $m->moved_at->toDateString(),
                    'reason' => self::text($m->reason),
                ])->values()->all(),
                'recent_maintenances' => $this->maintenances->forAsset($asset)->take(3)->map(fn (Maintenance $m) => [
                    'description' => self::text($m->description),
                    'started_at' => $m->started_at->toDateString(),
                    'completed_at' => $m->completed_at?->toDateString(),
                    'status' => $m->status->value,
                ])->values()->all(),
            ] : [],
        ];
    }
}
