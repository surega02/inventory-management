<?php

namespace App\Chat\Tools;

use App\Models\User;
use Illuminate\Contracts\Container\Container;

/**
 * The tools offered to the LLM for one user, bound to that user from the session.
 * Employees never get get_maintenance_history; the services still enforce scope
 * as the second layer.
 */
class ToolRegistry
{
    /** @var list<class-string<Tool>> */
    private const TOOLS = [
        SearchAssets::class,
        GetAssetDetail::class,
        ListActiveLoans::class,
        GetLoanHistory::class,
        GetMaintenanceHistory::class,
        GetInventorySummary::class,
    ];

    /** @var list<class-string<Tool>> */
    private const MANAGERS_ONLY = [GetMaintenanceHistory::class];

    public function __construct(private Container $container) {}

    /** @return list<ChatTool> */
    public function for(User $actor): array
    {
        $classes = array_filter(self::TOOLS, fn (string $class) => $actor->canManageInventory() || ! in_array($class, self::MANAGERS_ONLY, true));

        return array_values(array_map(fn (string $class) => $this->container->make($class, ['actor' => $actor]), $classes));
    }
}
