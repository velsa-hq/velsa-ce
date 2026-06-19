import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type Venue = { id: number; name: string };

type Props = {
    mode: 'create' | 'edit';
    mapping: {
        id: number | null;
        entra_group_id: string;
        group_label: string | null;
        role_name: string;
        venue_id: number | null;
    };
    roles: string[];
    venues: Venue[];
};

export default function SsoMappingForm({
    mode,
    mapping,
    roles,
    venues,
}: Props) {
    const isEdit = mode === 'edit';

    const { data, setData, post, put, transform, processing, errors } = useForm(
        {
            entra_group_id: mapping.entra_group_id ?? '',
            group_label: mapping.group_label ?? '',
            role_name: mapping.role_name ?? '',
            venue_id: mapping.venue_id !== null ? String(mapping.venue_id) : '',
        },
    );

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        transform((data) => ({
            entra_group_id: data.entra_group_id.trim(),
            group_label: data.group_label.trim() || null,
            role_name: data.role_name,
            venue_id: data.venue_id ? Number(data.venue_id) : null,
        }));

        if (isEdit && mapping.id) {
            put(`/admin/sso-mappings/${mapping.id}`, {
                preserveScroll: true,
                onSuccess: () => router.visit('/admin/sso-mappings'),
            });
        } else {
            post('/admin/sso-mappings');
        }
    };

    const deleteRow = () => {
        if (!mapping.id) {
            return;
        }

        if (!confirm('Delete this mapping?')) {
            return;
        }

        router.delete(`/admin/sso-mappings/${mapping.id}`);
    };

    return (
        <>
            <Head
                title={
                    isEdit
                        ? `${mapping.group_label ?? mapping.entra_group_id}`
                        : 'New SSO mapping'
                }
            />

            <form
                onSubmit={submit}
                className="flex h-full flex-1 flex-col gap-4 p-4"
            >
                <header className="flex items-start justify-between gap-3">
                    <div className="flex flex-col gap-1">
                        <Link
                            href="/admin/sso-mappings"
                            className="inline-flex w-fit items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                        >
                            <ArrowLeft className="size-3" />
                            All mappings
                        </Link>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {isEdit
                                ? (mapping.group_label ??
                                  mapping.entra_group_id)
                                : 'New SSO mapping'}
                        </h1>
                    </div>
                    <div className="flex items-center gap-2">
                        {isEdit && (
                            <Button
                                type="button"
                                size="sm"
                                variant="destructive"
                                onClick={deleteRow}
                            >
                                Delete
                            </Button>
                        )}
                        <Button type="submit" size="sm" disabled={processing}>
                            {isEdit ? 'Save changes' : 'Create mapping'}
                        </Button>
                    </div>
                </header>

                <Card>
                    <CardContent className="flex flex-col gap-3 p-4">
                        <h2 className="text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                            Entra group
                        </h2>
                        <label className="flex flex-col gap-1 text-xs font-medium">
                            Group object ID (GUID)
                            <Input
                                value={data.entra_group_id}
                                onChange={(e) =>
                                    setData('entra_group_id', e.target.value)
                                }
                                placeholder="e.g. 8c7a4d72-9c12-4d83-9f9f-9d31f8c45d11"
                            />
                            <span className="text-[11px] font-normal text-muted-foreground">
                                Find this in the Entra admin centre &gt; Groups
                                &gt; [your group] &gt; Overview &gt; Object ID.
                            </span>
                            {errors.entra_group_id && (
                                <span className="text-[11px] text-rose-600">
                                    {errors.entra_group_id}
                                </span>
                            )}
                        </label>
                        <label className="flex flex-col gap-1 text-xs font-medium">
                            Display label (optional)
                            <Input
                                value={data.group_label}
                                onChange={(e) =>
                                    setData('group_label', e.target.value)
                                }
                                placeholder="e.g. Finance Team"
                            />
                            <span className="text-[11px] font-normal text-muted-foreground">
                                Shown in the mappings list so admins can scan by
                                name rather than GUID. Doesn't have to match the
                                Entra display name exactly.
                            </span>
                            {errors.group_label && (
                                <span className="text-[11px] text-rose-600">
                                    {errors.group_label}
                                </span>
                            )}
                        </label>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="flex flex-col gap-3 p-4">
                        <h2 className="text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                            Role to assign
                        </h2>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <label className="flex flex-col gap-1 text-xs font-medium">
                                Role
                                <select
                                    value={data.role_name}
                                    onChange={(e) =>
                                        setData('role_name', e.target.value)
                                    }
                                    className="rounded-md border border-sidebar-border/70 bg-background px-2 py-1.5 text-sm dark:border-sidebar-border"
                                >
                                    <option value="">- pick a role -</option>
                                    {roles.map((r) => (
                                        <option key={r} value={r}>
                                            {r}
                                        </option>
                                    ))}
                                </select>
                                {errors.role_name && (
                                    <span className="text-[11px] text-rose-600">
                                        {errors.role_name}
                                    </span>
                                )}
                            </label>
                            <label className="flex flex-col gap-1 text-xs font-medium">
                                Venue
                                <select
                                    value={data.venue_id}
                                    onChange={(e) =>
                                        setData('venue_id', e.target.value)
                                    }
                                    className="rounded-md border border-sidebar-border/70 bg-background px-2 py-1.5 text-sm dark:border-sidebar-border"
                                >
                                    <option value="">Every active venue</option>
                                    {venues.map((v) => (
                                        <option key={v.id} value={v.id}>
                                            {v.name}
                                        </option>
                                    ))}
                                </select>
                                {errors.venue_id && (
                                    <span className="text-[11px] text-rose-600">
                                        {errors.venue_id}
                                    </span>
                                )}
                            </label>
                        </div>
                    </CardContent>
                </Card>
            </form>
        </>
    );
}

SsoMappingForm.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin/users' },
        { title: 'SSO mappings', href: '/admin/sso-mappings' },
    ],
};
