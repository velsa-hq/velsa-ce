import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';

type VenueAssignment = {
    venue_id: number;
    venue_name: string;
    venue_slug: string;
    users: {
        id: number;
        name: string;
        email: string;
        via_role: string;
    }[];
};

type Props = {
    permission: {
        name: string;
        module: string;
        granted_by_roles: string[];
    };
    assignments: VenueAssignment[];
};

export default function PermissionShow({ permission, assignments }: Props) {
    const totalUsers = assignments.reduce((sum, a) => sum + a.users.length, 0);

    return (
        <>
            <Head title={`${permission.name} · Permissions`} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <header className="flex flex-col gap-1">
                    <Link
                        href="/admin/permissions"
                        className="inline-flex w-fit items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                    >
                        <ArrowLeft className="size-3" />
                        All permissions
                    </Link>
                    <h1 className="font-mono text-2xl font-semibold tracking-tight">
                        {permission.name}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Module: <strong>{permission.module}</strong>. Granted by{' '}
                        {permission.granted_by_roles.length} role
                        {permission.granted_by_roles.length === 1 ? '' : 's'} ·
                        held by {totalUsers} user-venue assignment
                        {totalUsers === 1 ? '' : 's'}.
                    </p>
                </header>

                <Card>
                    <CardContent className="flex flex-col gap-2 p-4">
                        <h2 className="text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                            Granted by
                        </h2>
                        {permission.granted_by_roles.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No roles currently grant this permission.
                            </p>
                        ) : (
                            <div className="flex flex-wrap gap-2">
                                {permission.granted_by_roles.map((role) => (
                                    <span
                                        key={role}
                                        className="inline-flex items-center rounded-full bg-neutral-100 px-2 py-0.5 text-xs font-medium text-neutral-700 dark:bg-neutral-800 dark:text-neutral-200"
                                    >
                                        {role}
                                    </span>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="flex flex-col gap-3 p-4">
                        <h2 className="text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                            Who has it
                        </h2>

                        {assignments.length === 0 ? (
                            <p className="rounded-md border border-dashed border-sidebar-border/60 p-4 text-center text-sm text-muted-foreground dark:border-sidebar-border">
                                No user-venue assignment currently grants this
                                permission.
                            </p>
                        ) : (
                            assignments.map((a) => (
                                <section
                                    key={a.venue_id}
                                    className="flex flex-col gap-2"
                                >
                                    <h3 className="border-b border-sidebar-border/40 pb-1 text-xs font-semibold tracking-wider uppercase">
                                        <Link
                                            href={`/venues/${a.venue_slug}`}
                                            className="hover:underline"
                                        >
                                            {a.venue_name}
                                        </Link>
                                    </h3>
                                    <table className="w-full text-sm">
                                        <thead className="text-left text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                            <tr>
                                                <th className="pb-1">User</th>
                                                <th className="pb-1">
                                                    Via role
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {a.users.map((u) => (
                                                <tr
                                                    key={`${a.venue_id}-${u.id}-${u.via_role}`}
                                                    className="border-t border-sidebar-border/40 dark:border-sidebar-border/60"
                                                >
                                                    <td className="py-1.5">
                                                        <Link
                                                            href={`/admin/users/${u.id}`}
                                                            className="flex flex-col hover:underline"
                                                        >
                                                            <span className="font-medium">
                                                                {u.name}
                                                            </span>
                                                            <span className="text-xs text-muted-foreground">
                                                                {u.email}
                                                            </span>
                                                        </Link>
                                                    </td>
                                                    <td className="py-1.5">
                                                        <span className="inline-flex items-center rounded-full bg-neutral-100 px-2 py-0.5 text-xs font-medium text-neutral-700 dark:bg-neutral-800 dark:text-neutral-200">
                                                            {u.via_role}
                                                        </span>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </section>
                            ))
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

PermissionShow.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin/users' },
        { title: 'Permissions', href: '/admin/permissions' },
    ],
};
