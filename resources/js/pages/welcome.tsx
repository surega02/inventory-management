import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    ArrowRightLeft,
    Bot,
    Boxes,
    ClipboardList,
    History,
    LayoutDashboard,
    LockKeyhole,
    ShieldCheck,
    Wrench,
} from 'lucide-react';
import type { ComponentType, ReactNode } from 'react';

import AppLogoIcon from '@/components/app-logo-icon';
import AssetStatusBadge from '@/components/asset-status-badge';
import { Button } from '@/components/ui/button';
import { dashboard, login } from '@/routes';
import type { AssetStatus } from '@/types';

type Feature = {
    icon: ComponentType<{ className?: string }>;
    title: string;
    body: string;
};

const features: Feature[] = [
    {
        icon: Boxes,
        title: 'Asset register',
        body: 'Every item gets a code like LAP-0001, generated per category. Track status, condition, location and purchase value.',
    },
    {
        icon: ClipboardList,
        title: 'Loans & returns',
        body: 'Staff record who borrowed what and when it is due. Overdue loans are flagged automatically.',
    },
    {
        icon: ArrowRightLeft,
        title: 'Location moves',
        body: 'Move assets between rooms or buildings with a full movement trail. Borrowed assets stay put.',
    },
    {
        icon: Wrench,
        title: 'Maintenance log',
        body: 'Send an asset to maintenance, note the cost and outcome, and bring it back into service.',
    },
    {
        icon: History,
        title: 'Audit log',
        body: 'Every write action is recorded: who changed what, and when. Visible to admins only.',
    },
    {
        icon: LayoutDashboard,
        title: 'Dashboard per role',
        body: 'Admins and staff see inventory health at a glance. Employees see the items they hold.',
    },
];

const roles = [
    {
        name: 'Admin',
        sees: 'All assets, all loans, users and the audit log.',
        chat: 'Full scope',
    },
    {
        name: 'Warehouse staff',
        sees: 'All assets and loans. Records loans, moves and maintenance.',
        chat: 'Operational scope',
    },
    {
        name: 'Employee',
        sees: 'Only the assets they are currently borrowing.',
        chat: 'Own loans only',
    },
];

const previewAssets: { code: string; name: string; status: AssetStatus }[] = [
    { code: 'LAP-0012', name: 'ThinkPad T14', status: 'borrowed' },
    { code: 'PRJ-0003', name: 'Epson projector', status: 'available' },
    { code: 'PRN-0007', name: 'Laser printer', status: 'maintenance' },
    { code: 'MON-0021', name: '27" monitor', status: 'broken' },
];

function Section({
    id,
    eyebrow,
    title,
    children,
}: {
    id?: string;
    eyebrow: string;
    title: string;
    children: ReactNode;
}) {
    return (
        <section
            id={id}
            className="mx-auto w-full max-w-6xl px-4 py-16 sm:px-6 lg:py-24"
        >
            <p className="text-sm font-medium text-primary/70">{eyebrow}</p>
            <h2 className="mt-2 max-w-2xl text-2xl font-semibold tracking-tight sm:text-3xl">
                {title}
            </h2>
            <div className="mt-10">{children}</div>
        </section>
    );
}

