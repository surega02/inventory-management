import { Badge } from '@/components/ui/badge';
import type { LoanRow } from '@/types';

const styles = {
    Active: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
    Overdue: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
    Returned: 'bg-muted text-muted-foreground',
};

/** Overdue is computed by the server from due_date (never stored). */
export default function LoanStatusBadge({ loan }: { loan: LoanRow }) {
    const label = loan.returned_at
        ? 'Returned'
        : loan.is_overdue
          ? 'Overdue'
          : 'Active';

    return (
        <Badge
            variant="outline"
            className={`border-transparent ${styles[label]}`}
        >
            {label}
        </Badge>
    );
}

export function formatDate(value: string | null): string {
    return value
        ? new Date(value).toLocaleDateString('en-GB', {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
          })
        : '—';
}
