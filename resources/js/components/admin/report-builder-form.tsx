import type { RequestPayload } from '@inertiajs/core';
import { Link, router } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type CatalogField = {
    key: string;
    label: string;
    type: string;
    options: { value: string; label: string }[];
    aggregatable: boolean;
    operators: { value: string; label: string }[];
};

export type CatalogEntry = {
    value: string;
    label: string;
    fields: CatalogField[];
    aggregations: { value: string; label: string }[];
};

export type Filter = { field: string; operator: string; value: string };
export type Metric = { field: string; aggregation: string; label?: string };
export type Sort = { field: string; direction: 'asc' | 'desc' };

export type Definition = {
    id: number | null;
    slug: string;
    name: string;
    description: string | null;
    datasource: string;
    filters_json: Filter[];
    dimensions_json: string[];
    metrics_json: Metric[];
    sort_json: Sort[];
    row_limit: number;
};

type Props = {
    initial: Definition;
    catalog: CatalogEntry[];
    submitUrl: string;
    submitMethod: 'post' | 'put';
    submitLabel: string;
};

export function ReportBuilderForm({
    initial,
    catalog,
    submitUrl,
    submitMethod,
    submitLabel,
}: Props) {
    const [draft, setDraft] = useState<Definition>(initial);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const current = useMemo(
        () => catalog.find((c) => c.value === draft.datasource) ?? catalog[0],
        [catalog, draft.datasource],
    );

    const fieldByKey = useMemo(() => {
        const m: Record<string, CatalogField> = {};
        current?.fields.forEach((f) => {
            m[f.key] = f;
        });

        return m;
    }, [current]);

    const setDS = (value: string) => {
        // Switching datasource invalidates everything keyed by field.
        setDraft({
            ...draft,
            datasource: value,
            filters_json: [],
            dimensions_json: [],
            metrics_json: [],
            sort_json: [],
        });
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});
        const opts = {
            preserveScroll: true,
            onError: (errs: Record<string, string>) => setErrors(errs),
            onFinish: () => setProcessing(false),
        };
        const payload = draft as unknown as RequestPayload;

        if (submitMethod === 'put') {
            router.put(submitUrl, payload, opts);
        } else {
            router.post(submitUrl, payload, opts);
        }
    };

    return (
        <form onSubmit={submit} className="flex flex-col gap-6" noValidate>
            <section className="rounded-xl border border-border bg-card p-4">
                <h2 className="mb-3 text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                    Basics
                </h2>
                <div className="grid gap-3 sm:grid-cols-2">
                    <div className="grid gap-1 sm:col-span-2">
                        <Label htmlFor="name">Name</Label>
                        <Input
                            id="name"
                            required
                            value={draft.name}
                            onChange={(e) =>
                                setDraft({ ...draft, name: e.target.value })
                            }
                        />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-1 sm:col-span-2">
                        <Label htmlFor="description">Description</Label>
                        <textarea
                            id="description"
                            rows={2}
                            value={draft.description ?? ''}
                            onChange={(e) =>
                                setDraft({
                                    ...draft,
                                    description: e.target.value || null,
                                })
                            }
                            className="rounded-md border border-border bg-background px-3 py-2 text-sm"
                        />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="datasource">Datasource</Label>
                        <select
                            id="datasource"
                            data-tour-id="rb-datasource"
                            value={draft.datasource}
                            onChange={(e) => setDS(e.target.value)}
                            className="rounded-md border border-border bg-background px-3 py-2 text-sm"
                        >
                            {catalog.map((c) => (
                                <option key={c.value} value={c.value}>
                                    {c.label}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="row_limit">Row limit</Label>
                        <Input
                            id="row_limit"
                            type="number"
                            min={1}
                            max={10000}
                            value={draft.row_limit}
                            onChange={(e) =>
                                setDraft({
                                    ...draft,
                                    row_limit: Number(e.target.value),
                                })
                            }
                        />
                    </div>
                </div>
            </section>

            {/* Filters */}
            <section className="rounded-xl border border-border bg-card p-4">
                <div className="mb-3 flex items-center justify-between">
                    <h2 className="text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                        Filters
                    </h2>
                    <Button
                        type="button"
                        data-tour-id="rb-add-filter"
                        variant="outline"
                        size="sm"
                        onClick={() =>
                            setDraft({
                                ...draft,
                                filters_json: [
                                    ...draft.filters_json,
                                    {
                                        field: current?.fields[0]?.key ?? '',
                                        operator: '=',
                                        value: '',
                                    },
                                ],
                            })
                        }
                    >
                        <Plus className="size-4" /> Add filter
                    </Button>
                </div>
                {draft.filters_json.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        No filters - every row is included.
                    </p>
                )}
                <div className="flex flex-col gap-2">
                    {draft.filters_json.map((f, i) => {
                        const field = fieldByKey[f.field];

                        return (
                            <div
                                key={i}
                                className="grid gap-2 sm:grid-cols-[2fr_1fr_2fr_auto]"
                            >
                                <select
                                    value={f.field}
                                    onChange={(e) => {
                                        const nf = [...draft.filters_json];
                                        nf[i] = {
                                            ...nf[i],
                                            field: e.target.value,
                                        };
                                        setDraft({
                                            ...draft,
                                            filters_json: nf,
                                        });
                                    }}
                                    className="rounded-md border border-border bg-background px-2 py-1 text-sm"
                                >
                                    {current?.fields.map((cf) => (
                                        <option key={cf.key} value={cf.key}>
                                            {cf.label}
                                        </option>
                                    ))}
                                </select>
                                <select
                                    value={f.operator}
                                    onChange={(e) => {
                                        const nf = [...draft.filters_json];
                                        nf[i] = {
                                            ...nf[i],
                                            operator: e.target.value,
                                        };
                                        setDraft({
                                            ...draft,
                                            filters_json: nf,
                                        });
                                    }}
                                    className="rounded-md border border-border bg-background px-2 py-1 text-sm"
                                >
                                    {(field?.operators ?? []).map((op) => (
                                        <option key={op.value} value={op.value}>
                                            {op.label}
                                        </option>
                                    ))}
                                </select>
                                {field?.type === 'enum' ? (
                                    <select
                                        value={f.value}
                                        onChange={(e) => {
                                            const nf = [...draft.filters_json];
                                            nf[i] = {
                                                ...nf[i],
                                                value: e.target.value,
                                            };
                                            setDraft({
                                                ...draft,
                                                filters_json: nf,
                                            });
                                        }}
                                        className="rounded-md border border-border bg-background px-2 py-1 text-sm"
                                    >
                                        {field.options.map((opt) => (
                                            <option
                                                key={opt.value}
                                                value={opt.value}
                                            >
                                                {opt.label}
                                            </option>
                                        ))}
                                    </select>
                                ) : (
                                    <input
                                        type={
                                            field?.type === 'date'
                                                ? 'date'
                                                : field?.type === 'number' ||
                                                    field?.type === 'money'
                                                  ? 'number'
                                                  : 'text'
                                        }
                                        value={f.value}
                                        onChange={(e) => {
                                            const nf = [...draft.filters_json];
                                            nf[i] = {
                                                ...nf[i],
                                                value: e.target.value,
                                            };
                                            setDraft({
                                                ...draft,
                                                filters_json: nf,
                                            });
                                        }}
                                        placeholder={
                                            ['is_null', 'is_not_null'].includes(
                                                f.operator,
                                            )
                                                ? '(n/a)'
                                                : 'Value'
                                        }
                                        disabled={[
                                            'is_null',
                                            'is_not_null',
                                        ].includes(f.operator)}
                                        className="rounded-md border border-border bg-background px-2 py-1 text-sm"
                                    />
                                )}
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => {
                                        const nf = draft.filters_json.filter(
                                            (_, ix) => ix !== i,
                                        );
                                        setDraft({
                                            ...draft,
                                            filters_json: nf,
                                        });
                                    }}
                                    aria-label="Remove filter"
                                >
                                    <Trash2 className="size-4" />
                                </Button>
                            </div>
                        );
                    })}
                </div>
            </section>

            {/* Dimensions + Metrics */}
            <section className="grid gap-4 lg:grid-cols-2">
                <div
                    data-tour-id="rb-dimensions"
                    className="rounded-xl border border-border bg-card p-4"
                >
                    <h2 className="mb-3 text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                        Group by (dimensions)
                    </h2>
                    <p className="mb-2 text-xs text-muted-foreground">
                        Adding dimensions switches the report from a flat row
                        dump to a grouped aggregate. Empty = flat.
                    </p>
                    <div className="flex flex-wrap gap-2">
                        {current?.fields.map((f) => {
                            const active = draft.dimensions_json.includes(
                                f.key,
                            );

                            return (
                                <Button
                                    type="button"
                                    key={f.key}
                                    variant={active ? 'default' : 'outline'}
                                    size="sm"
                                    onClick={() => {
                                        const nd = active
                                            ? draft.dimensions_json.filter(
                                                  (k) => k !== f.key,
                                              )
                                            : [...draft.dimensions_json, f.key];
                                        setDraft({
                                            ...draft,
                                            dimensions_json: nd,
                                        });
                                    }}
                                    className="rounded-full"
                                >
                                    {f.label}
                                </Button>
                            );
                        })}
                    </div>
                </div>

                <div className="rounded-xl border border-border bg-card p-4">
                    <div className="mb-3 flex items-center justify-between">
                        <h2 className="text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                            Metrics
                        </h2>
                        <Button
                            type="button"
                            data-tour-id="rb-add-metric"
                            variant="outline"
                            size="sm"
                            onClick={() =>
                                setDraft({
                                    ...draft,
                                    metrics_json: [
                                        ...draft.metrics_json,
                                        {
                                            field:
                                                current?.fields.find(
                                                    (f) => f.aggregatable,
                                                )?.key ?? '',
                                            aggregation: 'sum',
                                            label: '',
                                        },
                                    ],
                                })
                            }
                        >
                            <Plus className="size-4" /> Add metric
                        </Button>
                    </div>
                    {draft.metrics_json.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            No metrics. With no metrics and no dimensions the
                            report just lists rows.
                        </p>
                    )}
                    <div className="flex flex-col gap-2">
                        {draft.metrics_json.map((m, i) => (
                            <div
                                key={i}
                                className="grid gap-2 sm:grid-cols-[2fr_1fr_2fr_auto]"
                            >
                                <select
                                    value={m.field}
                                    onChange={(e) => {
                                        const nm = [...draft.metrics_json];
                                        nm[i] = {
                                            ...nm[i],
                                            field: e.target.value,
                                        };
                                        setDraft({
                                            ...draft,
                                            metrics_json: nm,
                                        });
                                    }}
                                    className="rounded-md border border-border bg-background px-2 py-1 text-sm"
                                >
                                    <option value="">(any - for count)</option>
                                    {current?.fields
                                        .filter((f) => f.aggregatable)
                                        .map((f) => (
                                            <option key={f.key} value={f.key}>
                                                {f.label}
                                            </option>
                                        ))}
                                </select>
                                <select
                                    value={m.aggregation}
                                    onChange={(e) => {
                                        const nm = [...draft.metrics_json];
                                        nm[i] = {
                                            ...nm[i],
                                            aggregation: e.target.value,
                                        };
                                        setDraft({
                                            ...draft,
                                            metrics_json: nm,
                                        });
                                    }}
                                    className="rounded-md border border-border bg-background px-2 py-1 text-sm"
                                >
                                    {(current?.aggregations ?? []).map((a) => (
                                        <option key={a.value} value={a.value}>
                                            {a.label}
                                        </option>
                                    ))}
                                </select>
                                <input
                                    type="text"
                                    value={m.label ?? ''}
                                    onChange={(e) => {
                                        const nm = [...draft.metrics_json];
                                        nm[i] = {
                                            ...nm[i],
                                            label: e.target.value,
                                        };
                                        setDraft({
                                            ...draft,
                                            metrics_json: nm,
                                        });
                                    }}
                                    placeholder="Column label (optional)"
                                    className="rounded-md border border-border bg-background px-2 py-1 text-sm"
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => {
                                        const nm = draft.metrics_json.filter(
                                            (_, ix) => ix !== i,
                                        );
                                        setDraft({
                                            ...draft,
                                            metrics_json: nm,
                                        });
                                    }}
                                    aria-label="Remove metric"
                                >
                                    <Trash2 className="size-4" />
                                </Button>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            <div className="flex items-center gap-3">
                <Button
                    type="submit"
                    data-tour-id="rb-save"
                    disabled={processing}
                >
                    {submitLabel}
                </Button>
                <Button asChild variant="outline">
                    <Link href="/admin/report-builder">Cancel</Link>
                </Button>
                {draft.id && (
                    <Badge variant="outline" className="ml-auto">
                        {draft.slug}
                    </Badge>
                )}
            </div>
        </form>
    );
}
