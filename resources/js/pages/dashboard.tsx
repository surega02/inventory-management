import { Head, Link, usePage } from "@inertiajs/react";
import {
    AlertTriangle,
    ArrowLeftRight,
    CheckCircle2,
    Handshake,
    Package,
    Undo2,
    Wrench,
} from "lucide-react";
import type { ReactNode } from "react";
import { statusLabel } from "@/components/asset-status-badge";
import Heading from "@/components/heading";
import LoanStatusBadge, { formatDate } from "@/components/loan-status-badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { dashboard } from "@/routes";
import { index as assetsIndex, show } from "@/routes/assets";
import { index as loansIndex } from "@/routes/loans";
import type { AssetStatus, LoanRow } from "@/types";

type Group = { code: string; name: string; count: number };

type Summary = {
    active_loans: number;
    overdue_loans: number;
    by_status?: Record<AssetStatus, number>;
    by_category?: Group[];
    by_location?: Group[];
};

type Row = LoanRow & { asset: { id: number; code: string; name: string } };

type Event = {
    kind:
        | "lent"
        | "returned"
        | "moved"
        | "maintenance_started"
        | "maintenance_completed";
    at: string;
    asset: { id: number; code: string; name: string };
    detail: string | null;
};

type Props = {
    summary: Summary;
    loans: { kind: "overdue" | "mine"; total: number; items: Row[] };
    recent: Event[];
};

const statusBar: Record<AssetStatus, string> = {
    available: "bg-emerald-500",
    borrowed: "bg-blue-500",
    maintenance: "bg-amber-500",
    broken: "bg-red-500",
    disposed: "bg-muted-foreground/40",
};

const eventMeta: Record<Event["kind"], { icon: ReactNode; label: string }> = {
    lent: { icon: <Handshake className="size-4" />, label: "Lent to" },
    returned: { icon: <Undo2 className="size-4" />, label: "Returned by" },
    moved: { icon: <ArrowLeftRight className="size-4" />, label: "Moved" },
    maintenance_started: {
        icon: <Wrench className="size-4" />,
        label: "Maintenance started",
    },
    maintenance_completed: {
        icon: <CheckCircle2 className="size-4" />,
        label: "Maintenance completed",
    },
};

function Stat({
    label,
    value,
    href,
    tone,
}: {
    label: string;
    value: number;
    href?: string;
    tone?: "danger";
}) {
    const body = (
        <Card className="gap-1 py-4 transition-colors hover:bg-muted/40">
            <CardContent className="px-4">
                <div className="text-sm text-muted-foreground">{label}</div>
                <div
                    className={`text-2xl font-semibold tabular-nums ${
                        tone === "danger" && value > 0
                            ? "text-red-600 dark:text-red-400"
                            : ""
                    }`}
                >
                    {value.toLocaleString("en-US")}
                </div>
            </CardContent>
        </Card>
    );

    return href ? <Link href={href}>{body}</Link> : body;
}

function Bars({ groups }: { groups: Group[] }) {
    const max = Math.max(1, ...groups.map((g) => g.count));

    return (
        <ul className="flex flex-col gap-2">
            {groups.map((g) => (
                <li key={g.code} className="grid grid-cols-[1fr_auto] gap-x-3">
                    <span className="truncate text-sm">{g.name}</span>
                    <span className="text-sm text-muted-foreground tabular-nums">
                        {g.count}
                    </span>
                    <div className="col-span-2 mt-1 h-1.5 rounded-full bg-muted">
                        <div
                            className="h-1.5 rounded-full bg-primary/70"
                            style={{ width: `${(g.count / max) * 100}%` }}
                        />
                    </div>
                </li>
            ))}
        </ul>
    );
}

