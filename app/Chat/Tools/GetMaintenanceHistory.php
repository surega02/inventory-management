<?php

namespace App\Chat\Tools;

use App\Enums\MaintenanceStatus;
use App\Models\Maintenance;
use App\Models\User;
use App\Services\MaintenanceService;
use Illuminate\Contracts\JsonSchema\JsonSchema;

/** Admin and staff only: ToolRegistry never offers it to employees, and MaintenanceService returns nothing for them. */
class GetMaintenanceHistory extends Tool
{
    public function __construct(User $actor, private MaintenanceService $maintenances)
    {
        parent::__construct($actor);
    }

    public function name(): string
    {
        return 'get_maintenance_history';
    }

    public function description(): string
    {
        return 'Maintenance jobs, newest first, optionally for one asset code and/or status. Cost is in Rupiah. Returns total and up to 20 rows.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'asset_code' => $schema->string()->description('Asset code, e.g. PRJ-0002'),
            'status' => $schema->string()->enum(array_column(MaintenanceStatus::cases(), 'value')),
            'limit' => $schema->integer()->min(1)->max(self::MAX_ROWS)->description('Rows to return, default '.self::DEFAULT_ROWS),
        ];
    }

    protected function run(array $arguments): array
    {
        $result = $this->maintenances->history($this->actor, [
            'asset_code' => $this->string($arguments, 'asset_code'),
            'status' => $this->oneOf($arguments, 'status', array_column(MaintenanceStatus::cases(), 'value')),
        ], $this->limit($arguments));

        return $this->rows($result['total'], $result['items'], fn (Maintenance $m) => [
            'asset_code' => $m->asset->code,
            'asset_name' => $m->asset->name,
            'description' => self::text($m->description),
            'vendor' => $m->vendor,
            'started_at' => $m->started_at->toDateString(),
            'completed_at' => $m->completed_at?->toDateString(),
            'cost' => $m->cost,
            'status' => $m->status->value,
        ]);
    }
}
