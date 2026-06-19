import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Check } from 'lucide-react';
import { useMemo } from 'react';
import { Card, CardContent } from '@/components/ui/card';

type Venue = { id: number; name: string; slug: string };

type PermissionRow = {
    name: string;
    module: string;
    granted: Record<string, boolean>;
};

type Props = {
    user: { id: number; name: string; email: string };
    venues: Venue[];
    roles_by_venue: Record<string, string[]>;
    permissions: PermissionRow[];
};

export default function UserPermissionMatrix({
    user,
    venues,
    roles_by_venue,
    permissions,
}: Props) {
    // Skip permissions the user doesn't have anywhere - keeps the
    // matrix focused on what they CAN do, not the full 38 x N grid.
    const effective = useMemo(
        () =>
            permissions.filter((p) =>
                venues.some((v) => p.granted[String(v.id)]),
            ),
        [permissions, venues],
    );

    const groupedByModule = useMemo(() => {
        const buckets = new Map<string, PermissionRow[]>();

        for (const p of effective) {
            if (!buckets.has(p.module)) {
                buckets.set(p.module, []);
            }

            buckets.get(p.module)!.push(p);
        }

        return Array.from(buckets.entries()).map(([label, perms]) => ({
            label,
            permissions: perms,
        }));
    }, [effective]);

    return (
        <>
            <Head title={`${user.name} · Permissions`} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <header className="flex flex-col gap-1">
                    <Link
                        href={`/admin/users/${user.id}`}
                        className="inline-flex w-fit items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                    >
                        <ArrowLeft className="size-3" />
                        Back to {user.name}
                    </Link>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Effective permissions · {user.name}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Computed as the union of every role this user holds at
                        each venue. Showing only permissions they hold somewhere
                        - the full 38-permission catalog lives at{' '}
                        <Link
                            href="/admin/permissions"
                            className="underline hover:no-underline"
                        >
                            /admin/permissions
                        </Link>
                        .
                    </p>
                </header>

                {venues.length === 0 ? (
                    <Card>
                        <CardContent className="p-6 text-center text-sm text-muted-foreground">
                            This user has no role at any venue. They can't
                            access any venue-scoped data.
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        <Card>
                            <CardContent className="flex flex-col gap-2 p-4">
                                <h2 className="text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                                    Role assignments
                                </h2>
                                <table className="w-full text-sm">
                                    <thead className="text-left text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                        <tr className="border-b border-sidebar-border/40 dark:border-sidebar-border/60">
                                            <th className="py-2">Venue</th>
                                            <th className="py-2">Role(s)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {venues.map((v) => (
                                            <tr
                                                key={v.id}
                                                className="border-t border-sidebar-border/40 dark:border-sidebar-border/60"
                                            >
                                                <td className="py-2 font-medium">
                                                    {v.name}
                                                </td>
                                                <td className="py-2">
                                                    <div className="flex flex-wrap gap-1">
                                                        {(
                                                            roles_by_venue[
                                                                String(v.id)
                                                            ] ?? []
                                                        ).map((r) => (
                                                            <span
                                                                key={r}
                                                                className="inline-flex items-center rounded-full bg-neutral-100 px-2 py-0.5 text-xs font-medium text-neutral-700 dark:bg-neutral-800 dark:text-neutral-200"
                                                            >
                                                                {r}
                                                            </span>
                                                        ))}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent className="p-0">
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead className="sticky top-0 bg-background text-left text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                            <tr className="border-b border-sidebar-border/40 dark:border-sidebar-border/60">
                                                <th className="px-4 py-2">
                                                    Permission
                                                </th>
                                                {venues.map((v) => (
                                                    <th
                                                        key={v.id}
                                                        className="px-3 py-2 text-center"
                                                    >
                                                        {v.name}
                                                    </th>
                                                ))}
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {groupedByModule.length === 0 ? (
                                                <tr>
                                                    <td
                                                        colSpan={
                                                            venues.length + 1
                                                        }
                                                        className="px-4 py-6 text-center text-sm text-muted-foreground italic"
                                                    >
                                                        No effective permissions
                                                        resolved.
                                                    </td>
                                                </tr>
                                            ) : (
                                                groupedByModule.map((group) => (
                                                    <>
                                                        <tr
                                                            key={`hdr-${group.label}`}
                                                            className="bg-muted/40 dark:bg-muted/20"
                                                        >
                                                            <th
                                                                colSpan={
                                                                    venues.length +
                                                                    1
                                                                }
                                                                className="px-4 py-1.5 text-left text-[10px] font-semibold tracking-wider text-muted-foreground uppercase"
                                                            >
                                                                {group.label}
                                                            </th>
                                                        </tr>
                                                        {group.permissions.map(
                                                            (p) => (
                                                                <tr
                                                                    key={p.name}
                                                                    className="border-t border-sidebar-border/40 dark:border-sidebar-border/60"
                                                                >
                                                                    <td className="px-4 py-1.5">
                                                                        <Link
                                                                            href={`/admin/permissions/${p.name}`}
                                                                            className="font-mono text-xs hover:underline"
                                                                        >
                                                                            {
                                                                                p.name
                                                                            }
                                                                        </Link>
                                                                    </td>
                                                                    {venues.map(
                                                                        (v) => (
                                                                            <td
                                                                                key={
                                                                                    v.id
                                                                                }
                                                                                className="px-3 py-1.5 text-center"
                                                                            >
                                                                                {p
                                                                                    .granted[
                                                                                    String(
                                                                                        v.id,
                                                                                    )
                                                                                ] ? (
                                                                                    <Check className="mx-auto size-4 text-emerald-600" />
                                                                                ) : (
                                                                                    <span className="text-muted-foreground">
                                                                                        ·
                                                                                    </span>
                                                                                )}
                                                                            </td>
                                                                        ),
                                                                    )}
                                                                </tr>
                                                            ),
                                                        )}
                                                    </>
                                                ))
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </CardContent>
                        </Card>
                    </>
                )}
            </div>
        </>
    );
}

UserPermissionMatrix.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin/users' },
        { title: 'Users', href: '/admin/users' },
    ],
};
