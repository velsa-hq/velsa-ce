import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { update as updateSettings } from '@/routes/admin/system-settings';

const KEEP_CURRENT = '__KEEP_CURRENT__';

type Choice = { value: string; label: string };

type Setting = {
    key: string;
    label: string;
    description: string;
    type: 'string' | 'integer' | 'boolean' | 'multiselect' | 'select';
    is_secret: boolean;
    options:
        | (Record<string, unknown> & {
              choices?: Choice[];
              show_when?: { field: string; value: string };
              warn_on_change?: boolean;
          })
        | null;
    has_env_fallback: boolean;
    group: string | null;
    group_label: string | null;
    gates_group: boolean;
};

type Group = {
    key: string;
    label: string;
    settings: Setting[];
};

function groupSettings(settings: Setting[]): Group[] {
    const groups: Map<string, Group> = new Map();

    for (const s of settings) {
        const key = s.group ?? '__default__';
        const label = s.group_label ?? '';

        if (!groups.has(key)) {
            groups.set(key, { key, label, settings: [] });
        }

        groups.get(key)!.settings.push(s);
    }

    return Array.from(groups.values());
}

function isTruthy(v: string | undefined): boolean {
    if (v === undefined) {
        return false;
    }

    return v === '1' || v === 'true';
}

type Category = {
    key: string;
    label: string;
    settings: Setting[];
};

type Props = {
    categories: Category[];
    values: Record<string, string | number | boolean | null>;
};

export default function SystemSettingsIndex({ categories, values }: Props) {
    const initial: Record<string, string> = {};

    for (const cat of categories) {
        for (const s of cat.settings) {
            const v = values[s.key];

            if (s.is_secret && v !== null && v !== '') {
                initial[s.key] = KEEP_CURRENT;
            } else if (v === null || v === undefined) {
                initial[s.key] = '';
            } else {
                initial[s.key] = String(v);
            }
        }
    }

    const { data, setData, put, processing, recentlySuccessful } = useForm<{
        values: Record<string, string>;
    }>({ values: initial });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(updateSettings().url, { preserveScroll: true });
    };

    const updateOne = (key: string, value: string) => {
        setData('values', { ...data.values, [key]: value });
    };

    return (
        <>
            <Head title="System settings · Admin" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <header className="flex flex-col gap-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        System settings
                    </h1>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        Application-wide configuration. Values set here override
                        the deployment defaults; clearing a field falls back to
                        the environment value (if any) or the registry default.
                    </p>
                </header>

                <form onSubmit={submit} className="flex flex-col gap-6">
                    {categories.map((cat) => {
                        const groups = groupSettings(cat.settings);
                        const hasNamedGroups = groups.some(
                            (g) => g.key !== '__default__',
                        );

                        return (
                            <Card
                                key={cat.key}
                                data-tour-id={
                                    cat.key === 'operations'
                                        ? 'system-settings-operations-card'
                                        : undefined
                                }
                            >
                                <CardContent className="flex flex-col gap-5 p-4">
                                    <h2 className="text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                                        {cat.label}
                                    </h2>

                                    {groups.map((g) => {
                                        const gate = g.settings.find(
                                            (s) => s.gates_group,
                                        );
                                        const isGated = !!gate;
                                        const gateOn =
                                            !gate ||
                                            isTruthy(data.values[gate.key]);

                                        return (
                                            <div
                                                key={g.key}
                                                className="flex flex-col gap-4"
                                            >
                                                {hasNamedGroups && g.label && (
                                                    <div className="-mb-1 flex items-center justify-between border-b border-border pb-1">
                                                        <span className="text-sm font-semibold">
                                                            {g.label}
                                                        </span>
                                                        {isGated && (
                                                            <GateToggle
                                                                setting={gate!}
                                                                value={
                                                                    data.values[
                                                                        gate!
                                                                            .key
                                                                    ] ?? ''
                                                                }
                                                                onChange={(v) =>
                                                                    updateOne(
                                                                        gate!
                                                                            .key,
                                                                        v,
                                                                    )
                                                                }
                                                            />
                                                        )}
                                                    </div>
                                                )}

                                                {g.settings
                                                    .filter((s) => {
                                                        if (s.gates_group) {
                                                            return false;
                                                        }

                                                        // Hide siblings of an "off" gate
                                                        if (
                                                            isGated &&
                                                            !gateOn
                                                        ) {
                                                            return false;
                                                        }

                                                        // Conditional field: show only when its
                                                        // controlling field has the required value
                                                        const sw =
                                                            s.options
                                                                ?.show_when;

                                                        if (
                                                            sw &&
                                                            data.values[
                                                                sw.field
                                                            ] !== sw.value
                                                        ) {
                                                            return false;
                                                        }

                                                        return true;
                                                    })
                                                    .map((s) => (
                                                        <SettingField
                                                            key={s.key}
                                                            setting={s}
                                                            value={
                                                                data.values[
                                                                    s.key
                                                                ] ?? ''
                                                            }
                                                            original={
                                                                initial[
                                                                    s.key
                                                                ] ?? ''
                                                            }
                                                            onChange={(v) =>
                                                                updateOne(
                                                                    s.key,
                                                                    v,
                                                                )
                                                            }
                                                        />
                                                    ))}

                                                {/* Gate is off - short reason underneath
                                                    so the panel isn't empty */}
                                                {isGated && !gateOn && (
                                                    <p className="text-xs text-muted-foreground italic">
                                                        Enable to configure.
                                                    </p>
                                                )}
                                            </div>
                                        );
                                    })}
                                </CardContent>
                            </Card>
                        );
                    })}

                    <div className="flex items-center gap-3">
                        <Button
                            type="submit"
                            disabled={processing}
                            data-tour-id="save-settings-button"
                        >
                            Save settings
                        </Button>
                        {recentlySuccessful && (
                            <span className="text-sm text-emerald-700 dark:text-emerald-300">
                                Saved.
                            </span>
                        )}
                    </div>
                </form>
            </div>
        </>
    );
}

