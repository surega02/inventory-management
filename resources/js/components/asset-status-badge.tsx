import { Badge } from '@/components/ui/badge';
import type { AssetStatus } from '@/types';

const styles: Record<AssetStatus, string> = {
    available:
        'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
    borrowed:
        'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
    maintenance:
        'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
    broken: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
    disposed: 'bg-muted text-muted-foreground',
};

export function statusLabel(status: AssetStatus): string {
    return status.charAt(0).toUpperCase() + status.slice(1);
}

export default function AssetStatusBadge({ status }: { status: AssetStatus }) {
    return (
        <Badge
            variant="outline"
            className={`border-transparent ${styles[status]}`}
        >
            {statusLabel(status)}
        </Badge>
    );
}
