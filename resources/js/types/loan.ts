import type { AssetCondition } from './asset';

/** LoanController::row(): one loan, as shown in the loans list and the asset history. */
export type LoanRow = {
    id: number;
    borrower: string;
    loaned_at: string;
    due_date: string | null;
    returned_at: string | null;
    return_condition: AssetCondition | null;
    is_overdue: boolean;
    notes: string | null;
};

/** Combobox entry built server-side for the lend form. */
export type PickOption = { id: number; label: string };