function GateToggle({
    setting,
    value,
    onChange,
}: {
    setting: Setting;
    value: string;
    onChange: (v: string) => void;
}) {
    const checked = isTruthy(value);
    const fieldId = `gate-${setting.key.replace(/\./g, '-')}`;

    return (
        <label
            htmlFor={fieldId}
            className="flex cursor-pointer items-center gap-2 text-xs font-medium"
        >
            <Checkbox
                id={fieldId}
                checked={checked}
                onCheckedChange={(c) => onChange(c === true ? '1' : '0')}
            />
            <span>{checked ? 'Enabled' : 'Disabled'}</span>
        </label>
    );
}

function MultiSelectField({
    choices,
    value,
    onChange,
}: {
    choices: Choice[];
    value: string;
    onChange: (v: string) => void;
}) {
    const selected = new Set(
        value
            .split(',')
            .map((v) => v.trim())
            .filter((v) => v !== ''),
    );

    const toggle = (key: string, on: boolean) => {
        if (on) {
            selected.add(key);
        } else {
            selected.delete(key);
        }

        // Persist in catalog order so the layout is deterministic.
        const ordered = choices
            .map((c) => c.value)
            .filter((v) => selected.has(v));
        onChange(ordered.join(','));
    };

    return (
        <div className="flex flex-col gap-2 pt-1">
            {choices.map((c) => {
                const id = `choice-${c.value}`;

                return (
                    <label
                        key={c.value}
                        htmlFor={id}
                        className="flex cursor-pointer items-center gap-2 text-sm"
                    >
                        <Checkbox
                            id={id}
                            checked={selected.has(c.value)}
                            onCheckedChange={(checked) =>
                                toggle(c.value, checked === true)
                            }
                        />
                        <span>{c.label}</span>
                        <span className="font-mono text-xs text-muted-foreground">
                            {c.value}
                        </span>
                    </label>
                );
            })}
        </div>
    );
}

