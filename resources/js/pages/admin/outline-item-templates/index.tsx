import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type DepartmentOption = { value: string; label: string };

type Template = {
    id: number;
    label: string;
    department: string | null;
    default_duration_minutes: number;
    description: string | null;
    checklist: string[];
    is_active: boolean;
    is_system: boolean;
};

type Props = { templates: Template[]; departments: DepartmentOption[] };

const selectClass =
    'rounded-md border border-input bg-background px-2 py-1.5 text-sm';

function linesToArray(text: string): string[] {
    return text
        .split('\n')
        .map((l) => l.trim())
        .filter((l) => l !== '');
}

export default function OutlineItemTemplatesIndex({
    templates,
    departments,
}: Props) {
    return (
        <>
            <Head title="Run-of-show templates · Admin" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <header className="flex flex-col gap-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Run-of-show templates
                    </h1>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        Reusable run-of-show items - a repeatable activity (e.g.
                        an A/V sound check) with a default duration, department,
                        description, and checklist. Staff drop them onto a
                        booking's run-of-show from the{' '}
                        <strong>From template</strong> picker. Descriptions
                        support Markdown. System defaults can be hidden, edited,
                        and reordered but not deleted.
                    </p>
                </header>

                <AddTemplateForm departments={departments} />

                <div className="flex flex-col gap-3">
                    {templates.map((t) => (
                        <TemplateRow
                            key={t.id}
                            template={t}
                            departments={departments}
                        />
                    ))}
                </div>
            </div>
        </>
    );
}

