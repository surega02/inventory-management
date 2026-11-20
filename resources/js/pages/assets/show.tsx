import { Form, Head, Link, router } from '@inertiajs/react';
import { Archive, ArrowRightLeft, Pencil, Trash2, Wrench } from 'lucide-react';
import { useState } from 'react';
import AssetController from '@/actions/App/Http/Controllers/AssetController';
import {
    CompleteMaintenanceDialog,
    MoveAssetDialog,
    StartMaintenanceDialog,
} from '@/components/asset-action-dialogs';
import AssetStatusBadge from '@/components/asset-status-badge';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import LoanStatusBadge, { formatDate } from '@/components/loan-status-badge';
import ReturnLoanDialog from '@/components/return-loan-dialog';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { edit, index } from '@/routes/assets';
import type {
    AssetCondition,
    AssetDetail,
    LoanRow,
    MaintenanceRow,
    MovementRow,
    Option,
} from '@/types';

type Props = {
    asset: AssetDetail;
    loans: LoanRow[];
    movements: MovementRow[];
    maintenances: MaintenanceRow[];
    conditions: AssetCondition[];
    locations: Option[];
    abilities: {
        update: boolean;
        dispose: boolean;
        delete: boolean;
        return: boolean;
        move: boolean;
        maintain: boolean;
    };
};

const rupiah = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

