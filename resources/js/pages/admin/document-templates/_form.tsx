import { Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';

export type FormState = {
    kind: string;
    venue_id: number | null;
    name: string;
    body_html: string;
    is_active: boolean;
};

export type FormOption = { value: string; label: string };
export type VenueOption = { id: number; name: string };

/**
 * Merge fields shown as an editor sidebar reference. The renderer walks
 * data_get($vars, path) so any nested path works; these are the conventional
 * ones ContractDispatcher passes.
 */
const MERGE_FIELDS: Array<{ token: string; description: string }> = [
    { token: '{{booking.reference}}', description: 'BK-2026-XXXX' },
    { token: '{{booking.name}}', description: 'Event name' },
    { token: '{{booking.start_date}}', description: 'Formatted start' },
    { token: '{{booking.end_date}}', description: 'Formatted end' },
    { token: '{{booking.total}}', description: 'Formatted $' },
    { token: '{{venue.name}}', description: 'Venue name' },
    { token: '{{client.name}}', description: 'Client name' },
];

export function TemplateForm({
    initial,
    kinds,
    venues,
    submitUrl,
    method,
    headline,
    submitLabel,
}: {
    initial: Partial<FormState>;
    kinds: FormOption[];
    venues: VenueOption[];
    submitUrl: string;
    method: 'post' | 'put';
    headline: string;
    submitLabel: string;
}) {
    const [form, setForm] = useState<FormState>({
        kind: initial.kind ?? 'contract',
        venue_id: initial.venue_id ?? null,
        name: initial.name ?? '',
        body_html: initial.body_html ?? '<h1>Heading</h1>\n<p>Body...</p>',
        is_active: initial.is_active ?? true,
    });
    const [showPreview, setShowPreview] = useState(false);

    const previewHtml = useMemo(
        () => renderPreview(form.body_html),
        [form.body_html],
    );

    const submit = () => {
        const payload = {
            kind: form.kind,
            venue_id: form.venue_id ?? undefined,
            name: form.name,
            body_html: form.body_html,
            is_active: form.is_active,
        };

        if (method === 'post') {
            router.post(submitUrl, payload);
        } else {
            router.put(submitUrl, payload);
        }
    };

    return (
        <div className="flex h-full flex-1 flex-col gap-4 p-4">
            <header className="flex flex-wrap items-end justify-between gap-3">
                <h1 className="text-2xl font-semibold tracking-tight">
                    {headline}
                </h1>
                <Link
                    href="/admin/document-templates"
                    className="text-sm text-muted-foreground hover:text-foreground"
                >
                    Back to templates
                </Link>
            </header>

            <div className="grid gap-4 lg:grid-cols-[1fr_280px]">
                <div className="flex flex-col gap-3">
                    <div className="grid gap-3 sm:grid-cols-3">
                        <label className="flex flex-col gap-1 text-xs">
                            <span className="text-muted-foreground">Kind</span>
                            <select
                                value={form.kind}
                                onChange={(e) =>
                                    setForm({ ...form, kind: e.target.value })
                                }
                                data-tour-id="admin-dt-kind-select"
                                className="rounded-md border border-border bg-background px-2 py-1.5 text-sm"
                            >
                                {kinds.map((k) => (
                                    <option key={k.value} value={k.value}>
                                        {k.label}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="flex flex-col gap-1 text-xs">
                            <span className="text-muted-foreground">Scope</span>
                            <select
                                value={form.venue_id ?? ''}
                                onChange={(e) =>
                                    setForm({
                                        ...form,
                                        venue_id: e.target.value
                                            ? Number(e.target.value)
                                            : null,
                                    })
                                }
                                data-tour-id="admin-dt-scope-select"
                                className="rounded-md border border-border bg-background px-2 py-1.5 text-sm"
                            >
                                <option value="">Global (all venues)</option>
                                {venues.map((v) => (
                                    <option key={v.id} value={v.id}>
                                        {v.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="flex flex-col gap-1 text-xs">
                            <span className="text-muted-foreground">Name</span>
                            <input
                                type="text"
                                value={form.name}
                                onChange={(e) =>
                                    setForm({ ...form, name: e.target.value })
                                }
                                placeholder="Standard Event Contract"
                                className="rounded-md border border-border bg-background px-2 py-1.5 text-sm"
                            />
                        </label>
                    </div>

                    <label className="flex items-center gap-2 text-xs">
                        <input
                            type="checkbox"
                            checked={form.is_active}
                            onChange={(e) =>
                                setForm({
                                    ...form,
                                    is_active: e.target.checked,
                                })
                            }
                        />
                        <span>
                            <strong>Active</strong> - eligible for selection
                            when a contract is drafted from a booking
                        </span>
                    </label>

                    <div className="flex items-center justify-between">
                        <span className="text-xs font-medium text-muted-foreground">
                            Body (HTML)
                        </span>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => setShowPreview((v) => !v)}
                            data-tour-id="admin-dt-preview-toggle"
                        >
                            {showPreview ? 'Show source' : 'Preview'}
                        </Button>
                    </div>

                    {showPreview ? (
                        <div
                            className="prose prose-sm dark:prose-invert min-h-[400px] max-w-none rounded-md border border-border bg-background p-4"
                            dangerouslySetInnerHTML={{ __html: previewHtml }}
                        />
                    ) : (
                        <textarea
                            value={form.body_html}
                            onChange={(e) =>
                                setForm({ ...form, body_html: e.target.value })
                            }
                            rows={20}
                            data-tour-id="admin-dt-body-editor"
                            className="rounded-md border border-border bg-background p-3 font-mono text-xs"
                            spellCheck={false}
                        />
                    )}

                    <div className="flex justify-end gap-2">
                        <Button
                            type="button"
                            onClick={submit}
                            disabled={form.name.trim().length === 0}
                            data-tour-id="admin-dt-save"
                        >
                            {submitLabel}
                        </Button>
                    </div>
                </div>

                <aside className="flex flex-col gap-3">
                    <div className="rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border">
                        <h3 className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                            Merge fields
                        </h3>
                        <p className="mt-1 text-[11px] text-muted-foreground">
                            Drop these tokens into the body - the renderer
                            substitutes them at draft time using the booking's
                            actual values.
                        </p>
                        <ul className="mt-2 flex flex-col gap-1.5 text-xs">
                            {MERGE_FIELDS.map((f) => (
                                <li
                                    key={f.token}
                                    className="flex flex-col gap-0.5 rounded-md border border-sidebar-border/40 bg-muted/30 p-2 dark:border-sidebar-border/60"
                                >
                                    <code className="font-mono text-[11px]">
                                        {f.token}
                                    </code>
                                    <span className="text-[10px] text-muted-foreground">
                                        {f.description}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </div>
                </aside>
            </div>
        </div>
    );
}

/**
 * Preview that swaps {{token}} for a stand-in span, no server round-trip.
 * The real renderer substitutes actual booking/venue/client values at draft time.
 */
function renderPreview(bodyHtml: string): string {
    return bodyHtml.replace(
        /\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/g,
        (_, token) =>
            `<span class="rounded-sm bg-amber-100 px-1 text-amber-900 dark:bg-amber-900/40 dark:text-amber-200">${token}</span>`,
    );
}
