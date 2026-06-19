import { Head, router, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { destroy, store, update } from '@/routes/admin/audit-rules';

type Rule = {
    id: number;
    name: string;
    event_type: string;
    description: string | null;
    is_active: boolean;
};

type Props = { rules: Rule[] };

export default function AuditRulesIndex({ rules }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        event_type: '',
        description: '',
        is_active: true as boolean,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(store().url, { preserveScroll: true, onSuccess: () => reset() });
    };

    const toggle = (rule: Rule) => {
        router.put(
            update(rule.id).url,
            {
                name: rule.name,
                event_type: rule.event_type,
                description: rule.description ?? '',
                is_active: !rule.is_active,
            },
            { preserveScroll: true },
        );
    };

    const remove = (rule: Rule) => {
        if (window.confirm(`Remove the audit rule "${rule.name}"?`)) {
            router.delete(destroy(rule.id).url, { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title="Audit rules · Admin" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <header className="flex flex-col gap-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Audit rules
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Define event-type prefixes to flag in the audit log -
                        e.g. <code>role.</code> to flag every privilege change,
                        or <code>user.disabled</code> for account lockouts.
                        Active rules drive the "Flagged only" filter on the
                        audit log.
                    </p>
                </header>

                <Card>
                    <CardContent className="p-4">
                        <form
                            onSubmit={submit}
                            className="grid gap-3 sm:grid-cols-4 sm:items-end"
                        >
                            <div className="grid gap-1">
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    data-tour-id="audit-rules-name"
                                    value={data.name}
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
                                />
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="event_type">
                                    Event type (prefix)
                                </Label>
                                <Input
                                    id="event_type"
                                    data-tour-id="audit-rules-event-type"
                                    placeholder="role."
                                    value={data.event_type}
                                    onChange={(e) =>
                                        setData('event_type', e.target.value)
                                    }
                                />
                            </div>
                            <div className="grid gap-1 sm:col-span-1">
                                <Label htmlFor="description">Description</Label>
                                <Input
                                    id="description"
                                    data-tour-id="audit-rules-description"
                                    value={data.description}
                                    onChange={(e) =>
                                        setData('description', e.target.value)
                                    }
                                />
                            </div>
                            <Button
                                type="submit"
                                data-tour-id="audit-rules-add"
                                disabled={processing}
                            >
                                Add rule
                            </Button>
                        </form>
                        {(errors.name || errors.event_type) && (
                            <p className="mt-2 text-xs text-destructive">
                                {errors.name ?? errors.event_type}
                            </p>
                        )}
                    </CardContent>
                </Card>

                <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium">
                                    Name
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Event type
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Description
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Active
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {rules.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-6 text-center text-muted-foreground"
                                    >
                                        No audit rules yet.
                                    </td>
                                </tr>
                            ) : (
                                rules.map((rule) => (
                                    <tr key={rule.id}>
                                        <td className="px-4 py-2">
                                            {rule.name}
                                        </td>
                                        <td className="px-4 py-2 font-mono text-xs">
                                            {rule.event_type}
                                        </td>
                                        <td className="px-4 py-2 text-muted-foreground">
                                            {rule.description ?? '-'}
                                        </td>
                                        <td className="px-4 py-2">
                                            <button
                                                type="button"
                                                data-tour-id="audit-rules-toggle"
                                                onClick={() => toggle(rule)}
                                                className={`rounded-full px-2 py-0.5 text-xs font-medium ${rule.is_active ? 'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-100' : 'bg-neutral-200 text-neutral-700 dark:bg-neutral-700 dark:text-neutral-200'}`}
                                            >
                                                {rule.is_active
                                                    ? 'Active'
                                                    : 'Inactive'}
                                            </button>
                                        </td>
                                        <td className="px-4 py-2 text-right">
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                data-tour-id="audit-rules-remove"
                                                onClick={() => remove(rule)}
                                            >
                                                Remove
                                            </Button>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

AuditRulesIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin/audit-rules' },
        { title: 'Audit rules', href: '#' },
    ],
};
