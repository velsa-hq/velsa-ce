import { Form, router } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Eye, EyeOff } from 'lucide-react';
import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { deptSwatch } from '@/lib/department-colors';

export type TaxonomyItem = {
    id: number;
    key: string;
    label: string;
    color: string | null;
    sort_order: number;
    is_active: boolean;
    is_system: boolean;
    usage_count: number;
};

type FormProps = Record<string, unknown>;

/** Optional subclass-specific select rendered in the add + edit forms. */
export type TaxonomyExtraField = {
    name: string;
    label: string;
    options: string[];
    emptyLabel?: string;
};

export type TaxonomyRoutes = {
    store: { form: () => FormProps };
    update: { form: (id: number) => FormProps };
    toggle: (id: number) => { url: string };
    move: (id: number) => { url: string };
    destroy: { form: (id: number) => FormProps };
};

type Props = {
    /** Page heading, e.g. "Departments". */
    title: string;
    /** Intro prose under the heading. */
    description: ReactNode;
    items: TaxonomyItem[];
    /** Palette keys; empty disables the color column. */
    colors: string[];
    routes: TaxonomyRoutes;
    /** Header for the usage column, e.g. "In use". */
    usageLabel: string;
    /** Placeholder for the add-label input. */
    addPlaceholder: string;
    /** Prefix for data-tour-id hooks, e.g. "department". */
    tourPrefix: string;
    /** Title attribute on a disabled delete for system rows. */
    systemDeleteHint: string;
    /** Title attribute on a disabled delete for in-use rows. */
    inUseDeleteHint: string;
    /** Optional extra select rendered in the add + edit forms. */
    extraField?: TaxonomyExtraField;
};

function ColorSelect({
    name,
    defaultValue,
    colors,
}: {
    name: string;
    defaultValue: string;
    colors: string[];
}) {
    return (
        <select
            name={name}
            defaultValue={defaultValue}
            className="rounded-md border border-input bg-background px-2 py-1.5 text-sm capitalize"
        >
            {colors.map((c) => (
                <option key={c} value={c}>
                    {c}
                </option>
            ))}
        </select>
    );
}

function ExtraFieldSelect({
    field,
    defaultValue,
}: {
    field: TaxonomyExtraField;
    defaultValue?: string | null;
}) {
    return (
        <select
            name={field.name}
            defaultValue={defaultValue ?? ''}
            className="rounded-md border border-input bg-background px-2 py-1.5 text-sm"
        >
            <option value="">{field.emptyLabel ?? '- None -'}</option>
            {field.options.map((o) => (
                <option key={o} value={o}>
                    {o}
                </option>
            ))}
        </select>
    );
}

export function TaxonomyAdmin({
    title,
    description,
    items,
    colors,
    routes,
    usageLabel,
    addPlaceholder,
    tourPrefix,
    systemDeleteHint,
    inUseDeleteHint,
    extraField,
}: Props) {
    const hasColor = colors.length > 0;

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4">
            <header className="flex flex-col gap-1">
                <h1 className="text-2xl font-semibold tracking-tight">
                    {title}
                </h1>
                <p className="max-w-2xl text-sm text-muted-foreground">
                    {description}
                </p>
            </header>

            <Card>
                <CardContent className="p-4">
                    <h2 className="mb-3 text-sm font-semibold">Add</h2>
                    <Form
                        {...routes.store.form()}
                        options={{ preserveScroll: true }}
                        resetOnSuccess
                        className="flex items-end gap-3"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-1">
                                    <Label htmlFor="label">Label</Label>
                                    <Input
                                        id="label"
                                        name="label"
                                        type="text"
                                        required
                                        placeholder={addPlaceholder}
                                        className="w-64"
                                        data-tour-id={`${tourPrefix}-label`}
                                    />
                                    <InputError message={errors.label} />
                                </div>
                                {hasColor ? (
                                    <div className="grid gap-1">
                                        <Label htmlFor="color">Color</Label>
                                        <ColorSelect
                                            name="color"
                                            defaultValue={colors[0]}
                                            colors={colors}
                                        />
                                        <InputError message={errors.color} />
                                    </div>
                                ) : null}
                                {extraField ? (
                                    <div className="grid gap-1">
                                        <Label htmlFor={extraField.name}>
                                            {extraField.label}
                                        </Label>
                                        <ExtraFieldSelect field={extraField} />
                                    </div>
                                ) : null}
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    data-tour-id={`${tourPrefix}-add`}
                                >
                                    {processing && <Spinner />}
                                    Add
                                </Button>
                            </>
                        )}
                    </Form>
                </CardContent>
            </Card>

            <Card>
                <CardContent className="p-0">
                    <table className="w-full text-sm">
                        <thead className="border-b border-border text-left text-xs text-muted-foreground">
                            <tr>
                                <th className="p-3 font-medium">Order</th>
                                <th className="p-3 font-medium">Label</th>
                                <th className="p-3 font-medium">Key</th>
                                <th className="p-3 font-medium">
                                    {usageLabel}
                                </th>
                                <th className="p-3 font-medium" />
                            </tr>
                        </thead>
                        <tbody>
                            {items.map((item, i) => (
                                <TaxonomyRow
                                    key={item.id}
                                    item={item}
                                    colors={colors}
                                    routes={routes}
                                    tourPrefix={tourPrefix}
                                    systemDeleteHint={systemDeleteHint}
                                    inUseDeleteHint={inUseDeleteHint}
                                    extraField={extraField}
                                    isFirst={i === 0}
                                    isLast={i === items.length - 1}
                                />
                            ))}
                        </tbody>
                    </table>
                </CardContent>
            </Card>
        </div>
    );
}

