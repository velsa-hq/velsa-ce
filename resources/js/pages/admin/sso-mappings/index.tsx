import { Head, Link, router } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type Mapping = {
    id: number;
    entra_group_id: string;
    group_label: string | null;
    role_name: string;
    venue: { id: number; name: string; slug: string } | null;
    created_at: string | null;
};

type Props = {
    mappings: Mapping[];
};

export default function SsoMappingsIndex({ mappings }: Props) {
    const remove = (mapping: Mapping) => {
        if (
            !confirm(
                `Delete the mapping for ${mapping.group_label ?? mapping.entra_group_id}? Users currently logged in via this mapping won't be downgraded until they sign in again.`,
            )
        ) {
            return;
        }

        router.delete(`/admin/sso-mappings/${mapping.id}`);
    };

    return (
        <>
            <Head title="SSO group mappings · Admin" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <header className="flex items-start justify-between gap-3">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            SSO group mappings
                        </h1>
                        <p className="max-w-3xl text-sm text-muted-foreground">
                            When a user signs in via Microsoft Entra, we look up
                            their group memberships and assign roles based on
                            these mappings. A blank venue means "every active
                            venue." Removing a mapping does not revoke roles
                            already assigned - affected users keep their roles
                            until you remove them from{' '}
                            <Link
                                href="/admin/users"
                                className="underline hover:no-underline"
                            >
                                /admin/users
                            </Link>
                            .
                        </p>
                    </div>
                    <Button asChild size="sm">
                        <Link href="/admin/sso-mappings/create">
                            <Plus className="size-4" />
                            New mapping
                        </Link>
                    </Button>
                </header>

                <Card>
                    <CardContent className="p-0">
                        {mappings.length === 0 ? (
                            <p className="p-6 text-center text-sm text-muted-foreground">
                                No mappings yet. New users provisioned via SSO
                                will land roleless until you add one.
                            </p>
                        ) : (
                            <table className="w-full text-sm">
                                <thead className="text-left text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                    <tr className="border-b border-sidebar-border/40 dark:border-sidebar-border/60">
                                        <th className="px-4 py-3">
                                            Entra group
                                        </th>
                                        <th className="px-4 py-3">Role</th>
                                        <th className="px-4 py-3">Venue</th>
                                        <th className="px-4 py-3"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {mappings.map((m, idx) => (
                                        <tr
                                            key={m.id}
                                            className={
                                                idx % 2 === 0
                                                    ? 'border-t border-sidebar-border/40 dark:border-sidebar-border/60'
                                                    : 'border-t border-sidebar-border/40 bg-muted/20 dark:border-sidebar-border/60'
                                            }
                                        >
                                            <td className="px-4 py-3">
                                                <Link
                                                    href={`/admin/sso-mappings/${m.id}`}
                                                    className="flex flex-col hover:underline"
                                                >
                                                    <span className="font-medium">
                                                        {m.group_label ??
                                                            'Unnamed group'}
                                                    </span>
                                                    <span className="font-mono text-[11px] text-muted-foreground">
                                                        {m.entra_group_id}
                                                    </span>
                                                </Link>
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className="inline-flex items-center rounded-full bg-neutral-100 px-2 py-0.5 text-xs font-medium text-neutral-700 dark:bg-neutral-800 dark:text-neutral-200">
                                                    {m.role_name}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground">
                                                {m.venue ? (
                                                    <Link
                                                        href={`/venues/${m.venue.slug}`}
                                                        className="hover:underline"
                                                    >
                                                        {m.venue.name}
                                                    </Link>
                                                ) : (
                                                    <em>every active venue</em>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <Button
                                                    type="button"
                                                    variant="destructive"
                                                    size="sm"
                                                    onClick={() => remove(m)}
                                                    aria-label={`Delete mapping ${m.id}`}
                                                >
                                                    <Trash2 className="size-3.5" />
                                                    Delete
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

SsoMappingsIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin/users' },
        { title: 'SSO mappings', href: '/admin/sso-mappings' },
    ],
};