export default function Dashboard({ summary, loans, recent }: Props) {
    const { auth } = usePage().props;
    const manages = summary.by_status !== undefined;
    const byStatus = summary.by_status;
    const total = byStatus
        ? Object.values(byStatus).reduce((a, b) => a + b, 0)
        : 0;

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex flex-col gap-4 p-4">
                <Heading
                    title={`Hello, ${auth.user?.name ?? ""}`}
                    description={
                        manages
                            ? "Inventory at a glance."
                            : "The assets you currently have on loan."
                    }
                />

                <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                    {byStatus && (
                        <>
                            <Stat
                                label="Assets"
                                value={total}
                                href={assetsIndex.url()}
                            />
                            <Stat
                                label="Available"
                                value={byStatus.available}
                                href={assetsIndex.url({
                                    query: { status: "available" },
                                })}
                            />
                        </>
                    )}
                    <Stat
                        label={manages ? "Active loans" : "On loan to me"}
                        value={summary.active_loans}
                        href={loansIndex.url({ query: { status: "active" } })}
                    />
                    <Stat
                        label="Overdue"
                        value={summary.overdue_loans}
                        href={loansIndex.url({ query: { status: "overdue" } })}
                        tone="danger"
                    />
                </div>

                {byStatus && (
                    <Card className="gap-3 py-4">
                        <CardHeader className="px-4">
                            <CardTitle className="text-base">
                                Assets by status
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3 px-4">
                            <div className="flex h-3 overflow-hidden rounded-full bg-muted">
                                {(Object.keys(byStatus) as AssetStatus[]).map(
                                    (s) =>
                                        byStatus[s] > 0 && (
                                            <div
                                                key={s}
                                                className={statusBar[s]}
                                                style={{
                                                    width: `${(byStatus[s] / Math.max(1, total)) * 100}%`,
                                                }}
                                                title={`${statusLabel(s)}: ${byStatus[s]}`}
                                            />
                                        ),
                                )}
                            </div>
                            <div className="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                                {(Object.keys(byStatus) as AssetStatus[]).map(
                                    (s) => (
                                        <Link
                                            key={s}
                                            href={assetsIndex.url({
                                                query: { status: s },
                                            })}
                                            className="flex items-center gap-1.5 hover:underline"
                                        >
                                            <span
                                                className={`size-2.5 rounded-full ${statusBar[s]}`}
                                            />
                                            {statusLabel(s)}
                                            <span className="text-muted-foreground tabular-nums">
                                                {byStatus[s]}
                                            </span>
                                        </Link>
                                    ),
                                )}
                            </div>
                        </CardContent>
                    </Card>
                )}

                {summary.by_category && summary.by_location && (
                    <div className="grid gap-4 md:grid-cols-2">
                        <Card className="gap-3 py-4">
                            <CardHeader className="px-4">
                                <CardTitle className="text-base">
                                    By category
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="px-4">
                                <Bars groups={summary.by_category} />
                            </CardContent>
                        </Card>
                        <Card className="gap-3 py-4">
                            <CardHeader className="px-4">
                                <CardTitle className="text-base">
                                    By location
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="px-4">
                                <Bars groups={summary.by_location} />
                            </CardContent>
                        </Card>
                    </div>
                )}

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card className="gap-3 py-4">
                        <CardHeader className="flex flex-row items-center justify-between px-4">
                            <CardTitle className="flex items-center gap-2 text-base">
                                {loans.kind === "overdue" ? (
                                    <>
                                        <AlertTriangle className="size-4 text-red-600 dark:text-red-400" />
                                        Most overdue
                                    </>
                                ) : (
                                    <>
                                        <Package className="size-4" />
                                        My loans
                                    </>
                                )}
                            </CardTitle>
                            {loans.total > loans.items.length && (
                                <Link
                                    href={loansIndex.url({
                                        query: {
                                            status:
                                                loans.kind === "overdue"
                                                    ? "overdue"
                                                    : "active",
                                        },
                                    })}
                                    className="text-sm text-muted-foreground hover:underline"
                                >
                                    All {loans.total}
                                </Link>
                            )}
                        </CardHeader>
                        <CardContent className="px-4">
                            {loans.items.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    {loans.kind === "overdue"
                                        ? "No overdue loans."
                                        : "You have no assets on loan."}
                                </p>
                            ) : (
                                <ul className="divide-y">
                                    {loans.items.map((l) => (
                                        <li
                                            key={l.id}
                                            className="flex items-center justify-between gap-3 py-2"
                                        >
                                            <div className="min-w-0">
                                                <Link
                                                    href={show(l.asset.id)}
                                                    className="font-medium hover:underline"
                                                >
                                                    {l.asset.code}
                                                </Link>
                                                <span className="ml-2 truncate text-sm text-muted-foreground">
                                                    {l.asset.name}
                                                </span>
                                                <div className="text-sm text-muted-foreground">
                                                    {loans.kind === "overdue" &&
                                                        `${l.borrower} · `}
                                                    Due {formatDate(l.due_date)}
                                                </div>
                                            </div>
                                            <LoanStatusBadge loan={l} />
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>

                    <Card className="gap-3 py-4">
                        <CardHeader className="px-4">
                            <CardTitle className="text-base">
                                Recent activity
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="px-4">
                            {recent.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Nothing yet.
                                </p>
                            ) : (
                                <ul className="flex flex-col gap-3">
                                    {recent.map((e, i) => (
                                        <li
                                            key={`${e.kind}-${e.asset.id}-${i}`}
                                            className="flex gap-3"
                                        >
                                            <span className="mt-0.5 text-muted-foreground">
                                                {eventMeta[e.kind].icon}
                                            </span>
                                            <div className="min-w-0 text-sm">
                                                {/* employees can only open assets they still have */}
                                                {manages ||
                                                e.kind === "lent" ? (
                                                    <Link
                                                        href={show(e.asset.id)}
                                                        className="font-medium hover:underline"
                                                    >
                                                        {e.asset.code}
                                                    </Link>
                                                ) : (
                                                    <span className="font-medium">
                                                        {e.asset.code}
                                                    </span>
                                                )}{" "}
                                                <span className="text-muted-foreground">
                                                    {eventMeta[e.kind].label}
                                                </span>{" "}
                                                {e.detail && (
                                                    <span className="break-words">
                                                        {e.detail}
                                                    </span>
                                                )}
                                                <div className="text-xs text-muted-foreground">
                                                    {formatDate(e.at)}
                                                </div>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: "Dashboard",
            href: dashboard(),
        },
    ],
};
