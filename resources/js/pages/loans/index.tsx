import { Form, Head, Link, router } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useState } from 'react';
import LoanController from '@/actions/App/Http/Controllers/LoanController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import LoanStatusBadge, { formatDate } from '@/components/loan-status-badge';
import ReturnLoanDialog, { fieldClass } from '@/components/return-loan-dialog';
import SearchPick from '@/components/search-pick';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { show } from '@/routes/assets';
import { index } from '@/routes/loans';
import type { AssetCondition, LoanRow, Paginated, PickOption } from '@/types';

type Row = LoanRow & { asset: { id: number; code: string; name: string } };

type Filters = { q?: string; status?: string };

type Props = {
    loans: Paginated<Row>;
    filters: Filters;
    conditions: AssetCondition[];
    abilities: { lend: boolean; return: boolean };
    assetOptions?: PickOption[];
    borrowerOptions?: PickOption[];
};

const tabs = [
    { value: '', label: 'All' },
    { value: 'active', label: 'Active' },
    { value: 'overdue', label: 'Overdue' },
    { value: 'returned', label: 'Returned' },
];

export default function Loans({
    loans,
    filters,
    conditions,
    abilities,
    assetOptions,
    borrowerOptions,
}: Props) {
    const [lending, setLending] = useState(false);
    const [returning, setReturning] = useState<Row | null>(null);

    const apply = (changes: Filters) => {
        const next = Object.fromEntries(
            Object.entries({ ...filters, ...changes }).filter(
                ([, v]) => v !== '' && v !== undefined && v !== null,
            ),
        );
        router.get(index.url(), next, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    // employees only ever see their own loans, so the borrower column says nothing
    const showBorrower = abilities.lend;

    return (
        <>
            <Head title={abilities.lend ? 'Loans' : 'My loans'} />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-2">
                    <Heading
                        title={abilities.lend ? 'Loans' : 'My loans'}
                        description={`${loans.total} loans match the filters.`}
                    />
                    {abilities.lend && (
                        <Button onClick={() => setLending(true)}>
                            <Plus /> Record loan
                        </Button>
                    )}
                </div>

                <div className="flex flex-wrap gap-2">
                    <div className="inline-flex rounded-md border p-0.5">
                        {tabs.map((t) => (
                            <Button
                                key={t.value}
                                size="sm"
                                variant={
                                    (filters.status ?? '') === t.value
                                        ? 'secondary'
                                        : 'ghost'
                                }
                                onClick={() => apply({ status: t.value })}
                            >
                                {t.label}
                            </Button>
                        ))}
                    </div>
                    <form
                        role="search"
                        className="relative w-full sm:w-64"
                        onSubmit={(e) => {
                            e.preventDefault();
                            const q = new FormData(e.currentTarget).get('q');
                            apply({ q: typeof q === 'string' ? q : '' });
                        }}
                    >
                        <Search className="absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                        <Input
                            name="q"
                            defaultValue={filters.q}
                            placeholder={
                                showBorrower
                                    ? 'Asset code, name or borrower'
                                    : 'Asset code or name'
                            }
                            aria-label="Search loans"
                            className="pl-8"
                        />
                    </form>
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-2 font-medium">Asset</th>
                                {showBorrower && (
                                    <th className="px-4 py-2 font-medium">
                                        Borrower
                                    </th>
                                )}
                                <th className="hidden px-4 py-2 font-medium md:table-cell">
                                    Loaned
                                </th>
                                <th className="px-4 py-2 font-medium">Due</th>
                                <th className="hidden px-4 py-2 font-medium md:table-cell">
                                    Returned
                                </th>
                                <th className="px-4 py-2 font-medium">
                                    Status
                                </th>
                                {abilities.return && (
                                    <th className="px-4 py-2" />
                                )}
                            </tr>
                        </thead>
                        <tbody>
                            {loans.data.map((l) => (
                                <tr
                                    key={l.id}
                                    className="border-t hover:bg-muted/30"
                                >
                                    <td className="px-4 py-2">
                                        <Link
                                            href={show(l.asset.id)}
                                            className="font-mono whitespace-nowrap underline-offset-4 hover:underline"
                                        >
                                            {l.asset.code}
                                        </Link>
                                        <div className="text-muted-foreground">
                                            {l.asset.name}
                                        </div>
                                    </td>
                                    {showBorrower && (
                                        <td className="px-4 py-2">
                                            {l.borrower}
                                        </td>
                                    )}
                                    <td className="hidden px-4 py-2 whitespace-nowrap md:table-cell">
                                        {formatDate(l.loaned_at)}
                                    </td>
                                    <td className="px-4 py-2 whitespace-nowrap">
                                        {formatDate(l.due_date)}
                                    </td>
                                    <td className="hidden px-4 py-2 whitespace-nowrap md:table-cell">
                                        {formatDate(l.returned_at)}
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
                            {loans.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="px-4 py-8 text-center text-muted-foreground"
                                    >
                                        No loans match these filters.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {loans.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm text-muted-foreground">
                        <span>
                            Page {loans.current_page} of {loans.last_page}
                        </span>
                        <div className="flex gap-2">
                            {loans.prev_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link
                                        href={loans.prev_page_url}
                                        preserveScroll
                                    >
                                        Previous
                                    </Link>
                                </Button>
                            )}
                            {loans.next_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link
                                        href={loans.next_page_url}
                                        preserveScroll
                                    >
                                        Next
                                    </Link>
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </div>

            {abilities.lend && (
                <Dialog open={lending} onOpenChange={setLending}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Record loan</DialogTitle>
                            <DialogDescription>
                                Only available assets and active users can be
                                picked.
                            </DialogDescription>
                        </DialogHeader>
                        <Form
                            {...LoanController.store.form()}
                            onSuccess={() => setLending(false)}
                            className="space-y-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="asset_pick">
                                            Asset
                                        </Label>
                                        <SearchPick
                                            id="asset_pick"
                                            name="asset_id"
                                            prop="assetOptions"
                                            param="asset_q"
                                            options={assetOptions}
                                            placeholder="Search code, name or serial"
                                        />
                                        <InputError message={errors.asset_id} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="borrower_pick">
                                            Borrower
                                        </Label>
                                        <SearchPick
                                            id="borrower_pick"
                                            name="borrower_id"
                                            prop="borrowerOptions"
                                            param="borrower_q"
                                            options={borrowerOptions}
                                            placeholder="Search name or email"
                                        />
                                        <InputError
                                            message={errors.borrower_id}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="due_date">
                                            Due date (optional)
                                        </Label>
                                        <Input
                                            id="due_date"
                                            name="due_date"
                                            type="date"
                                        />
                                        <InputError message={errors.due_date} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="notes">
                                            Notes (optional)
                                        </Label>
                                        <textarea
                                            id="notes"
                                            name="notes"
                                            rows={2}
                                            maxLength={1000}
                                            className={fieldClass}
                                        />
                                        <InputError message={errors.notes} />
                                    </div>
                                    <DialogFooter>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => setLending(false)}
                                        >
                                            Cancel
                                        </Button>
                                        <Button disabled={processing}>
                                            Record loan
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    </DialogContent>
                </Dialog>
            )}

            <ReturnLoanDialog
                loan={
                    returning && {
                        id: returning.id,
                        assetCode: returning.asset.code,
                        borrower: returning.borrower,
                    }
                }
                conditions={conditions}
                onClose={() => setReturning(null)}
            />
        </>
    );
}

Loans.layout = {
    breadcrumbs: [{ title: 'Loans', href: index() }],
};
