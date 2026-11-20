<?php

namespace App\Chat\Tools;

use App\Chat\Tools\Concerns\FiltersByMasterData;
use App\Models\Loan;
use App\Models\User;
use App\Services\LoanService;
use App\Services\MasterDataService;
use Illuminate\Contracts\JsonSchema\JsonSchema;

/** Employees: only their own loans (LoanService → visibleTo). */
class ListActiveLoans extends Tool
{
    use FiltersByMasterData;

    public function __construct(User $actor, private LoanService $loans, protected MasterDataService $master)
    {
        parent::__construct($actor);
    }

    public function name(): string
    {
        return 'list_active_loans';
    }

    public function description(): string
    {
        return 'List loans not yet returned, sorted by due date (longest overdue first). Optionally only overdue ones and/or one asset category. Returns total and up to 20 rows.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'overdue_only' => $schema->boolean()->description('Only loans past their due date'),
            'category' => $schema->string()->description($this->categoryHint()),
            'limit' => $schema->integer()->min(1)->max(self::MAX_ROWS)->description('Rows to return, default '.self::DEFAULT_ROWS),
        ];
    }

    protected function run(array $arguments): array
    {
        $result = $this->loans->active(
            $this->actor,
            overdueOnly: $this->bool($arguments, 'overdue_only'),
            categoryIds: $this->categoryFilter($this->string($arguments, 'category')),
            limit: $this->limit($arguments),
        );

        return $this->rows($result['total'], $result['items'], fn (Loan $l) => [
            'asset_code' => $l->asset->code,
            'asset_name' => $l->asset->name,
            'borrower_name' => $l->borrower->name,
            'loaned_at' => $l->loaned_at->toDateString(),
            'due_date' => $l->due_date?->toDateString(),
            'is_overdue' => $l->isOverdue(),
        ]);
    }
}
