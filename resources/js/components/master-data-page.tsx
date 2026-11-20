import { Form, Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Paginated } from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

export type Field<T> = {
    name: keyof T & string;
    label: string;
    type?: 'text' | 'number' | 'textarea';
    placeholder?: string;
    required?: boolean;
    hint?: string;
    /** Read-only for this row (e.g. a category code already used in asset codes). */
    lockedFor?: (row: T) => string | false;
};

export type Column<T> = {
    label: string;
    cell: (row: T) => ReactNode;
    className?: string;
};

type Row = { id: number; code: string; assets_count: number };

type Props<T extends Row> = {
    title: string;
    description: string;
    singular: string;
    rows: Paginated<T>;
    columns: Column<T>[];
    fields: Field<T>[];
    storeForm: RouteFormDefinition<'post'>;
    // Wayfinder forms always POST; PUT travels as a spoofed _method field
    updateForm: (row: T) => RouteFormDefinition<'post'>;
    destroyUrl: (row: T) => string;
};

/** List + create/edit dialog + delete for small master-data tables (categories, locations). */
export default function MasterDataPage<T extends Row>({
    title,
    description,
    singular,
    rows,
    columns,
    fields,
    storeForm,
    updateForm,
    destroyUrl,
}: Props<T>) {
    // null = closed, 'new' = create, a row = edit
    const [editing, setEditing] = useState<T | 'new' | null>(null);
    const row = editing === 'new' ? null : editing;

    const remove = (r: T) => {
        if (confirm(`Delete ${singular.toLowerCase()} ${r.code}?`)) {
            router.delete(destroyUrl(r), { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title={title} />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-2">
                    <Heading title={title} description={description} />
                    <Button onClick={() => setEditing('new')}>
                        <Plus /> Add {singular.toLowerCase()}
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                {columns.map((c) => (
                                    <th
                                        key={c.label}
                                        className={`px-4 py-2 font-medium ${c.className ?? ''}`}
                                    >
                                        {c.label}
                                    </th>
                                ))}
                                <th className="px-4 py-2">
                                    <span className="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.data.map((r) => (
                                <tr key={r.id} className="border-t">
                                    {columns.map((c) => (
                                        <td
                                            key={c.label}
                                            className={`px-4 py-2 ${c.className ?? ''}`}
                                        >
                                            {c.cell(r)}
                                        </td>
                                    ))}
                                    <td className="px-4 py-2 text-right whitespace-nowrap">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={`Edit ${r.code}`}
                                            onClick={() => setEditing(r)}
                                        >
                                            <Pencil />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={`Delete ${r.code}`}
                                            disabled={r.assets_count > 0}
                                            title={
                                                r.assets_count > 0
                                                    ? 'Still used by assets'
                                                    : undefined
                                            }
                                            onClick={() => remove(r)}
                                        >
                                            <Trash2 />
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                            {rows.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={columns.length + 1}
                                        className="px-4 py-8 text-center text-muted-foreground"
                                    >
                                        No {title.toLowerCase()} yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {rows.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm text-muted-foreground">
                        <span>
                            Page {rows.current_page} of {rows.last_page} ·{' '}
                            {rows.total} total
                        </span>
                        <div className="flex gap-2">
                            {rows.prev_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={rows.prev_page_url}>
                                        Previous
                                    </Link>
                                </Button>
                            )}
                            {rows.next_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={rows.next_page_url}>Next</Link>
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </div>

            <Dialog
                open={editing !== null}
                onOpenChange={(open) => !open && setEditing(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {row
                                ? `Edit ${row.code}`
                                : `Add ${singular.toLowerCase()}`}
                        </DialogTitle>
                    </DialogHeader>

                    <Form
                        key={row?.id ?? 'new'}
                        {...(row ? updateForm(row) : storeForm)}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setEditing(null)}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                {fields.map((f) => {
                                    const locked = row
                                        ? f.lockedFor?.(row)
                                        : false;
                                    const value = row?.[f.name];

                                    return (
                                        <div
                                            key={f.name}
                                            className="grid gap-2"
                                        >
                                            <Label htmlFor={f.name}>
                                                {f.label}
                                            </Label>
                                            {f.type === 'textarea' ? (
                                                <textarea
                                                    id={f.name}
                                                    name={f.name}
                                                    rows={3}
                                                    defaultValue={
                                                        (value as
                                                            | string
                                                            | null) ?? ''
                                                    }
                                                    placeholder={f.placeholder}
                                                    className="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                                />
                                            ) : (
                                                <Input
                                                    id={f.name}
                                                    name={f.name}
                                                    type={f.type ?? 'text'}
                                                    defaultValue={
                                                        (value as
                                                            | string
                                                            | number
                                                            | null) ?? ''
                                                    }
                                                    placeholder={f.placeholder}
                                                    required={f.required}
                                                    readOnly={Boolean(locked)}
                                                    aria-describedby={`${f.name}-hint`}
                                                />
                                            )}
                                            {(locked || f.hint) && (
                                                <p
                                                    id={`${f.name}-hint`}
                                                    className="text-xs text-muted-foreground"
                                                >
                                                    {locked || f.hint}
                                                </p>
                                            )}
                                            <InputError
                                                message={errors[f.name]}
                                            />
                                        </div>
                                    );
                                })}

                                <DialogFooter>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setEditing(null)}
                                    >
                                        Cancel
                                    </Button>
                                    <Button disabled={processing}>Save</Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>
        </>
    );
}
