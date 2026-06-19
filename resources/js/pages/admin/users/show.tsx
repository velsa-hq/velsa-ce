import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, ShieldOff, ShieldCheck, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type Assignment = {
    venue_id: number;
    venue_slug: string;
    venue_name: string;
    role: string;
    expires_at: string | null;
};

type Venue = { id: number; name: string; slug: string };

type Props = {
    user: {
        id: number;
        name: string;
        email: string;
        license_tier: string;
        disabled_reason: string | null;
        email_verified: boolean;
        last_active_at: string | null;
        sso_provisioned_at: string | null;
        created_at: string | null;
        assignments: Assignment[];
    };
    roles: string[];
    venues: Venue[];
    active_venue_count: number;
};

// per-venue assignment, or a collapsed "All venues" row when held everywhere
type DisplayRow = {
    key: string;
    role: string;
    venue_label: string;
    venue_slug: string | null;
    venue_id: number | 'all';
    expires_at: string | null;
    all_venues: boolean;
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

function formatDateTime(iso: string | null): string {
    if (!iso) {
        return '-';
    }

    return new Date(iso).toLocaleString();
}

export default function ShowUser({
    user,
    roles,
    venues,
    active_venue_count,
}: Props) {
    const [name, setName] = useState(user.name);
    const [email, setEmail] = useState(user.email);
    const [venueId, setVenueId] = useState<string>(
        venues[0]?.id.toString() ?? '',
    );

    // collapse a role held at every active venue into one "All venues" row
    const displayRows = useMemo<DisplayRow[]>(() => {
        const byRole = new Map<string, Assignment[]>();

        for (const a of user.assignments) {
            const list = byRole.get(a.role) ?? [];
            list.push(a);
            byRole.set(a.role, list);
        }

        const rows: DisplayRow[] = [];

        for (const [r, list] of byRole) {
            if (active_venue_count > 0 && list.length === active_venue_count) {
                rows.push({
                    key: `all-${r}`,
                    role: r,
                    venue_label: 'All venues',
                    venue_slug: null,
                    venue_id: 'all',
                    expires_at: list[0].expires_at,
                    all_venues: true,
                });
            } else {
                for (const a of list) {
                    rows.push({
                        key: `${a.venue_id}-${r}`,
                        role: r,
                        venue_label: a.venue_name,
                        venue_slug: a.venue_slug,
                        venue_id: a.venue_id,
                        expires_at: a.expires_at,
                        all_venues: false,
                    });
                }
            }
        }

        return rows;
    }, [user.assignments, active_venue_count]);
    const [role, setRole] = useState<string>(roles[0] ?? '');
    const [expiresAt, setExpiresAt] = useState<string>('');
    const [disableReason, setDisableReason] = useState('');

    const profileDirty = name !== user.name || email !== user.email;

    const saveProfile = () => {
        router.put(
            `/admin/users/${user.id}`,
            { name, email },
            { preserveScroll: true },
        );
    };

    const assignRole = () => {
        if (!venueId || !role) {
            return;
        }

        router.post(
            `/admin/users/${user.id}/assignments`,
            {
                venue_id: venueId === 'all' ? 'all' : Number(venueId),
                role,
                expires_at: expiresAt || null,
            },
            { preserveScroll: true, onSuccess: () => setExpiresAt('') },
        );
    };

    const unassignRole = (row: DisplayRow) => {
        const where = row.all_venues ? 'all venues' : `at ${row.venue_label}`;

        if (!confirm(`Remove ${row.role} ${where}?`)) {
            return;
        }

        router.delete(`/admin/users/${user.id}/assignments`, {
            data: { venue_id: row.venue_id, role: row.role },
            preserveScroll: true,
        });
    };

    const disable = () => {
        if (!disableReason.trim()) {
            return;
        }

        router.post(
            `/admin/users/${user.id}/disable`,
            { reason: disableReason },
            {
                preserveScroll: true,
                onSuccess: () => setDisableReason(''),
            },
        );
    };

    const enable = () => {
        router.post(
            `/admin/users/${user.id}/enable`,
            {},
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title={`${user.name} · Users`} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <header className="flex items-start justify-between gap-3">
                    <div className="flex flex-col gap-1">
                        <Link
                            href="/admin/users"
                            className="inline-flex w-fit items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                        >
                            <ArrowLeft className="size-3" />
                            All users
                        </Link>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {user.name}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {user.email}
                        </p>
                    </div>
                    <div className="flex flex-col items-end gap-1 text-xs text-muted-foreground">
                        {user.disabled_reason ? (
                            <span className="inline-flex items-center rounded-full bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-900 dark:bg-rose-900/40 dark:text-rose-100">
                                Disabled · {user.disabled_reason}
                            </span>
                        ) : (
                            <span className="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-100">
                                Active
                            </span>
                        )}
                        <span>
                            Last active: {formatDateTime(user.last_active_at)}
                        </span>
                        {user.sso_provisioned_at && (
                            <span>
                                SSO-provisioned:{' '}
                                {formatDateTime(user.sso_provisioned_at)}
                            </span>
                        )}
                        <Link
                            href={`/admin/users/${user.id}/permissions`}
                            className="text-xs underline hover:no-underline"
                        >
                            View effective permissions
                        </Link>
                    </div>
                </header>

                <div className="grid gap-4 lg:grid-cols-2">
                    {/* Profile */}
                    <Card>
                        <CardContent className="flex flex-col gap-3 p-4">
                            <h2 className="text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                                Profile
                            </h2>
                            <label className="flex flex-col gap-1 text-xs font-medium">
                                Name
                                <Input
                                    value={name}
                                    onChange={(e) => setName(e.target.value)}
                                />
                            </label>
                            <label className="flex flex-col gap-1 text-xs font-medium">
                                Email
                                <Input
                                    type="email"
                                    value={email}
                                    onChange={(e) => setEmail(e.target.value)}
                                />
                            </label>
                            <div className="flex items-center justify-between gap-2">
                                <span className="text-xs text-muted-foreground">
                                    Tier: {user.license_tier}
                                </span>
                                <Button
                                    type="button"
                                    size="sm"
                                    onClick={saveProfile}
                                    disabled={!profileDirty}
                                >
                                    Save changes
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Account status */}
                    <Card>
                        <CardContent className="flex flex-col gap-3 p-4">
                            <h2 className="text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                                Account status
                            </h2>
                            {user.disabled_reason ? (
                                <>
                                    <p className="text-sm">
                                        This account is currently disabled.
                                        Reason: <em>{user.disabled_reason}</em>
                                    </p>
                                    <Button
                                        type="button"
                                        size="sm"
                                        onClick={enable}
                                    >
                                        <ShieldCheck className="size-4" />
                                        Re-enable account
                                    </Button>
                                </>
                            ) : (
                                <>
                                    <p className="text-sm">
                                        Disabling locks the account out of all
                                        sign-ins. SSO users won't be
                                        re-provisioned automatically.
                                    </p>
                                    <label className="flex flex-col gap-1 text-xs font-medium">
                                        Reason (required, shown in the audit
                                        log)
                                        <Input
                                            value={disableReason}
                                            onChange={(e) =>
                                                setDisableReason(e.target.value)
                                            }
                                            placeholder="e.g. left the organization"
                                        />
                                    </label>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="destructive"
                                        onClick={disable}
                                        disabled={!disableReason.trim()}
                                    >
                                        <ShieldOff className="size-4" />
                                        Disable account
                                    </Button>
                                </>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Venue x role assignments */}
                <Card data-tour-id="strong-rbac-assign-role">
                    <CardContent className="flex flex-col gap-3 p-4">
                        <h2 className="text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                            Venue x role assignments
                        </h2>

                        {displayRows.length === 0 ? (
                            <p className="rounded-md border border-dashed border-sidebar-border/60 p-4 text-center text-sm text-muted-foreground dark:border-sidebar-border">
                                No roles assigned yet. Add one below.
                            </p>
                        ) : (
                            <table className="w-full text-sm">
                                <thead className="text-left text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                    <tr>
                                        <th className="pb-2">Venue</th>
                                        <th className="pb-2">Role</th>
                                        <th className="pb-2">Expires</th>
                                        <th className="pb-2"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {displayRows.map((a) => (
                                        <tr
                                            key={a.key}
                                            className="border-t border-sidebar-border/40 dark:border-sidebar-border/60"
                                        >
                                            <td className="py-2">
                                                {a.all_venues ||
                                                !a.venue_slug ? (
                                                    <span className="font-medium">
                                                        {a.venue_label}
                                                    </span>
                                                ) : (
                                                    <Link
                                                        href={`/venues/${a.venue_slug}`}
                                                        className="hover:underline"
                                                    >
                                                        {a.venue_label}
                                                    </Link>
                                                )}
                                            </td>
                                            <td className="py-2">
                                                <span
                                                    className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${ROLE_COLORS[a.role] ?? 'bg-neutral-100 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-200'}`}
                                                >
                                                    {a.role}
                                                </span>
                                            </td>
                                            <td className="py-2 text-xs text-muted-foreground">
                                                {a.expires_at
                                                    ? new Date(
                                                          a.expires_at,
                                                      ).toLocaleDateString()
                                                    : '-'}
                                            </td>
                                            <td className="py-2 text-right">
                                                <Button
                                                    type="button"
                                                    variant="destructive"
                                                    size="sm"
                                                    onClick={() =>
                                                        unassignRole(a)
                                                    }
                                                    aria-label={`Remove ${a.role} ${a.all_venues ? 'from all venues' : `at ${a.venue_label}`}`}
                                                >
                                                    <Trash2 className="size-3.5" />
                                                    Remove
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}

                        {/* Add assignment */}
                        <div className="flex flex-wrap items-end gap-2 rounded-md border border-sidebar-border/50 p-3 dark:border-sidebar-border">
                            <label className="flex flex-col gap-1 text-xs font-medium">
                                Venue
                                <select
                                    value={venueId}
                                    onChange={(e) => setVenueId(e.target.value)}
                                    className="rounded-md border border-sidebar-border/70 bg-background px-2 py-1 text-sm dark:border-sidebar-border"
                                >
                                    <option value="all">
                                        All venues (organization-wide)
                                    </option>
                                    {venues.map((v) => (
                                        <option key={v.id} value={v.id}>
                                            {v.name}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <label className="flex flex-col gap-1 text-xs font-medium">
                                Role
                                <select
                                    value={role}
                                    onChange={(e) => setRole(e.target.value)}
                                    className="rounded-md border border-sidebar-border/70 bg-background px-2 py-1 text-sm dark:border-sidebar-border"
                                >
                                    {roles.map((r) => (
                                        <option key={r} value={r}>
                                            {r}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <label className="flex flex-col gap-1 text-xs font-medium">
                                Expires (optional)
                                <input
                                    type="date"
                                    value={expiresAt}
                                    onChange={(e) =>
                                        setExpiresAt(e.target.value)
                                    }
                                    className="rounded-md border border-sidebar-border/70 bg-background px-2 py-1 text-sm dark:border-sidebar-border"
                                />
                            </label>
                            <Button
                                type="button"
                                size="sm"
                                onClick={assignRole}
                                disabled={!venueId || !role}
                            >
                                Assign
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

ShowUser.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin/users' },
        { title: 'Users', href: '/admin/users' },
    ],
};