function SettingField({
    setting,
    value,
    original,
    onChange,
}: {
    setting: Setting;
    value: string;
    original: string;
    onChange: (v: string) => void;
}) {
    const warnChange =
        setting.options?.warn_on_change === true &&
        value !== original &&
        original !== '' &&
        original !== 'none';
    const [showSecret, setShowSecret] = useState(false);
    const isMaskedSecret = setting.is_secret && value === KEEP_CURRENT;

    const fieldId = `setting-${setting.key.replace(/\./g, '-')}`;
    const isVenueIsolation = setting.key === 'operations.venue_isolation';

    return (
        <label htmlFor={fieldId} className="flex flex-col gap-1">
            <div className="flex items-center justify-between">
                <span className="text-sm font-medium">{setting.label}</span>
                <span className="font-mono text-xs text-muted-foreground">
                    {setting.key}
                </span>
            </div>
            {setting.description ? (
                <span
                    className="text-xs text-muted-foreground"
                    data-tour-id={
                        isVenueIsolation
                            ? 'venue-isolation-description'
                            : undefined
                    }
                >
                    {setting.description}
                </span>
            ) : null}

            {setting.type === 'boolean' ? (
                <div className="flex items-center gap-2 pt-1">
                    <Checkbox
                        id={fieldId}
                        data-tour-id={
                            isVenueIsolation
                                ? 'venue-isolation-toggle'
                                : undefined
                        }
                        checked={value === '1' || value === 'true'}
                        onCheckedChange={(c) =>
                            onChange(c === true ? '1' : '0')
                        }
                    />
                    <span className="text-sm">
                        {value === '1' || value === 'true'
                            ? 'Enabled'
                            : 'Disabled'}
                    </span>
                </div>
            ) : setting.type === 'multiselect' ? (
                <MultiSelectField
                    choices={setting.options?.choices ?? []}
                    value={value}
                    onChange={onChange}
                />
            ) : setting.type === 'select' ? (
                <select
                    id={fieldId}
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    className="rounded-md border border-border bg-background px-3 py-2 text-sm"
                >
                    {(setting.options?.choices ?? []).map((c) => (
                        <option key={c.value} value={c.value}>
                            {c.label}
                        </option>
                    ))}
                </select>
            ) : setting.type === 'integer' ? (
                <input
                    id={fieldId}
                    type="number"
                    value={value}
                    min={
                        (setting.options?.min as number | undefined) ??
                        undefined
                    }
                    max={
                        (setting.options?.max as number | undefined) ??
                        undefined
                    }
                    onChange={(e) => onChange(e.target.value)}
                    className="rounded-md border border-border bg-background px-3 py-2 text-sm"
                />
            ) : setting.is_secret ? (
                <div className="flex gap-2">
                    <input
                        id={fieldId}
                        type={showSecret ? 'text' : 'password'}
                        value={isMaskedSecret ? '' : value}
                        placeholder={
                            isMaskedSecret
                                ? '•••••••• (set - leave blank to keep)'
                                : '(not configured)'
                        }
                        onChange={(e) => onChange(e.target.value)}
                        autoComplete="off"
                        className="flex-1 rounded-md border border-border bg-background px-3 py-2 text-sm"
                    />
                    {value && !isMaskedSecret && (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => setShowSecret((s) => !s)}
                        >
                            {showSecret ? 'Hide' : 'Show'}
                        </Button>
                    )}
                </div>
            ) : (
                <input
                    id={fieldId}
                    type="text"
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    className="rounded-md border border-border bg-background px-3 py-2 text-sm"
                />
            )}

            {warnChange && (
                <span className="rounded-md border border-amber-300 bg-amber-50 px-2 py-1 text-xs text-amber-800 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-200">
                    Changing the gateway from <strong>{original}</strong> will
                    not migrate stored payment tokens. Tokens and references
                    saved under the previous gateway will stop working, so
                    confirm this is intended before saving.
                </span>
            )}

            {setting.has_env_fallback && (
                <span className="text-xs text-muted-foreground">
                    Clear to fall back to environment / registry default.
                </span>
            )}
        </label>
    );
}

SystemSettingsIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin/system-settings' },
        { title: 'System settings', href: '#' },
    ],
};