export default function AssetShow({
    asset,
    loans,
    movements,
    maintenances,
    conditions,
    locations,
    abilities,
}: Props) {
    const [disposing, setDisposing] = useState(false);
    const [returning, setReturning] = useState<LoanRow | null>(null);
    const [moving, setMoving] = useState(false);
    const [starting, setStarting] = useState(false);
    const [completing, setCompleting] = useState<MaintenanceRow | null>(null);
    // mirror the services; the server still answers a conflict if the page is stale
    const movable = asset.status !== 'borrowed' && asset.status !== 'disposed';
    const maintainable =
        asset.status === 'available' || asset.status === 'broken';
    // mirrors AssetService::dispose: not when already disposed, on loan or under maintenance
    const disposable =
        asset.status === 'available' || asset.status === 'broken';

    const details: [string, string | null][] = [
        ['Category', asset.category],
        ['Location', asset.location],
        [
            'Condition',
            asset.condition.charAt(0).toUpperCase() + asset.condition.slice(1),
        ],
        ['Brand', asset.brand],
        ['Serial number', asset.serial_number],
        ['Purchase date', asset.purchase_date],
        // hidden (null) for employees by the server
        [
            'Purchase price',
            asset.purchase_price === null
                ? null
                : rupiah.format(Number(asset.purchase_price)),
        ],
        [
            'Useful life',
            asset.useful_life_years ? `${asset.useful_life_years} years` : null,
        ],
    ];

    return (
        <>
            <Head title={asset.code} />

            <div className="flex max-w-3xl flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <Heading title={`${asset.code} · ${asset.name}`} />
                        <div className="-mt-6">
                            <AssetStatusBadge status={asset.status} />
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {abilities.move && (
                            <Button
                                variant="outline"
                                disabled={!movable}
                                title={
                                    movable
                                        ? undefined
                                        : 'Borrowed or disposed assets cannot be moved'
                                }
                                onClick={() => setMoving(true)}
                            >
                                <ArrowRightLeft /> Move
                            </Button>
                        )}
                        {abilities.maintain && (
                            <Button
                                variant="outline"
                                disabled={!maintainable}
                                title={
                                    maintainable
                                        ? undefined
                                        : 'Only available or broken assets can go into maintenance'
                                }
                                onClick={() => setStarting(true)}
                            >
                                <Wrench /> Maintenance
                            </Button>
                        )}
                        {abilities.update && (
                            <Button variant="outline" asChild>
                                <Link href={edit(asset.id)}>
                                    <Pencil /> Edit
                                </Link>
                            </Button>
                        )}
                        {abilities.dispose && (
                            <Button
                                variant="outline"
                                disabled={!disposable}
                                title={
                                    disposable
                                        ? undefined
                                        : 'Only available or broken assets can be disposed'
                                }
                                onClick={() => setDisposing(true)}
                            >
                                <Archive /> Dispose
                            </Button>
                        )}
                        {abilities.delete && (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    confirm(
                                        `Delete ${asset.code}? Only for assets created by mistake.`,
                                    ) &&
                                    router.delete(
                                        AssetController.destroy.url(asset.id),
                                    )
                                }
                            >
                                <Trash2 /> Delete
                            </Button>
                        )}
                    </div>
                </div>

                <dl className="grid gap-x-6 gap-y-3 rounded-xl border p-4 text-sm sm:grid-cols-2">
                    {details
                        .filter(([, value]) => value !== null && value !== '')
                        .map(([label, value]) => (
                            <div key={label}>
                                <dt className="text-muted-foreground">
                                    {label}
                                </dt>
                                <dd className="font-medium">{value}</dd>
                            </div>
                        ))}
                    {asset.notes && (
                        <div className="sm:col-span-2">
                            <dt className="text-muted-foreground">Notes</dt>
                            <dd className="whitespace-pre-line">
                                {asset.notes}
                            </dd>
                        </div>
                    )}
                </dl>

                <section className="rounded-xl border text-sm">
                    <h2 className="px-4 pt-4 pb-2 font-medium">Loan history</h2>
                    {loans.length === 0 ? (
                        <p className="px-4 pb-4 text-muted-foreground">
                            No loans yet.
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead className="text-left text-muted-foreground">
                                    <tr>
                                        <th className="px-4 py-2 font-medium">
                                            Borrower
                                        </th>
                                        <th className="px-4 py-2 font-medium">
                                            Loaned
                                        </th>
                                        <th className="hidden px-4 py-2 font-medium sm:table-cell">
                                            Due
                                        </th>
                                        <th className="hidden px-4 py-2 font-medium sm:table-cell">
                                            Returned
                                        </th>
                                        <th className="px-4 py-2 font-medium">
                                            Status
                                        </th>
                                        {abilities.return && <th />}
                                    </tr>
                                </thead>
                                <tbody>
                                    {loans.map((l) => (
                                        <tr key={l.id} className="border-t">
                                            <td className="px-4 py-2">
                                                {l.borrower}
                                            </td>
                                            <td className="px-4 py-2 whitespace-nowrap">
                                                {formatDate(l.loaned_at)}
                                            </td>
                                            <td className="hidden px-4 py-2 whitespace-nowrap sm:table-cell">
                                                {formatDate(l.due_date)}
                                            </td>
                                            <td className="hidden px-4 py-2 whitespace-nowrap sm:table-cell">
                                                {formatDate(l.returned_at)}
                                                {l.return_condition &&
                                                    ` (${l.return_condition})`}
                                            </td>
                                            <td className="px-4 py-2">
                                                <LoanStatusBadge loan={l} />
                                            </td>
                                            {abilities.return && (
                                                <td className="px-4 py-2 text-right">
                                                    {!l.returned_at && (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() =>
                                                                setReturning(l)
                                                            }
                                                        >
                                                            Return
                                                        </Button>
                                                    )}
                                                </td>
                                            )}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                {abilities.maintain && (
                    <section className="rounded-xl border text-sm">
                        <h2 className="px-4 pt-4 pb-2 font-medium">
                            Maintenance history
                        </h2>
                        {maintenances.length === 0 ? (
                            <p className="px-4 pb-4 text-muted-foreground">
                                No maintenance yet.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead className="text-left text-muted-foreground">
                                        <tr>
                                            <th className="px-4 py-2 font-medium">
                                                Work
                                            </th>
                                            <th className="px-4 py-2 font-medium">
                                                Started
                                            </th>
                                            <th className="hidden px-4 py-2 font-medium sm:table-cell">
                                                Completed
                                            </th>
                                            <th className="hidden px-4 py-2 text-right font-medium sm:table-cell">
                                                Cost
                                            </th>
                                            <th />
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {maintenances.map((m) => (
                                            <tr key={m.id} className="border-t">
                                                <td className="px-4 py-2">
                                                    {m.description}
                                                    <div className="text-muted-foreground">
                                                        {[
                                                            m.vendor,
                                                            `by ${m.recorded_by}`,
                                                        ]
                                                            .filter(Boolean)
                                                            .join(' · ')}
                                                    </div>
                                                </td>
                                                <td className="px-4 py-2 whitespace-nowrap">
                                                    {formatDate(m.started_at)}
                                                </td>
                                                <td className="hidden px-4 py-2 whitespace-nowrap sm:table-cell">
                                                    {formatDate(m.completed_at)}
                                                </td>
                                                <td className="hidden px-4 py-2 text-right whitespace-nowrap sm:table-cell">
                                                    {m.cost === null
                                                        ? '—'
                                                        : rupiah.format(
                                                              Number(m.cost),
                                                          )}
                                                </td>
                                                <td className="px-4 py-2 text-right">
                                                    {m.status ===
                                                    'in_progress' ? (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() =>
                                                                setCompleting(m)
                                                            }
                                                        >
                                                            Complete
                                                        </Button>
                                                    ) : (
                                                        <span className="text-muted-foreground">
                                                            Done
                                                        </span>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </section>
                )}

                {abilities.move && (
                    <section className="rounded-xl border text-sm">
                        <h2 className="px-4 pt-4 pb-2 font-medium">
                            Location history
                        </h2>
                        {movements.length === 0 ? (
                            <p className="px-4 pb-4 text-muted-foreground">
                                Never moved.
                            </p>
                        ) : (
                            <ul>
                                {movements.map((m) => (
                                    <li
                                        key={m.id}
                                        className="flex flex-wrap justify-between gap-x-4 border-t px-4 py-2"
                                    >
                                        <span>
                                            {m.from} → {m.to}
                                            {m.reason && (
                                                <span className="text-muted-foreground">
                                                    {` · ${m.reason}`}
                                                </span>
                                            )}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {formatDate(m.moved_at)} by{' '}
                                            {m.recorded_by}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                )}
            </div>

            {abilities.move && (
                <MoveAssetDialog
                    open={moving}
                    onClose={() => setMoving(false)}
                    asset={asset}
                    locations={locations}
                />
            )}
            {abilities.maintain && (
                <>
                    <StartMaintenanceDialog
                        open={starting}
                        onClose={() => setStarting(false)}
                        asset={asset}
                    />
                    <CompleteMaintenanceDialog
                        maintenance={completing}
                        onClose={() => setCompleting(null)}
                        assetCode={asset.code}
                    />
                </>
            )}

            <Dialog open={disposing} onOpenChange={setDisposing}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Dispose {asset.code}?</DialogTitle>
                        <DialogDescription>
                            Disposal is final: the asset stays in the records
                            but can no longer be borrowed, moved or serviced.
                        </DialogDescription>
                    </DialogHeader>
                    <Form
                        {...AssetController.dispose.form(asset.id)}
                        onSuccess={() => setDisposing(false)}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="reason">Reason</Label>
                                    <textarea
                                        id="reason"
                                        name="reason"
                                        rows={3}
                                        required
                                        maxLength={500}
                                        placeholder="e.g. Beyond repair, written off"
                                        className="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                    />
                                    <InputError message={errors.reason} />
                                </div>
                                <DialogFooter>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setDisposing(false)}
                                    >
                                        Cancel
                                    </Button>
                                    <Button
                                        variant="destructive"
                                        disabled={processing}
                                    >
                                        Dispose
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>

            <ReturnLoanDialog
                loan={
                    returning && {
                        id: returning.id,
                        assetCode: asset.code,
                        borrower: returning.borrower,
                    }
                }
                conditions={conditions}
                onClose={() => setReturning(null)}
            />
        </>
    );
}

AssetShow.layout = {
    breadcrumbs: [{ title: 'Assets', href: index() }],
};
