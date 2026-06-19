import type { RequestPayload } from '@inertiajs/core';
import { Link, router } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Plus, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    index,
    preview as previewRoute,
} from '@/routes/admin/export-templates';

export type ColumnDraft = {
    id?: number | null;
    label: string;
    source: string;
    format_mask: string | null;
    default_value: string | null;
    width: number | null;
    align: 'left' | 'right';
    pad_char: string;
};

export type TemplateDraft = {
    id: number | null;
    slug: string;
    name: string;
    description: string | null;
    format: string;
    delimiter: string;
    quote_char: string;
    line_ending: 'lf' | 'crlf';
    encoding: string;
    include_header: boolean;
    include_footer: boolean;
    is_default: boolean;
    file_extension: string;
    columns: ColumnDraft[];
};

export type Metadata = {
    formats: { value: string; label: string }[];
    sources: { value: string; label: string; group: string }[];
    format_masks: { value: string; label: string }[];
    line_endings: { value: string; label: string }[];
};

type Props = {
    initial: TemplateDraft;
    metadata: Metadata;
    initialPreview?: string;
    submitUrl: string;
    submitMethod: 'post' | 'put';
    submitLabel: string;
};

function emptyColumn(): ColumnDraft {
    return {
        label: '',
        source: 'account_code',
        format_mask: null,
        default_value: null,
        width: null,
        align: 'left',
        pad_char: ' ',
    };
}

