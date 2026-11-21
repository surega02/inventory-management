import { Form, Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, Power, Search } from 'lucide-react';
import { useState } from 'react';
import UserController from '@/actions/App/Http/Controllers/UserController';
import ConfirmDialog from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
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
import { index } from '@/routes/users';
import type { Paginated, Role } from '@/types';

type UserRow = {
    id: number;
    name: string;
    email: string;
    role: Role | null;
    is_active: boolean;
    last_login_at: string | null;
    is_self: boolean;
};

type Props = {
    users: Paginated<UserRow>;
    roles: Role[];
    filters: { q: string };
};

const roleLabel: Record<Role, string> = {
    admin: 'Admin',
    warehouse_staff: 'Warehouse staff',
    employee: 'Employee',
};

export default function Users({ users, roles, filters }: Props) {
    // null = closed, 'new' = create, a row = edit
    const [editing, setEditing] = useState<UserRow | 'new' | null>(null);
    const user = editing === 'new' ? null : editing;

    const [toggling, setToggling] = useState<UserRow | null>(null);

    return (
        <>
            <Head title="Users" />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-2">
                    <Heading
                        title="Users"
                        description="Accounts and roles. Inactive users cannot log in."
                    />
                    <Button onClick={() => setEditing('new')}>
                        <Plus /> Add user
                    </Button>
                </div>

                <form
                    role="search"
                    className="relative max-w-sm"
                    onSubmit={(e) => {
                        e.preventDefault();
                        const q = new FormData(e.currentTarget).get('q');
                        router.get(
                            index.url(),
                            typeof q === 'string' && q ? { q } : {},
                            {
                                preserveState: true,
                            },
                        );
                    }}
                >
                    <Search className="absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                    <Input
                        name="q"
                        defaultValue={filters.q}
                        placeholder="Search name or email"
                        aria-label="Search users"
                        className="pl-8"
                    />
                </form>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-2 font-medium">Name</th>
                                <th className="hidden px-4 py-2 font-medium md:table-cell">
                                    Email
                                </th>
                                <th className="px-4 py-2 font-medium">Role</th>
                                <th className="px-4 py-2 font-medium">
                                    Status
                                </th>
                                <th className="hidden px-4 py-2 font-medium lg:table-cell">
                                    Last login
                                </th>
                                <th className="px-4 py-2">
                                    <span className="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.data.map((u) => (
                                <tr key={u.id} className="border-t">
                                    <td className="px-4 py-2">
                                        {u.name}
                                        {u.is_self && (
                                            <span className="text-muted-foreground">
                                                {' '}
                                                (you)
                                            </span>
                                        )}
                                        <div className="text-xs text-muted-foreground md:hidden">
                                            {u.email}
                                        </div>
                                    </td>
                                    <td className="hidden px-4 py-2 md:table-cell">
                                        {u.email}
                                    </td>
                                    <td className="px-4 py-2 whitespace-nowrap">
                                        {u.role ? roleLabel[u.role] : '—'}
                                    </td>
                                    <td className="px-4 py-2">
                                        <Badge
                                            variant={
                                                u.is_active
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                        >
                                            {u.is_active
                                                ? 'Active'
                                                : 'Inactive'}
                                        </Badge>
                                    </td>
                                    <td className="hidden px-4 py-2 text-muted-foreground lg:table-cell">
                                        {u.last_login_at
                                            ? new Date(
                                                  u.last_login_at,
                                              ).toLocaleString()
                                            : 'Never'}
                                    </td>
                                    <td className="px-4 py-2 text-right whitespace-nowrap">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={`Edit ${u.name}`}
                                            onClick={() => setEditing(u)}
                                        >
                                            <Pencil />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={`${u.is_active ? 'Deactivate' : 'Activate'} ${u.name}`}
                                            disabled={u.is_self}
                                            title={
                                                u.is_self
                                                    ? 'You cannot deactivate yourself'
                                                    : undefined
                                            }
                                            onClick={() => setToggling(u)}
                                        >
                                            <Power />
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                            {users.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-4 py-8 text-center text-muted-foreground"
                                    >
                                        No users found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {users.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm text-muted-foreground">
                        <span>
                            Page {users.current_page} of {users.last_page} ·{' '}
                            {users.total} total
                        </span>
                        <div className="flex gap-2">
                            {users.prev_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link
                                        href={users.prev_page_url}
                                        preserveScroll
                                    >
                                        Previous
                                    </Link>
                                </Button>
                            )}
                            {users.next_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link
                                        href={users.next_page_url}
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

            <Dialog
                open={editing !== null}
                onOpenChange={(open) => !open && setEditing(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {user ? `Edit ${user.name}` : 'Add user'}
                        </DialogTitle>
                    </DialogHeader>

                    <Form
                        key={user?.id ?? 'new'}
                        {...(user
                            ? UserController.update.form(user.id)
                            : UserController.store.form())}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setEditing(null)}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        defaultValue={user?.name}
                                        required
                                    />
                                    <InputError message={errors.name} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="email">Email</Label>
                                    <Input
                                        id="email"
                                        name="email"
                                        type="email"
                                        defaultValue={user?.email}
                                        required
                                    />
                                    <InputError message={errors.email} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="password">Password</Label>
                                    <Input
                                        id="password"
                                        name="password"
                                        type="password"
                                        autoComplete="new-password"
                                        required={!user}
                                        placeholder={
                                            user
                                                ? 'Leave blank to keep the current password'
                                                : ''
                                        }
                                    />
                                    <InputError message={errors.password} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="role">Role</Label>
                                    <select
                                        id="role"
                                        name="role"
                                        defaultValue={user?.role ?? 'employee'}
                                        disabled={user?.is_self}
                                        className="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                    >
                                        {roles.map((r) => (
                                            <option key={r} value={r}>
                                                {roleLabel[r]}
                                            </option>
                                        ))}
                                    </select>
                                    {user?.is_self && (
                                        // a disabled select is not submitted, so send the unchanged role
                                        <input
                                            type="hidden"
                                            name="role"
                                            value={user.role ?? ''}
                                        />
                                    )}
                                    <InputError message={errors.role} />
                                </div>

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

            <ConfirmDialog
                request={
                    toggling && {
                        title: `${toggling.is_active ? 'Deactivate' : 'Activate'} ${toggling.name}?`,
                        description: toggling.is_active
                            ? 'They are signed out on their next request and cannot log in until an admin activates the account again. Their loan history is kept.'
                            : 'They will be able to log in again with their existing password.',
                        confirmLabel: toggling.is_active
                            ? 'Deactivate user'
                            : 'Activate user',
                        destructive: toggling.is_active,
                        method: 'post',
                        url: UserController.toggleActive.url(toggling.id),
                    }
                }
                onClose={() => setToggling(null)}
            />
        </>
    );
}

Users.layout = {
    breadcrumbs: [{ title: 'Users', href: index() }],
};