function TaxonomyRow({
    item,
    colors,
    routes,
    tourPrefix,
    systemDeleteHint,
    inUseDeleteHint,
    extraField,
    isFirst,
    isLast,
}: {
    item: TaxonomyItem;
    colors: string[];
    routes: TaxonomyRoutes;
    tourPrefix: string;
    systemDeleteHint: string;
    inUseDeleteHint: string;
    extraField?: TaxonomyExtraField;
    isFirst: boolean;
    isLast: boolean;
}) {
    const hasColor = colors.length > 0;
    const deletable = !item.is_system && item.usage_count === 0;

    const moveItem = (direction: 'up' | 'down') =>
        router.patch(
            routes.move(item.id).url,
            { direction },
            { preserveScroll: true },
        );

    const toggleItem = () =>
        router.patch(routes.toggle(item.id).url, {}, { preserveScroll: true });

    return (
        <tr
            className={`border-b border-border last:border-0 ${
                item.is_active ? '' : 'opacity-50'
            }`}
        >
            <td className="p-3">
                <div className="flex items-center gap-1">
                    <button
                        type="button"
                        onClick={() => moveItem('up')}
                        disabled={isFirst}
                        className="rounded p-0.5 text-muted-foreground hover:bg-muted disabled:opacity-30"
                        title="Move up"
                        data-tour-id={`${tourPrefix}-move-up`}
                    >
                        <ArrowUp className="size-4" />
                    </button>
                    <button
                        type="button"
                        onClick={() => moveItem('down')}
                        disabled={isLast}
                        className="rounded p-0.5 text-muted-foreground hover:bg-muted disabled:opacity-30"
                        title="Move down"
                        data-tour-id={`${tourPrefix}-move-down`}
                    >
                        <ArrowDown className="size-4" />
                    </button>
                </div>
            </td>
            <Form
                {...routes.update.form(item.id)}
                options={{ preserveScroll: true }}
                as="td"
                className="p-3"
            >
                {({ processing }) => (
                    <div className="flex items-center gap-2">
                        {hasColor ? (
                            <span
                                className={`inline-block size-3 shrink-0 rounded-full ${deptSwatch(item.color)}`}
                                aria-hidden
                            />
                        ) : null}
                        <Input
                            name="label"
                            type="text"
                            required
                            defaultValue={item.label}
                            className="w-44"
                        />
                        {hasColor ? (
                            <ColorSelect
                                name="color"
                                defaultValue={item.color ?? colors[0]}
                                colors={colors}
                            />
                        ) : null}
                        {extraField ? (
                            <ExtraFieldSelect
                                field={extraField}
                                defaultValue={
                                    (item as Record<string, unknown>)[
                                        extraField.name
                                    ] as string | null
                                }
                            />
                        ) : null}
                        {item.is_system ? (
                            <span className="rounded-full bg-muted px-2 py-0.5 text-[10px] font-medium text-muted-foreground">
                                system
                            </span>
                        ) : null}
                        {!item.is_active ? (
                            <span className="rounded-full bg-muted px-2 py-0.5 text-[10px] font-medium text-muted-foreground">
                                hidden
                            </span>
                        ) : null}
                        <Button
                            type="submit"
                            variant="outline"
                            size="sm"
                            disabled={processing}
                        >
                            Save
                        </Button>
                    </div>
                )}
            </Form>
            <td className="p-3 font-mono text-xs text-muted-foreground">
                {item.key}
            </td>
            <td className="p-3 text-muted-foreground">{item.usage_count}</td>
            <td className="p-3">
                <div className="flex items-center justify-end gap-3">
                    <button
                        type="button"
                        onClick={toggleItem}
                        className="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                        title={item.is_active ? 'Hide' : 'Show'}
                        data-tour-id={`${tourPrefix}-hide`}
                    >
                        {item.is_active ? (
                            <>
                                <EyeOff className="size-3.5" /> Hide
                            </>
                        ) : (
                            <>
                                <Eye className="size-3.5" /> Show
                            </>
                        )}
                    </button>
                    {deletable ? (
                        <Form
                            {...routes.destroy.form(item.id)}
                            options={{ preserveScroll: true }}
                        >
                            <button
                                type="submit"
                                className="text-xs text-rose-600 hover:underline dark:text-rose-400"
                            >
                                Delete
                            </button>
                        </Form>
                    ) : (
                        <span
                            className="text-xs text-muted-foreground/50"
                            title={
                                item.is_system
                                    ? systemDeleteHint
                                    : inUseDeleteHint
                            }
                        >
                            -
                        </span>
                    )}
                </div>
            </td>
        </tr>
    );
}
