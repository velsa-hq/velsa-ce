import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    create as usersCreate,
    index as usersIndex,
} from '@/routes/admin/users';

type Assignment = {
    venue_slug: string;
    venue_name: string;
    role: string;
};

type UserRow = {
    id: number;
    name: string;
    email: string;
    license_tier: string;
    disabled_reason: string | null;
    last_active_at: string | null;
    email_verified: boolean;
    assignments: Assignment[];
};

type Props = {
    users: UserRow[];
    filters: { q: string };
};

const ROLE_COLORS: Record<string, string> = {
    super_admin:
        'bg-purple-100 text-purple-900 dark:bg-purple-900/40 dark:text-purple-100',
    org_admin:
        'bg-blue-100 text-blue-900 dark:bg-blue-900/40 dark:text-blue-100',
    venue_admin: 'bg-sky-100 text-sky-900 dark:bg-sky-900/40 dark:text-sky-100',
    sales_manager:
        'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-100',
    sales_rep:
        'bg-teal-100 text-teal-900 dark:bg-teal-900/40 dark:text-teal-100',
    event_coordinator:
        'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100',
    ops_lead:
        'bg-orange-100 text-orange-900 dark:bg-orange-900/40 dark:text-orange-100',
    finance: 'bg-rose-100 text-rose-900 dark:bg-rose-900/40 dark:text-rose-100',
    read_only:
        'bg-neutral-200 text-neutral-700 dark:bg-neutral-700/60 dark:text-neutral-200',
    exhibitor:
        'bg-fuchsia-100 text-fuchsia-900 dark:bg-fuchsia-900/40 dark:text-fuchsia-100',
    contractor:
        'bg-slate-200 text-slate-700 dark:bg-slate-700/60 dark:text-slate-200',
};

function roleBadge(role: string) {
    const cls =
        ROLE_COLORS[role] ??
        'bg-neutral-100 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-200';

    return `inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${cls}`;
}

function formatLastActive(iso: string | null): string {
    if (!iso) {
        return 'never';
    }

    const date = new Date(iso);
    const diffMs = Date.now() - date.getTime();
    const minutes = Math.floor(diffMs / 60_000);

    if (minutes < 1) {
        return 'just now';
    }

    if (minutes < 60) {
        return `${minutes}m ago`;
    }

    const hours = Math.floor(minutes / 60);

    if (hours < 24) {
        return `${hours}h ago`;
    }

    const days = Math.floor(hours / 24);

    return `${days}d ago`;
}

export default function AdminUsersIndex({ users, filters }: Props) {
    const [q, setQ] = useState(filters.q ?? '');

    const search = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(usersIndex().url, q ? { q } : {}, {
            preserveState: true,
            replace: true,
        });
    };

    return (
        <>
            <Head title="Users · Admin" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <header className="flex flex-col gap-2">
                    <div className="flex items-center justify-between gap-2">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Users
                        </h1>
                        <Link href={usersCreate().url}>
                            <Button size="sm">New user</Button>
                        </Link>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        {users.length} {users.length === 1 ? 'user' : 'users'}
                        {filters.q ? ` matching "${filters.q}"` : ''} ·
                        venue-scoped roles via Spatie/laravel-permission teams
                    </p>
                    <form onSubmit={search} className="flex max-w-sm gap-2">
                        <Input
                            type="search"
                            placeholder="Search users by name or email..."
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                        />
                    </form>
                </header>

                <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium">
                                    User
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Status
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Last active
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Venue assignments
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.map((user, idx) => (
                                <tr
                                    key={user.id}
                                    className={
                                        idx % 2 === 0
                                            ? 'border-t border-sidebar-border/40 dark:border-sidebar-border/60'
                                            : 'border-t border-sidebar-border/40 bg-muted/20 dark:border-sidebar-border/60'
                                    }
                                >
                                    <td className="px-4 py-3">
                                        <Link
                                            href={`/admin/users/${user.id}`}
                                            className="flex flex-col hover:underline"
                                        >
                                            <span className="font-medium">
                                                {user.name}
                                            </span>
                                            <span className="text-xs text-muted-foreground">
                                                {user.email}
                                            </span>
                                        </Link>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-col gap-1">
                                            <span
                                                className={
                                                    user.disabled_reason
                                                        ? 'inline-flex w-fit items-center rounded-full bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-900 dark:bg-rose-900/40 dark:text-rose-100'
                                                        : user.email_verified
                                                          ? 'inline-flex w-fit items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-100'
                                                          : 'inline-flex w-fit items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-900 dark:bg-amber-900/40 dark:text-amber-100'
                                                }
                                            >
                                                {user.disabled_reason
                                                    ? `Disabled · ${user.disabled_reason}`
                                                    : user.email_verified
                                                      ? 'Verified'
                                                      : 'Unverified'}
                                            </span>
                                            <span className="text-xs text-muted-foreground">
                                                {user.license_tier}
                                            </span>
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {formatLastActive(user.last_active_at)}
                                    </td>
                                    <td className="px-4 py-3">
                                        {user.assignments.length === 0 ? (
                                            <span className="text-xs text-muted-foreground italic">
                                                no venue access
                                            </span>
                                        ) : (
                                            <div className="flex flex-wrap gap-1.5">
                                                {user.assignments.map((a) => (
                                                    <span
                                                        key={`${user.id}-${a.venue_slug}`}
                                                        className="inline-flex items-center gap-1.5 rounded-md border border-sidebar-border/60 px-2 py-1 text-xs dark:border-sidebar-border"
                                                        title={`${a.venue_name} · ${a.role}`}
                                                    >
                                                        <span className="font-medium">
                                                            {a.venue_name}
                                                        </span>
                                                        <span
                                                            className={roleBadge(
                                                                a.role,
                                                            )}
                                                        >
                                                            {a.role}
                                                        </span>
                                                    </span>
                                                ))}
                                            </div>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

AdminUsersIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin/users' },
        { title: 'Users', href: '/admin/users' },
    ],
};
