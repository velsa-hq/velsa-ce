import { Head, Link, router } from '@inertiajs/react';
import { Copy, Lock, Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type RoleRow = {
    id: number;
    name: string;
    permission_count: number;
    user_count: number;
    is_built_in: boolean;
};

type Props = {
    roles: RoleRow[];
};

export default function RolesIndex({ roles }: Props) {
    const remove = (role: RoleRow) => {
        if (role.is_built_in) {
            return;
        }

        if (role.user_count > 0) {
            alert(
                `This role is assigned to ${role.user_count} user${role.user_count === 1 ? '' : 's'}. Remove the assignments first.`,
            );

            return;
        }

        if (
            !confirm(`Delete the "${role.name}" role? This cannot be undone.`)
        ) {
            return;
        }

        router.delete(`/admin/roles/${role.id}`);
    };

    return (
        <>
            <Head title="Roles · Admin" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <header className="flex items-start justify-between gap-3">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Roles
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            The built-in 11 roles are locked from edit and
                            delete. Add custom roles for any combination of
                            permissions the built-ins don't cover.
                        </p>
                    </div>
                    <Button
                        asChild
                        size="sm"
                        data-tour-id="strong-rbac-create-role"
                    >
                        <Link href="/admin/roles/create">
                            <Plus className="size-4" />
                            New role
                        </Link>
                    </Button>
                </header>

                <Card>
                    <CardContent
                        className="p-0"
                        data-tour-id="strong-rbac-roles-list"
                    >
                        <table className="w-full text-sm">
                            <thead className="text-left text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                <tr className="border-b border-sidebar-border/40 dark:border-sidebar-border/60">
                                    <th className="px-4 py-3">Role</th>
                                    <th className="px-4 py-3">Permissions</th>
                                    <th className="px-4 py-3">Assigned to</th>
                                    <th className="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody>
                                {roles.map((role, idx) => (
                                    <tr
                                        key={role.id}
                                        className={
                                            idx % 2 === 0
                                                ? 'border-t border-sidebar-border/40 dark:border-sidebar-border/60'
                                                : 'border-t border-sidebar-border/40 bg-muted/20 dark:border-sidebar-border/60'
                                        }
                                    >
                                        <td className="px-4 py-3">
                                            <Link
                                                href={`/admin/roles/${role.id}`}
                                                className="flex items-center gap-2 font-medium hover:underline"
                                            >
                                                <span>{role.name}</span>
                                                {role.is_built_in && (
                                                    <span className="inline-flex items-center gap-1 rounded-full bg-neutral-100 px-2 py-0.5 text-[10px] font-medium tracking-wider text-neutral-700 uppercase dark:bg-neutral-800 dark:text-neutral-200">
                                                        <Lock className="size-2.5" />
                                                        Built-in
                                                    </span>
                                                )}
                                            </Link>
                                        </td>
                                        <td className="px-4 py-3 tabular-nums">
                                            {role.permission_count}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums">
                                            {role.user_count}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="flex items-center justify-end gap-2">
                                                <Button
                                                    asChild
                                                    variant="outline"
                                                    size="sm"
                                                    data-tour-id="strong-rbac-clone-role"
                                                >
                                                    <Link
                                                        href={`/admin/roles/${role.id}/clone`}
                                                        aria-label={`Clone ${role.name}`}
                                                    >
                                                        <Copy className="size-3.5" />
                                                        Clone
                                                    </Link>
                                                </Button>
                                                {role.is_built_in ? (
                                                    <span className="text-xs text-muted-foreground">
                                                        locked
                                                    </span>
                                                ) : (
                                                    <Button
                                                        type="button"
                                                        variant="destructive"
                                                        size="sm"
                                                        onClick={() =>
                                                            remove(role)
                                                        }
                                                        aria-label={`Delete ${role.name}`}
                                                    >
                                                        <Trash2 className="size-3.5" />
                                                        Delete
                                                    </Button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

RolesIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin/users' },
        { title: 'Roles', href: '/admin/roles' },
    ],
};