export function ExportTemplateForm({
    initial,
    metadata,
    initialPreview = '',
    submitUrl,
    submitMethod,
    submitLabel,
}: Props) {
    const [draft, setDraft] = useState<TemplateDraft>(initial);
    const [preview, setPreview] = useState<string>(initialPreview);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const isFixedWidth = draft.format === 'fixed_width';

    // Group sources by their `group` field for the <optgroup> UX.
    const sourcesByGroup = useMemo(() => {
        const groups: Record<string, typeof metadata.sources> = {};

        for (const src of metadata.sources) {
            (groups[src.group] ??= []).push(src);
        }

        return groups;
    }, [metadata.sources]);

    // Debounced live preview - POSTs the in-memory draft to the
    // preview endpoint whenever the form changes.
    useEffect(() => {
        const handle = setTimeout(() => {
            void fetch(previewRoute().url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN':
                        document
                            .querySelector('meta[name="csrf-token"]')
                            ?.getAttribute('content') ?? '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ template: draft }),
            })
                .then((r) => r.json())
                .then((res) => {
                    if (typeof res?.payload === 'string') {
                        setPreview(res.payload);
                    }
                })
                .catch(() => {
                    /* preview is non-critical */
                });
        }, 350);

        return () => clearTimeout(handle);
    }, [draft]);

    const update = <K extends keyof TemplateDraft>(
        key: K,
        value: TemplateDraft[K],
    ) => {
        setDraft((d) => ({ ...d, [key]: value }));
    };

    const updateColumn = (idx: number, patch: Partial<ColumnDraft>) => {
        setDraft((d) => ({
            ...d,
            columns: d.columns.map((c, i) =>
                i === idx ? { ...c, ...patch } : c,
            ),
        }));
    };

    const addColumn = () => {
        setDraft((d) => ({ ...d, columns: [...d.columns, emptyColumn()] }));
    };

    const removeColumn = (idx: number) => {
        setDraft((d) => ({
            ...d,
            columns: d.columns.filter((_, i) => i !== idx),
        }));
    };

    const moveColumn = (idx: number, dir: -1 | 1) => {
        const target = idx + dir;

        if (target < 0 || target >= draft.columns.length) {
            return;
        }

        setDraft((d) => {
            const next = [...d.columns];
            [next[idx], next[target]] = [next[target], next[idx]];

            return { ...d, columns: next };
        });
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        const opts = {
            preserveScroll: true,
            onError: (errs: Record<string, string>) => setErrors(errs),
            onFinish: () => setProcessing(false),
        };

        if (submitMethod === 'put') {
            router.put(submitUrl, draft as unknown as RequestPayload, opts);
        } else {
            router.post(submitUrl, draft as unknown as RequestPayload, opts);
        }
    };

    return (
        <form
            onSubmit={handleSubmit}
            className="flex flex-1 flex-col gap-6"
            noValidate
        >
            <div className="grid gap-6 lg:grid-cols-[1fr_minmax(0,420px)]">
                {/* Left: form */}
                <div className="flex flex-col gap-6">
                    <section className="rounded-xl border border-border bg-card p-4">
                        <h2 className="mb-3 text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                            Template
                        </h2>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    type="text"
                                    required
                                    value={draft.name}
                                    onChange={(e) =>
                                        update('name', e.target.value)
                                    }
                                    placeholder="General Ledger CSV"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="description">Description</Label>
                                <textarea
                                    id="description"
                                    rows={2}
                                    value={draft.description ?? ''}
                                    onChange={(e) =>
                                        update(
                                            'description',
                                            e.target.value || null,
                                        )
                                    }
                                    className="rounded-md border border-border bg-background px-3 py-2 text-sm"
                                    placeholder="What this template is used for..."
                                />
                                <InputError message={errors.description} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="format">Format</Label>
                                <select
                                    id="format"
                                    value={draft.format}
                                    onChange={(e) =>
                                        update('format', e.target.value)
                                    }
                                    className="rounded-md border border-border bg-background px-3 py-2 text-sm"
                                >
                                    {metadata.formats.map((f) => (
                                        <option key={f.value} value={f.value}>
                                            {f.label}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.format} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="file_extension">
                                    File extension
                                </Label>
                                <Input
                                    id="file_extension"
                                    type="text"
                                    required
                                    maxLength={8}
                                    value={draft.file_extension}
                                    onChange={(e) =>
                                        update('file_extension', e.target.value)
                                    }
                                    placeholder="csv"
                                />
                                <InputError message={errors.file_extension} />
                            </div>

                            {!isFixedWidth && (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="delimiter">
                                            Delimiter
                                        </Label>
                                        <Input
                                            id="delimiter"
                                            type="text"
                                            maxLength={4}
                                            value={draft.delimiter}
                                            onChange={(e) =>
                                                update(
                                                    'delimiter',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={errors.delimiter}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="quote_char">
                                            Quote char
                                        </Label>
                                        <Input
                                            id="quote_char"
                                            type="text"
                                            maxLength={2}
                                            value={draft.quote_char}
                                            onChange={(e) =>
                                                update(
                                                    'quote_char',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={errors.quote_char}
                                        />
                                    </div>
                                </>
                            )}

                            <div className="grid gap-2">
                                <Label htmlFor="line_ending">Line ending</Label>
                                <select
                                    id="line_ending"
                                    value={draft.line_ending}
                                    onChange={(e) =>
                                        update(
                                            'line_ending',
                                            e.target.value as 'lf' | 'crlf',
                                        )
                                    }
                                    className="rounded-md border border-border bg-background px-3 py-2 text-sm"
                                >
                                    {metadata.line_endings.map((le) => (
                                        <option key={le.value} value={le.value}>
                                            {le.label}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.line_ending} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="encoding">Encoding</Label>
                                <Input
                                    id="encoding"
                                    type="text"
                                    value={draft.encoding}
                                    onChange={(e) =>
                                        update('encoding', e.target.value)
                                    }
                                />
                                <InputError message={errors.encoding} />
                            </div>

                            <div className="flex items-center gap-6 sm:col-span-2">
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={draft.include_header}
                                        onChange={(e) =>
                                            update(
                                                'include_header',
                                                e.target.checked,
                                            )
                                        }
                                    />
                                    Include header row
                                </label>
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={draft.include_footer}
                                        onChange={(e) =>
                                            update(
                                                'include_footer',
                                                e.target.checked,
                                            )
                                        }
                                    />
                                    Include footer (batch totals)
                                </label>
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={draft.is_default}
                                        onChange={(e) =>
                                            update(
                                                'is_default',
                                                e.target.checked,
                                            )
                                        }
                                    />
                                    Default template
                                </label>
                            </div>
                        </div>
                    </section>

                    <section className="rounded-xl border border-border bg-card p-4">
                        <div className="mb-3 flex items-center justify-between">
                            <h2 className="text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                                Columns
                            </h2>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={addColumn}
                            >
                                <Plus className="size-4" /> Add column
                            </Button>
                        </div>

                        {draft.columns.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                No columns yet. Add at least one.
                            </p>
                        )}

                        <div className="flex flex-col gap-3">
                            {draft.columns.map((col, idx) => (
                                <div
                                    key={idx}
                                    className="rounded-lg border border-border/70 bg-background p-3"
                                >
                                    <div className="mb-2 flex items-center justify-between">
                                        <Badge variant="outline">
                                            #{idx + 1}
                                        </Badge>
                                        <div className="flex items-center gap-1">
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="ghost"
                                                onClick={() =>
                                                    moveColumn(idx, -1)
                                                }
                                                disabled={idx === 0}
                                                aria-label="Move up"
                                            >
                                                <ArrowUp className="size-4" />
                                            </Button>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="ghost"
                                                onClick={() =>
                                                    moveColumn(idx, 1)
                                                }
                                                disabled={
                                                    idx ===
                                                    draft.columns.length - 1
                                                }
                                                aria-label="Move down"
                                            >
                                                <ArrowDown className="size-4" />
                                            </Button>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="ghost"
                                                onClick={() =>
                                                    removeColumn(idx)
                                                }
                                                aria-label="Remove column"
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </div>
                                    </div>

                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <div className="grid gap-1">
                                            <Label htmlFor={`col-${idx}-label`}>
                                                Label
                                            </Label>
                                            <Input
                                                id={`col-${idx}-label`}
                                                type="text"
                                                required
                                                value={col.label}
                                                onChange={(e) =>
                                                    updateColumn(idx, {
                                                        label: e.target.value,
                                                    })
                                                }
                                            />
                                            <InputError
                                                message={
                                                    errors[
                                                        `columns.${idx}.label`
                                                    ]
                                                }
                                            />
                                        </div>

                                        <div className="grid gap-1">
                                            <Label
                                                htmlFor={`col-${idx}-source`}
                                            >
                                                Source
                                            </Label>
                                            <select
                                                id={`col-${idx}-source`}
                                                value={col.source}
                                                onChange={(e) =>
                                                    updateColumn(idx, {
                                                        source: e.target.value,
                                                    })
                                                }
                                                className="rounded-md border border-border bg-background px-3 py-2 text-sm"
                                            >
                                                {Object.entries(
                                                    sourcesByGroup,
                                                ).map(([group, srcs]) => (
                                                    <optgroup
                                                        key={group}
                                                        label={group}
                                                    >
                                                        {srcs.map((s) => (
                                                            <option
                                                                key={s.value}
                                                                value={s.value}
                                                            >
                                                                {s.label}
                                                            </option>
                                                        ))}
                                                    </optgroup>
                                                ))}
                                            </select>
                                        </div>

                                        <div className="grid gap-1">
                                            <Label htmlFor={`col-${idx}-mask`}>
                                                Format mask{' '}
                                                <span className="text-muted-foreground">
                                                    (optional)
                                                </span>
                                            </Label>
                                            <Input
                                                id={`col-${idx}-mask`}
                                                type="text"
                                                list={`masks-${idx}`}
                                                value={col.format_mask ?? ''}
                                                onChange={(e) =>
                                                    updateColumn(idx, {
                                                        format_mask:
                                                            e.target.value ||
                                                            null,
                                                    })
                                                }
                                                placeholder="e.g. date:Y-m-d or money:dot"
                                            />
                                            <datalist id={`masks-${idx}`}>
                                                {metadata.format_masks.map(
                                                    (m) => (
                                                        <option
                                                            key={m.value}
                                                            value={m.value}
                                                        >
                                                            {m.label}
                                                        </option>
                                                    ),
                                                )}
                                            </datalist>
                                        </div>

                                        <div className="grid gap-1">
                                            <Label
                                                htmlFor={`col-${idx}-default`}
                                            >
                                                Default if empty{' '}
                                                <span className="text-muted-foreground">
                                                    (optional)
                                                </span>
                                            </Label>
                                            <Input
                                                id={`col-${idx}-default`}
                                                type="text"
                                                value={col.default_value ?? ''}
                                                onChange={(e) =>
                                                    updateColumn(idx, {
                                                        default_value:
                                                            e.target.value ||
                                                            null,
                                                    })
                                                }
                                            />
                                        </div>

                                        {isFixedWidth && (
                                            <>
                                                <div className="grid gap-1">
                                                    <Label
                                                        htmlFor={`col-${idx}-width`}
                                                    >
                                                        Width
                                                    </Label>
                                                    <Input
                                                        id={`col-${idx}-width`}
                                                        type="number"
                                                        min={1}
                                                        max={500}
                                                        value={col.width ?? ''}
                                                        onChange={(e) =>
                                                            updateColumn(idx, {
                                                                width: e.target
                                                                    .value
                                                                    ? Number(
                                                                          e
                                                                              .target
                                                                              .value,
                                                                      )
                                                                    : null,
                                                            })
                                                        }
                                                    />
                                                </div>
                                                <div className="grid grid-cols-2 gap-2">
                                                    <div className="grid gap-1">
                                                        <Label
                                                            htmlFor={`col-${idx}-align`}
                                                        >
                                                            Align
                                                        </Label>
                                                        <select
                                                            id={`col-${idx}-align`}
                                                            value={col.align}
                                                            onChange={(e) =>
                                                                updateColumn(
                                                                    idx,
                                                                    {
                                                                        align: e
                                                                            .target
                                                                            .value as
                                                                            | 'left'
                                                                            | 'right',
                                                                    },
                                                                )
                                                            }
                                                            className="rounded-md border border-border bg-background px-3 py-2 text-sm"
                                                        >
                                                            <option value="left">
                                                                Left
                                                            </option>
                                                            <option value="right">
                                                                Right
                                                            </option>
                                                        </select>
                                                    </div>
                                                    <div className="grid gap-1">
                                                        <Label
                                                            htmlFor={`col-${idx}-pad`}
                                                        >
                                                            Pad char
                                                        </Label>
                                                        <Input
                                                            id={`col-${idx}-pad`}
                                                            type="text"
                                                            maxLength={1}
                                                            value={col.pad_char}
                                                            onChange={(e) =>
                                                                updateColumn(
                                                                    idx,
                                                                    {
                                                                        pad_char:
                                                                            e
                                                                                .target
                                                                                .value ||
                                                                            ' ',
                                                                    },
                                                                )
                                                            }
                                                        />
                                                    </div>
                                                </div>
                                            </>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>

                        <InputError message={errors.columns} />
                    </section>

                    <div className="flex items-center gap-3">
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            {submitLabel}
                        </Button>
                        <Link
                            href={index().url}
                            className="text-sm text-muted-foreground hover:text-foreground"
                        >
                            Cancel
                        </Link>
                    </div>
                </div>

                {/* Right: sticky live preview */}
                <aside className="lg:sticky lg:top-4 lg:self-start">
                    <div className="rounded-xl border border-border bg-card p-4">
                        <div className="mb-3 flex items-center justify-between">
                            <h2 className="text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                                Live preview
                            </h2>
                            <Badge variant="outline">2 sample entries</Badge>
                        </div>
                        <pre className="max-h-[60vh] overflow-auto rounded-md bg-muted/40 p-3 font-mono text-xs leading-relaxed whitespace-pre">
                            {preview || '...'}
                        </pre>
                        <p className="mt-2 text-xs text-muted-foreground">
                            Rendered against two synthetic journal entries (one
                            debit, one credit). Updates as you edit.
                        </p>
                    </div>
                </aside>
            </div>
        </form>
    );
}