export default function Welcome() {
    const { auth, name } = usePage().props;
    const cta = auth.user
        ? { href: dashboard(), label: 'Go to dashboard' }
        : { href: login(), label: 'Log in' };

    return (
        <>
            <Head title="Welcome" />
            <div className="min-h-screen bg-background text-foreground">
                <header className="sticky top-0 z-10 border-b bg-background/80 backdrop-blur">
                    <div className="mx-auto flex h-14 max-w-6xl items-center justify-between px-4 sm:px-6">
                        <Link
                            href="/"
                            className="flex items-center gap-2 font-semibold"
                        >
                            <span className="flex size-8 items-center justify-center rounded-md bg-primary text-primary-foreground">
                                <AppLogoIcon className="size-5 fill-current" />
                            </span>
                            {name}
                        </Link>
                        <nav className="flex items-center gap-1 text-sm">
                            <a
                                href="#features"
                                className="hidden rounded-md px-3 py-2 text-muted-foreground hover:text-foreground sm:inline"
                            >
                                Features
                            </a>
                            <a
                                href="#chatbot"
                                className="hidden rounded-md px-3 py-2 text-muted-foreground hover:text-foreground sm:inline"
                            >
                                Chatbot
                            </a>
                            <a
                                href="#roles"
                                className="hidden rounded-md px-3 py-2 text-muted-foreground hover:text-foreground sm:inline"
                            >
                                Roles
                            </a>
                            <Button asChild size="sm" className="ml-2">
                                <Link href={cta.href}>{cta.label}</Link>
                            </Button>
                        </nav>
                    </div>
                </header>

                <main>
                    {/* Hero */}
                    <section className="mx-auto grid w-full max-w-6xl items-center gap-12 px-4 pt-16 pb-12 sm:px-6 lg:grid-cols-2 lg:pt-24">
                        <div>
                            <p className="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs text-muted-foreground">
                                <ShieldCheck className="size-3.5" />
                                Role-based access, down to every row
                            </p>
                            <h1 className="mt-5 text-4xl font-semibold tracking-tight text-balance sm:text-5xl">
                                Know where every asset is, and who has it.
                            </h1>
                            <p className="mt-5 max-w-xl text-lg text-muted-foreground">
                                {name} keeps your organisation's laptops,
                                projectors and equipment in one register: loans,
                                moves, maintenance and a full audit trail. Ask
                                the built-in assistant in plain Indonesian or
                                English.
                            </p>
                            <div className="mt-8 flex flex-wrap gap-3">
                                <Button asChild size="lg">
                                    <Link href={cta.href}>
                                        {cta.label}
                                        <ArrowRight />
                                    </Link>
                                </Button>
                                <Button asChild size="lg" variant="outline">
                                    <a href="#features">See what it does</a>
                                </Button>
                            </div>
                        </div>

                        {/* Product preview */}
                        <div className="rounded-xl border bg-card p-4 shadow-sm sm:p-5">
                            <div className="grid grid-cols-3 gap-3">
                                {[
                                    ['Assets', '1,000'],
                                    ['On loan', '184'],
                                    ['Overdue', '9'],
                                ].map(([label, value]) => (
                                    <div
                                        key={label}
                                        className="rounded-lg border p-3"
                                    >
                                        <p className="text-xs text-muted-foreground">
                                            {label}
                                        </p>
                                        <p
                                            className={`mt-1 text-xl font-semibold tabular-nums ${label === 'Overdue' ? 'text-red-600 dark:text-red-400' : ''}`}
                                        >
                                            {value}
                                        </p>
                                    </div>
                                ))}
                            </div>
                            <ul className="mt-4 divide-y rounded-lg border">
                                {previewAssets.map((a) => (
                                    <li
                                        key={a.code}
                                        className="flex items-center justify-between gap-3 px-3 py-2.5 text-sm"
                                    >
                                        <span className="min-w-0">
                                            <span className="font-mono text-xs text-muted-foreground">
                                                {a.code}
                                            </span>
                                            <span className="ml-2 truncate">
                                                {a.name}
                                            </span>
                                        </span>
                                        <AssetStatusBadge status={a.status} />
                                    </li>
                                ))}
                            </ul>
                            <p className="mt-3 text-center text-xs text-muted-foreground">
                                Illustration with sample data
                            </p>
                        </div>
                    </section>

                    {/* Features */}
                    <div className="border-t bg-muted/40">
                        <Section
                            id="features"
                            eyebrow="Features"
                            title="Everything the warehouse needs, nothing it doesn't"
                        >
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                {features.map(({ icon: Icon, title, body }) => (
                                    <div
                                        key={title}
                                        className="rounded-xl border bg-card p-5"
                                    >
                                        <span className="flex size-9 items-center justify-center rounded-md bg-primary/10 text-primary">
                                            <Icon className="size-5" />
                                        </span>
                                        <h3 className="mt-4 font-semibold">
                                            {title}
                                        </h3>
                                        <p className="mt-1.5 text-sm text-muted-foreground">
                                            {body}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </Section>
                    </div>

                    {/* Chatbot */}
                    <Section
                        id="chatbot"
                        eyebrow="Assistant"
                        title="Ask questions. Get answers you're allowed to see."
                    >
                        <div className="grid items-start gap-10 lg:grid-cols-2">
                            <ul className="space-y-5">
                                {[
                                    {
                                        icon: Bot,
                                        title: 'Read-only by design',
                                        body: 'The assistant looks things up. It never creates, edits or lends. Requests to change data are pointed to the right page.',
                                    },
                                    {
                                        icon: LockKeyhole,
                                        title: 'Same rules as the app',
                                        body: "Answers come from the same access checks as every page. An employee asking about someone else's laptop gets nothing.",
                                    },
                                    {
                                        icon: ArrowRight,
                                        title: 'Links, not guesses',
                                        body: 'Each answer links to the real asset or loan page, built from the data itself.',
                                    },
                                ].map(({ icon: Icon, title, body }) => (
                                    <li key={title} className="flex gap-4">
                                        <span className="flex size-9 shrink-0 items-center justify-center rounded-md border">
                                            <Icon className="size-4" />
                                        </span>
                                        <div>
                                            <h3 className="font-semibold">
                                                {title}
                                            </h3>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {body}
                                            </p>
                                        </div>
                                    </li>
                                ))}
                            </ul>

                            <div className="rounded-xl border bg-card p-4 shadow-sm">
                                <div className="flex items-center gap-2 border-b pb-3 text-sm font-medium">
                                    <Bot className="size-4" /> Assistant
                                </div>
                                <div className="space-y-3 pt-4 text-sm">
                                    <p className="ml-auto w-fit max-w-[85%] rounded-lg bg-primary px-3 py-2 text-primary-foreground">
                                        Which loans are overdue this week?
                                    </p>
                                    <div className="max-w-[90%] rounded-lg bg-muted px-3 py-2">
                                        <p>There are 3 overdue loans:</p>
                                        <ul className="mt-1.5 list-disc space-y-0.5 pl-5">
                                            <li>LAP-0012, due 14 Nov</li>
                                            <li>PRJ-0003, due 17 Nov</li>
                                            <li>CAM-0002, due 18 Nov</li>
                                        </ul>
                                        <p className="mt-2 flex flex-wrap gap-2">
                                            {[
                                                'LAP-0012',
                                                'PRJ-0003',
                                                'CAM-0002',
                                            ].map((c) => (
                                                <span
                                                    key={c}
                                                    className="rounded border bg-background px-1.5 py-0.5 font-mono text-xs"
                                                >
                                                    {c} ↗
                                                </span>
                                            ))}
                                        </p>
                                    </div>
                                    <p className="ml-auto w-fit max-w-[85%] rounded-lg bg-primary px-3 py-2 text-primary-foreground">
                                        Pinjamkan LAP-0015 ke Budi.
                                    </p>
                                    <p className="max-w-[90%] rounded-lg bg-muted px-3 py-2">
                                        Saya hanya bisa membaca data. Silakan
                                        catat peminjaman di halaman Loans.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </Section>

                    {/* Roles */}
                    <div className="border-t bg-muted/40">
                        <Section
                            id="roles"
                            eyebrow="Access"
                            title="Three roles. Each sees exactly what it should."
                        >
                            <div className="grid gap-4 md:grid-cols-3">
                                {roles.map((r) => (
                                    <div
                                        key={r.name}
                                        className="flex flex-col rounded-xl border bg-card p-5"
                                    >
                                        <h3 className="font-semibold">
                                            {r.name}
                                        </h3>
                                        <p className="mt-2 flex-1 text-sm text-muted-foreground">
                                            {r.sees}
                                        </p>
                                        <p className="mt-4 flex items-center gap-2 border-t pt-3 text-xs text-muted-foreground">
                                            <Bot className="size-3.5" />{' '}
                                            Assistant: {r.chat}
                                        </p>
                                    </div>
                                ))}
                            </div>
                            <p className="mt-6 text-sm text-muted-foreground">
                                Loans are recorded by staff directly. There is
                                no public sign-up: accounts are created by an
                                admin.
                            </p>
                        </Section>
                    </div>

                    {/* CTA */}
                    <section className="mx-auto w-full max-w-6xl px-4 py-16 text-center sm:px-6 lg:py-24">
                        <h2 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                            Ready to take stock?
                        </h2>
                        <p className="mx-auto mt-3 max-w-md text-muted-foreground">
                            Sign in with the account your admin gave you.
                        </p>
                        <Button asChild size="lg" className="mt-8">
                            <Link href={cta.href}>
                                {cta.label}
                                <ArrowRight />
                            </Link>
                        </Button>
                    </section>
                </main>

                <footer className="border-t">
                    <div className="mx-auto flex max-w-6xl flex-col items-center justify-between gap-2 px-4 py-6 text-xs text-muted-foreground sm:flex-row sm:px-6">
                        <span>{name}</span>
                        <span>Built with Laravel, Inertia and React</span>
                    </div>
                </footer>
            </div>
        </>
    );
}
