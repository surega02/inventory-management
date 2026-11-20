<?php

namespace App\Chat\Tools;

use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Contracts\JsonSchema\JsonSchema;

/** Same numbers as the dashboard. Employees: only their own active and overdue loan counts. */
class GetInventorySummary extends Tool
{
    public function __construct(User $actor, private DashboardService $dashboard)
    {
        parent::__construct($actor);
    }

    public function name(): string
    {
        return 'get_inventory_summary';
    }

    public function description(): string
    {
        return $this->actor->isEmployee()
            ? 'How many assets the user currently has on loan, and how many of those are overdue.'
            : 'Inventory counts: assets per status, per category and per location (disposed assets excluded from category/location), plus active and overdue loans.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    protected function run(array $arguments): array
    {
        return $this->dashboard->summary($this->actor);
    }
}