function AddTemplateForm({ departments }: { departments: DepartmentOption[] }) {
    const [form, setForm] = useState({
        label: '',
        department: departments[0]?.value ?? '',
        default_duration_minutes: 30,
        description: '',
        checklistText: '',
    });
    const [saving, setSaving] = useState(false);

    const submit = () => {
        if (!form.label.trim()) {
            return;
        }

        setSaving(true);
        router.post(
            '/admin/outline-item-templates',
            {
                label: form.label,
                department: form.department || null,
                default_duration_minutes: form.default_duration_minutes,
                description: form.description || null,
                checklist: linesToArray(form.checklistText),
            },
            {
                preserveScroll: true,
                onSuccess: () =>
                    setForm({
                        label: '',
                        department: departments[0]?.value ?? '',
                        default_duration_minutes: 30,
                        description: '',
                        checklistText: '',
                    }),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <Card>
            <CardContent className="flex flex-col gap-3 p-4">
                <h2 className="text-sm font-semibold">Add a template</h2>
                <div className="grid gap-3 sm:grid-cols-12">
                    <div className="grid gap-1 sm:col-span-5">
                        <Label htmlFor="t-label">Label</Label>
                        <Input
                            id="t-label"
                            value={form.label}
                            onChange={(e) =>
                                setForm({ ...form, label: e.target.value })
                            }
                            placeholder="e.g. A/V sound check"
                            data-tour-id="template-label"
                        />
                    </div>
                    <div className="grid gap-1 sm:col-span-4">
                        <Label htmlFor="t-dept">Department</Label>
                        <select
                            id="t-dept"
                            value={form.department}
                            onChange={(e) =>
                                setForm({ ...form, department: e.target.value })
                            }
                            className={selectClass}
                        >
                            {departments.map((d) => (
                                <option key={d.value} value={d.value}>
                                    {d.label}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="grid gap-1 sm:col-span-3">
                        <Label htmlFor="t-dur">Duration (min)</Label>
                        <Input
                            id="t-dur"
                            type="number"
                            min={5}
                            max={1440}
                            value={form.default_duration_minutes}
                            onChange={(e) =>
                                setForm({
                                    ...form,
                                    default_duration_minutes: Number(
                                        e.target.value,
                                    ),
                                })
                            }
                        />
                    </div>
                    <div className="grid gap-1 sm:col-span-6">
                        <Label htmlFor="t-desc">Description (Markdown)</Label>
                        <textarea
                            id="t-desc"
                            rows={3}
                            value={form.description}
                            onChange={(e) =>
                                setForm({
                                    ...form,
                                    description: e.target.value,
                                })
                            }
                            className="rounded-md border border-input bg-background px-3 py-2 text-sm"
                        />
                    </div>
                    <div className="grid gap-1 sm:col-span-6">
                        <Label htmlFor="t-check">
                            Checklist (one item per line)
                        </Label>
                        <textarea
                            id="t-check"
                            rows={3}
                            value={form.checklistText}
                            onChange={(e) =>
                                setForm({
                                    ...form,
                                    checklistText: e.target.value,
                                })
                            }
                            placeholder={'Mic check\nPlayback test\nSet levels'}
                            className="rounded-md border border-input bg-background px-3 py-2 text-sm"
                            data-tour-id="template-checklist"
                        />
                    </div>
                </div>
                <div>
                    <Button
                        onClick={submit}
                        disabled={saving}
                        data-tour-id="template-add"
                    >
                        Add template
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}

function TemplateRow({
    template,
    departments,
}: {
    template: Template;
    departments: DepartmentOption[];
}) {
    const [form, setForm] = useState({
        label: template.label,
        department: template.department ?? departments[0]?.value ?? '',
        default_duration_minutes: template.default_duration_minutes,
        description: template.description ?? '',
        checklistText: template.checklist.join('\n'),
    });
    const [saving, setSaving] = useState(false);

    const save = () => {
        setSaving(true);
        router.put(
            `/admin/outline-item-templates/${template.id}`,
            {
                label: form.label,
                department: form.department || null,
                default_duration_minutes: form.default_duration_minutes,
                description: form.description || null,
                checklist: linesToArray(form.checklistText),
            },
            { preserveScroll: true, onFinish: () => setSaving(false) },
        );
    };

    const toggle = () =>
        router.patch(
            `/admin/outline-item-templates/${template.id}/toggle`,
            {},
            { preserveScroll: true },
        );

    const destroy = () => {
        if (!window.confirm(`Delete template "${template.label}"?`)) {
            return;
        }

        router.delete(`/admin/outline-item-templates/${template.id}`, {
            preserveScroll: true,
        });
    };

    return (
        <Card className={template.is_active ? '' : 'opacity-60'}>
            <CardContent className="flex flex-col gap-3 p-4">
                <div className="grid gap-3 sm:grid-cols-12">
                    <div className="grid gap-1 sm:col-span-5">
                        <Input
                            value={form.label}
                            onChange={(e) =>
                                setForm({ ...form, label: e.target.value })
                            }
                        />
                    </div>
                    <div className="grid gap-1 sm:col-span-4">
                        <select
                            value={form.department}
                            onChange={(e) =>
                                setForm({ ...form, department: e.target.value })
                            }
                            className={selectClass}
                        >
                            {departments.map((d) => (
                                <option key={d.value} value={d.value}>
                                    {d.label}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="grid gap-1 sm:col-span-3">
                        <Input
                            type="number"
                            min={5}
                            max={1440}
                            value={form.default_duration_minutes}
                            onChange={(e) =>
                                setForm({
                                    ...form,
                                    default_duration_minutes: Number(
                                        e.target.value,
                                    ),
                                })
                            }
                        />
                    </div>
                    <textarea
                        rows={2}
                        value={form.description}
                        onChange={(e) =>
                            setForm({ ...form, description: e.target.value })
                        }
                        placeholder="Description (Markdown)"
                        className="rounded-md border border-input bg-background px-3 py-2 text-sm sm:col-span-6"
                    />
                    <textarea
                        rows={2}
                        value={form.checklistText}
                        onChange={(e) =>
                            setForm({ ...form, checklistText: e.target.value })
                        }
                        placeholder="Checklist (one item per line)"
                        className="rounded-md border border-input bg-background px-3 py-2 text-sm sm:col-span-6"
                    />
                </div>
                <div className="flex items-center gap-3">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={save}
                        disabled={saving}
                    >
                        Save
                    </Button>
                    <button
                        type="button"
                        onClick={toggle}
                        className="text-xs text-muted-foreground hover:text-foreground"
                    >
                        {template.is_active ? 'Hide' : 'Show'}
                    </button>
                    {template.is_system ? (
                        <span className="rounded-full bg-muted px-2 py-0.5 text-[10px] font-medium text-muted-foreground">
                            system
                        </span>
                    ) : (
                        <button
                            type="button"
                            onClick={destroy}
                            className="ml-auto text-xs text-rose-600 hover:underline dark:text-rose-400"
                        >
                            Delete
                        </button>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}

OutlineItemTemplatesIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '#' },
        {
            title: 'Run-of-show templates',
            href: '/admin/outline-item-templates',
        },
    ],
};
