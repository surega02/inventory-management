import { Head, Link, router } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Plus, Search } from 'lucide-react';
import AssetStatusBadge, { statusLabel } from '@/components/asset-status-badge';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { create, index, show } from '@/routes/assets';
import type { AssetStatus, Option, Paginated } from '@/types';

type Row = {
    id: number;
    code: string;
    name: string;
    category: string;
    location: string;
    status: AssetStatus;
};

type Filters = {
    q?: string;
    category_id?: string;
    location_id?: string;
    status?: string;
    sort?: string;
};

type Props = {
    assets: Paginated<Row>;
    filters: Filters;
    categories: Option[];
    locations: Option[];
    statuses: AssetStatus[];
};

const selectClass =
    'h-9 rounded-md border border-input bg-transparent px-2 text-sm shadow-xs focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none';

export default function Assets({
    assets,
    filters,
    categories,
    locations,
    statuses,
}: Props) {
    // Each filter change reloads page 1 with the other filters kept; empty values are dropped from the URL.
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

    const sort = filters.sort ?? 'code';
    const sortBy = (column: string) =>
        apply({ sort: sort === column ? `-${column}` : column });
    const sortHeader = (column: string, label: string, className = '') => (
        <th className={`px-4 py-2 font-medium ${className}`}>
            <button
                type="button"
                className="inline-flex items-center gap-1 hover:text-foreground"
                onClick={() => sortBy(column)}
            >
                {label}
                {sort === column && <ArrowUp className="size-3" />}
                {sort === `-${column}` && <ArrowDown className="size-3" />}
            </button>
        </th>
    );

    return (
        <>
            <Head title="Assets" />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-2">
                    <Heading
                        title="Assets"
                        description={`${assets.total} assets match the filters.`}
                    />
                    <Button asChild>
                        <Link href={create()}>
                            <Plus /> Add asset
                        </Link>
                    </Button>
                </div>

                <div className="flex flex-wrap gap-2">
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
                            placeholder="Code, name or serial"
                            aria-label="Search assets"
                            className="pl-8"
                        />
                    </form>
                    <select
                        aria-label="Category"
                        className={selectClass}
                        value={filters.category_id ?? ''}
                        onChange={(e) => apply({ category_id: e.target.value })}
                    >
                        <option value="">All categories</option>
                        {categories.map((c) => (
                            <option key={c.id} value={c.id}>
                                {c.name}
                            </option>
                        ))}
                    </select>
                    <select
                        aria-label="Location"
                        className={selectClass}
                        value={filters.location_id ?? ''}
                        onChange={(e) => apply({ location_id: e.target.value })}
                    >
                        <option value="">All locations</option>
                        {locations.map((l) => (
                            <option key={l.id} value={l.id}>
                                {l.name}
                            </option>
                        ))}
                    </select>
                    <select
                        aria-label="Status"
                        className={selectClass}
                        value={filters.status ?? ''}
                        onChange={(e) => apply({ status: e.target.value })}
                    >
                        <option value="">All statuses</option>
                        {statuses.map((s) => (
                            <option key={s} value={s}>
                                {statusLabel(s)}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                {sortHeader('code', 'Code')}
                                {sortHeader('name', 'Name')}
                                <th className="hidden px-4 py-2 font-medium md:table-cell">
                                    Category
                                </th>
                                <th className="hidden px-4 py-2 font-medium lg:table-cell">
                                    Location
                                </th>
                                {sortHeader('status', 'Status')}
                            </tr>
                        </thead>
                        <tbody>
                            {assets.data.map((a) => (
                                <tr
                                    key={a.id}
                                    className="border-t hover:bg-muted/30"
                                >
                                    <td className="px-4 py-2 font-mono whitespace-nowrap">
                                        <Link
                                            href={show(a.id)}
                                            className="underline-offset-4 hover:underline"
                                        >
                                            {a.code}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-2">{a.name}</td>
                                    <td className="hidden px-4 py-2 md:table-cell">
                                        {a.category}
                                    </td>
                                    <td className="hidden px-4 py-2 text-muted-foreground lg:table-cell">
                                        {a.location}
                                    </td>
                                    <td className="px-4 py-2">
                                        <AssetStatusBadge status={a.status} />
                                    </td>
                                </tr>
                            ))}
                            {assets.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-8 text-center text-muted-foreground"
                                    >
                                        No assets match these filters.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {assets.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm text-muted-foreground">
                        <span>
                            Page {assets.current_page} of {assets.last_page}
                        </span>
                        <div className="flex gap-2">
                            {assets.prev_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link
                                        href={assets.prev_page_url}
                                        preserveScroll
                                    >
                                        Previous
                                    </Link>
                                </Button>
                            )}
                            {assets.next_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link
                                        href={assets.next_page_url}
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
        </>
    );
}

Assets.layout = {
    breadcrumbs: [{ title: 'Assets', href: index() }],
};
