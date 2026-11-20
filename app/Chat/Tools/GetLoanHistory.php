<?php

namespace App\Chat\Tools;

use App\Models\Loan;
use App\Models\User;
use App\Services\LoanService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;

/** Employees: only their own history; the borrower argument is ignored for them (and not offered). */
class GetLoanHistory extends Tool
{
    public function __construct(User $actor, private LoanService $loans)
    {
        parent::__construct($actor);
    }

    public function name(): string
    {
        return 'get_loan_history';
    }

    public function description(): string
    {
        return 'Loan history, newest first, including returned loans. Optionally for one asset code and/or a loan date range. Returns total and up to 20 rows.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'asset_code' => $schema->string()->description('Asset code, e.g. LAP-0003'),
            ...$this->actor->isEmployee() ? [] : [
                'borrower' => $schema->string()->description('Borrower name (partial) or exact email'),
            ],
            'date_from' => $schema->string()->format('date')->description('Loaned on or after, YYYY-MM-DD'),
            'date_to' => $schema->string()->format('date')->description('Loaned on or before, YYYY-MM-DD'),
            'limit' => $schema->integer()->min(1)->max(self::MAX_ROWS)->description('Rows to return, default '.self::DEFAULT_ROWS),
        ];
    }

    protected function run(array $arguments): array
    {
        $from = $this->date($arguments, 'date_from');
        $to = $this->date($arguments, 'date_to');

        if ($from !== null && $to !== null && $from > $to) {
            throw new InvalidArgumentException('date_from must not be after date_to.');
        }

        $result = $this->loans->history($this->actor, [
            'asset_code' => $this->string($arguments, 'asset_code'),
            // LoanService ignores it for employees too; dropping it here keeps the intent obvious
            'borrower' => $this->actor->isEmployee() ? null : $this->string($arguments, 'borrower'),
            'date_from' => $from,
            'date_to' => $to,
        ], $this->limit($arguments));

        return $this->rows($result['total'], $result['items'], fn (Loan $l) => [
            'asset_code' => $l->asset->code,
            'asset_name' => $l->asset->name,
            'borrower_name' => $l->borrower->name,
            'loaned_at' => $l->loaned_at->toDateString(),
            'due_date' => $l->due_date?->toDateString(),
            'returned_at' => $l->returned_at?->toDateString(),
            'return_condition' => $l->return_condition?->value,
        ]);
    }
}
