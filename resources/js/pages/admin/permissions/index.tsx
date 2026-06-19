import { Head, Link, router, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type PermissionRow = {
    name: string;
    action: string;
    role_count: number;
    user_count: number;
    is_custom: boolean;
};

type Group = {
    label: string;
    permissions: PermissionRow[];
};

type Props = {
    groups: Group[];
};

export default function PermissionsIndex({ groups }: Props) {
    const totalPerms = groups.reduce((sum, g) => sum + g.permissions.length, 0);

    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/admin/permissions', {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    const remove = (perm: PermissionRow) => {
        if (
            window.confirm(
                `Delete the custom permission "${perm.name}"? It will be removed from every role.`,
            )
        ) {
            router.delete(`/admin/permissions/${perm.name}`, {
                preserveScroll: true,
            });
        }
    };

    return (
        <>
            <Head title="Permissions · Admin" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <header className="flex flex-col gap-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Permissions
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {totalPerms} permissions across {groups.length} modules.
                        Click any permission to see exactly which users hold it,
                        via which role, at which venue.
                    </p>
                </header>

                <Card>
                    <CardContent className="p-4">
                        <form
                            onSubmit={submit}
                            className="flex flex-wrap items-end gap-2"
                        >
                            <div
                                className="flex flex-col gap-1"
                                data-tour-id="strong-rbac-add-permission"
                            >
                                <label
                                    htmlFor="name"
                                    className="text-xs font-medium"
                                >
                                    Add a custom permission
                                </label>
                                <Input
                                    id="name"
                                    placeholder="module.action (e.g. exports.run)"
                                    value={data.name}
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
                                    className="w-72 font-mono text-sm"
                                />
                            </div>
                            <Button type="submit" disabled={processing}>
                                Add
                            </Button>
                            {errors.name && (
                                <p className="text-xs text-destructive">
                                    {errors.name}
                                </p>
                            )}
                        </form>
                        <p className="mt-2 text-xs text-muted-foreground">
                            Custom permissions appear in the role builder and
                            gate any route/action that references them. Built-in
                            permissions are reserved.
                        </p>
                    </CardContent>
                </Card>

                {groups.map((group) => (
                    <Card key={group.label}>
                        <CardContent className="flex flex-col gap-2 p-4">
                            <h2 className="text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                                {group.label}
                            </h2>
                            <table className="w-full text-sm">
                                <thead className="text-left text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                    <tr className="border-b border-sidebar-border/40 dark:border-sidebar-border/60">
                                        <th className="py-2">Permission</th>
                                        <th className="py-2 text-right">
                                            Roles
                                        </th>
                                        <th className="py-2 text-right">
                                            Users
                                        </th>
                                        <th className="py-2" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {group.permissions.map((perm) => (
                                        <tr
                                            key={perm.name}
                                            className="border-t border-sidebar-border/40 dark:border-sidebar-border/60"
                                        >
                                            <td className="py-2">
                                                <Link
                                                    href={`/admin/permissions/${perm.name}`}
                                                    className="font-mono text-xs hover:underline"
                                                >
                                                    {perm.name}
                                                </Link>
                                                {perm.is_custom && (
                                                    <span className="ml-2 rounded-full bg-sky-100 px-1.5 py-0.5 text-[10px] font-medium text-sky-900 dark:bg-sky-900/40 dark:text-sky-100">
                                                        custom
                                                    </span>
                                                )}
                                            </td>
                                            <td className="py-2 text-right tabular-nums">
                                                {perm.role_count}
                                            </td>
                                            <td className="py-2 text-right tabular-nums">
                                                {perm.user_count}
                                            </td>
                                            <td className="py-2 text-right">
                                                {perm.is_custom && (
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() =>
                                                            remove(perm)
                                                        }
                                                    >
                                                        Delete
                                                    </Button>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>
                ))}
            </div>
        </>
    );
}

PermissionsIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin/users' },
        { title: 'Permissions', href: '/admin/permissions' },
    ],
};
