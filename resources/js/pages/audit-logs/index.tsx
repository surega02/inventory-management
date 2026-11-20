import { Head, Link, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { fieldClass } from '@/components/return-loan-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { show } from '@/routes/assets';
import { index } from '@/routes/audit-logs';
import type { Paginated } from '@/types';

type Entry = {
    id: number;
    at: string | null;
    causer: string | null;
    event: string | null;
    description: string;
    subject: {
        type: string | null;
        label: string | null;
        asset_id: number | null;
    };
    changes: { old: Record<string, unknown>; new: Record<string, unknown> };
    properties: Record<string, unknown>;
};

type Filters = {
    causer_id?: string;
    subject_type?: string;
    event?: string;
    date_from?: string;
    date_to?: string;
};

type Props = {
    entries: Paginated<Entry>;
    filters: Filters;
    causers: { id: number; name: string }[];
    subjectTypes: string[];
    events: string[];
};

const humanize = (s: string) =>
    (s.charAt(0).toUpperCase() + s.slice(1)).replaceAll('_', ' ');

const showValue = (v: unknown) =>
    v === null || v === undefined || v === ''
        ? '—'
        : typeof v === 'string'
          ? v
          : JSON.stringify(v);

const formatTime = (value: string | null) =>
    value
        ? new Date(value).toLocaleString('en-GB', {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
              hour: '2-digit',
              minute: '2-digit',
          })
        : '—';

/** old → new per field (new only for created, old only for deleted), plus extra properties such as a dispose reason. */
function Changes({ entry }: { entry: Entry }) {
    const keys = [
        ...new Set([
            ...Object.keys(entry.changes.new),
            ...Object.keys(entry.changes.old),
        ]),
    ];
    const props = Object.entries(entry.properties);

    if (keys.length === 0 && props.length === 0) {
        return null;
    }

    return (
        <details className="mt-1">
            <summary className="cursor-pointer text-muted-foreground">
                {keys.length > 0
                    ? `${keys.length} field${keys.length > 1 ? 's' : ''}`
                    : 'Details'}
            </summary>
            <dl className="mt-1 grid grid-cols-[auto_1fr] gap-x-3 gap-y-0.5 text-xs">
                {keys.map((k) => (
                    <div key={k} className="contents">
                        <dt className="font-mono text-muted-foreground">{k}</dt>
                        <dd className="break-all">
                            {k in entry.changes.old && (
                                <>
                                    <span className="text-muted-foreground line-through">
                                        {showValue(entry.changes.old[k])}
                                    </span>
                                    {k in entry.changes.new && ' → '}
                                </>
                            )}
                            {k in entry.changes.new &&
                                showValue(entry.changes.new[k])}
                        </dd>
                    </div>
                ))}
                {props.map(([k, v]) => (
                    <div key={k} className="contents">
                        <dt className="font-mono text-muted-foreground">{k}</dt>
                        <dd className="break-all">{showValue(v)}</dd>
                    </div>
                ))}
            </dl>
        </details>
    );
}

export default function AuditLogs({
    entries,
    filters,
    causers,
    subjectTypes,
    events,
}: Props) {
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

    const select = (
        name: keyof Filters,
        label: string,
        options: { value: string; label: string }[],
    ) => (
        <select
            aria-label={label}
            value={filters[name] ?? ''}
            onChange={(e) => apply({ [name]: e.target.value })}
            className={`h-9 ${fieldClass} py-0`}
        >
            <option value="">{label}</option>
            {options.map((o) => (
                <option key={o.value} value={o.value}>
                    {o.label}
                </option>
            ))}
        </select>
    );

    const filtered = Object.values(filters).some((v) => v);

    return (
        <>
            <Head title="Audit log" />

            <div className="flex flex-col gap-4 p-4">
                <Heading
                    title="Audit log"
                    description={`${entries.total} entries match the filters.`}
                />

                <div className="grid gap-2 sm:flex sm:flex-wrap sm:items-center">
                    {select(
                        'causer_id',
                        'Anyone',
                        causers.map((c) => ({
                            value: String(c.id),
                            label: c.name,
                        })),
                    )}
                    {select(
                        'subject_type',
                        'All records',
                        subjectTypes.map((t) => ({
                            value: t,
                            label: humanize(t),
                        })),
                    )}
                    {select(
                        'event',
                        'All events',
                        events.map((e) => ({ value: e, label: humanize(e) })),
                    )}
                    <div className="flex items-center gap-2">
                        <Input
                            type="date"
                            aria-label="From date"
                            value={filters.date_from ?? ''}
                            max={filters.date_to}
                            onChange={(e) =>
                                apply({ date_from: e.target.value })
                            }
                            className="sm:w-40"
                        />
                        <span className="text-muted-foreground">–</span>
                        <Input
                            type="date"
                            aria-label="To date"
                            value={filters.date_to ?? ''}
                            min={filters.date_from}
                            onChange={(e) => apply({ date_to: e.target.value })}
                            className="sm:w-40"
                        />
                    </div>
                    {filtered && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => router.get(index.url())}
                        >
                            Clear filters
                        </Button>
                    )}
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-2 font-medium">When</th>
                                <th className="px-4 py-2 font-medium">Who</th>
                                <th className="px-4 py-2 font-medium">What</th>
                                <th className="hidden px-4 py-2 font-medium md:table-cell">
                                    Record
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {entries.data.map((e) => (
                                <tr key={e.id} className="border-t align-top">
                                    <td className="px-4 py-2 whitespace-nowrap">
                                        {formatTime(e.at)}
                                    </td>
                                    <td className="px-4 py-2">
                                        {e.causer ?? (
                                            <span className="text-muted-foreground">
                                                System
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-2">
                                        <span className="font-medium">
                                            {e.event
                                                ? humanize(e.event)
                                                : e.description}
                                        </span>
                                        {/* model events repeat the event name as description */}
                                        {e.event &&
                                            e.description !== e.event && (
                                                <span className="text-muted-foreground">
                                                    {' '}
                                                    · {e.description}
                                                </span>
                                            )}
                                        <div className="md:hidden">
                                            <Subject entry={e} />
                                        </div>
                                        <Changes entry={e} />
                                    </td>
                                    <td className="hidden px-4 py-2 md:table-cell">
                                        <Subject entry={e} />
                                    </td>
                                </tr>
                            ))}
                            {entries.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={4}
                                        className="px-4 py-8 text-center text-muted-foreground"
                                    >
                                        No entries match these filters.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {entries.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm text-muted-foreground">
                        <span>
                            Page {entries.current_page} of {entries.last_page}
                        </span>
                        <div className="flex gap-2">
                            {entries.prev_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link
                                        href={entries.prev_page_url}
                                        preserveScroll
                                    >
                                        Previous
                                    </Link>
                                </Button>
                            )}
                            {entries.next_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link
                                        href={entries.next_page_url}
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

function Subject({ entry }: { entry: Entry }) {
    const { type, label, asset_id } = entry.subject;

    if (!type) {
        return null;
    }

    return (
        <span className="text-muted-foreground">
            {humanize(type)}
            {label && ' '}
            {label &&
                (asset_id ? (
                    <Link
                        href={show(asset_id)}
                        className="font-mono text-foreground underline-offset-4 hover:underline"
                    >
                        {label}
                    </Link>
                ) : (
                    <span className="text-foreground">{label}</span>
                ))}
        </span>
    );
}

AuditLogs.layout = {
    breadcrumbs: [{ title: 'Audit log', href: index() }],
};
