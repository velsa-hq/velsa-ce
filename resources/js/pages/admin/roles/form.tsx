import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Lock } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';

type Permission = { name: string; action: string };
type Group = { key: string; label: string; permissions: Permission[] };

type Props = {
    mode: 'create' | 'edit';
    role: {
        id: number | null;
        name: string;
        permissions: string[];
        is_built_in: boolean;
        user_count: number;
    };
    permission_groups: Group[];
};

export default function RoleForm({ mode, role, permission_groups }: Props) {
    const isEdit = mode === 'edit';
    const isLocked = isEdit && role.is_built_in;

    const { data, setData, post, put, processing, errors } = useForm({
        name: role.name,
        permissions: role.permissions,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        if (isLocked) {
            return;
        }

        if (isEdit && role.id) {
            put(`/admin/roles/${role.id}`, { preserveScroll: true });
        } else {
            post('/admin/roles');
        }
    };

    const togglePermission = (name: string, checked: boolean) => {
        if (isLocked) {
            return;
        }

        setData(
            'permissions',
            checked
                ? Array.from(new Set([...data.permissions, name]))
                : data.permissions.filter((p) => p !== name),
        );
    };

    const selectAllInGroup = (group: Group) => {
        if (isLocked) {
            return;
        }

        const names = group.permissions.map((p) => p.name);
        setData(
            'permissions',
            Array.from(new Set([...data.permissions, ...names])),
        );
    };

    const clearAllInGroup = (group: Group) => {
        if (isLocked) {
            return;
        }

        const groupSet = new Set(group.permissions.map((p) => p.name));
        setData(
            'permissions',
            data.permissions.filter((p) => !groupSet.has(p)),
        );
    };

    const deleteRole = () => {
        if (!role.id || isLocked) {
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
            <Head title={isEdit ? `${role.name} · Roles` : 'New role'} />

            <form
                onSubmit={submit}
                className="flex h-full flex-1 flex-col gap-4 p-4"
            >
                <header className="flex items-start justify-between gap-3">
                    <div className="flex flex-col gap-1">
                        <Link
                            href="/admin/roles"
                            className="inline-flex w-fit items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                        >
                            <ArrowLeft className="size-3" />
                            All roles
                        </Link>
                        <h1 className="flex items-center gap-2 text-2xl font-semibold tracking-tight">
                            {isEdit ? role.name : 'New role'}
                            {isLocked && (
                                <span className="inline-flex items-center gap-1 rounded-full bg-neutral-100 px-2 py-0.5 text-xs font-medium tracking-wider text-neutral-700 uppercase dark:bg-neutral-800 dark:text-neutral-200">
                                    <Lock className="size-3" />
                                    Built-in
                                </span>
                            )}
                        </h1>
                        {isLocked && (
                            <p className="text-sm text-muted-foreground">
                                This is one of the 11 built-in roles. Its name
                                and permissions are locked. To customize, create
                                a new role instead.
                            </p>
                        )}
                        {isEdit && !isLocked && (
                            <p className="text-sm text-muted-foreground">
                                Assigned to {role.user_count} user
                                {role.user_count === 1 ? '' : 's'}.
                            </p>
                        )}
                    </div>
                    <div className="flex items-center gap-2">
                        {isEdit && !isLocked && (
                            <Button
                                type="button"
                                size="sm"
                                variant="destructive"
                                onClick={deleteRole}
                                disabled={role.user_count > 0}
                            >
                                Delete role
                            </Button>
                        )}
                        {!isLocked && (
                            <Button
                                type="submit"
                                size="sm"
                                disabled={processing}
                            >
                                {isEdit ? 'Save changes' : 'Create role'}
                            </Button>
                        )}
                    </div>
                </header>

                <Card>
                    <CardContent className="flex flex-col gap-3 p-4">
                        <h2 className="text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                            Identity
                        </h2>
                        <label className="flex flex-col gap-1 text-xs font-medium">
                            Name
                            <Input
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                placeholder="e.g. venue_finance_admin"
                                disabled={isLocked}
                                pattern="^[a-z][a-z0-9_]*$"
                            />
                            <span className="text-[11px] font-normal text-muted-foreground">
                                Lower-case letters, digits, and underscores.
                                Must start with a letter.
                            </span>
                            {errors.name && (
                                <span className="text-[11px] text-rose-600">
                                    {errors.name}
                                </span>
                            )}
                        </label>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="flex flex-col gap-4 p-4">
                        <div
                            className="flex items-baseline justify-between"
                            data-tour-id="strong-rbac-permissions-groups"
                        >
                            <h2 className="text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                                Permissions
                            </h2>
                            <span className="text-xs text-muted-foreground">
                                {data.permissions.length} selected
                            </span>
                        </div>

                        {permission_groups.map((group) => {
                            const groupNames = group.permissions.map(
                                (p) => p.name,
                            );
                            const selectedInGroup = groupNames.filter((n) =>
                                data.permissions.includes(n),
                            ).length;
                            const allSelected =
                                selectedInGroup === groupNames.length;

                            return (
                                <section
                                    key={group.key}
                                    className="flex flex-col gap-2"
                                >
                                    <div className="flex items-baseline justify-between border-b border-sidebar-border/40 pb-1">
                                        <h3 className="text-xs font-semibold tracking-wider uppercase">
                                            {group.label}
                                        </h3>
                                        <div className="flex items-center gap-2 text-[11px] text-muted-foreground">
                                            <span>
                                                {selectedInGroup}/
                                                {groupNames.length}
                                            </span>
                                            {!isLocked && (
                                                <Button
                                                    type="button"
                                                    variant="link"
                                                    size="sm"
                                                    onClick={() =>
                                                        allSelected
                                                            ? clearAllInGroup(
                                                                  group,
                                                              )
                                                            : selectAllInGroup(
                                                                  group,
                                                              )
                                                    }
                                                    className="h-auto p-0 text-[11px] text-muted-foreground hover:text-foreground"
                                                >
                                                    {allSelected
                                                        ? 'Clear'
                                                        : 'Select all'}
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                    <div className="grid grid-cols-1 gap-1 sm:grid-cols-2 md:grid-cols-3">
                                        {group.permissions.map((perm) => (
                                            <label
                                                key={perm.name}
                                                className="flex items-center gap-2 rounded-md px-2 py-1 text-sm hover:bg-muted"
                                            >
                                                <Checkbox
                                                    checked={data.permissions.includes(
                                                        perm.name,
                                                    )}
                                                    onCheckedChange={(c) =>
                                                        togglePermission(
                                                            perm.name,
                                                            Boolean(c),
                                                        )
                                                    }
                                                    disabled={isLocked}
                                                />
                                                <span className="flex flex-col leading-tight">
                                                    <span className="font-mono text-xs">
                                                        {perm.name}
                                                    </span>
                                                </span>
                                            </label>
                                        ))}
                                    </div>
                                </section>
                            );
                        })}

                        {errors.permissions && (
                            <span className="text-[11px] text-rose-600">
                                {errors.permissions}
                            </span>
                        )}
                    </CardContent>
                </Card>
            </form>
        </>
    );
}

RoleForm.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin/users' },
        { title: 'Roles', href: '/admin/roles' },
    ],
};
