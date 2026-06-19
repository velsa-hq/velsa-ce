import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Item = {
    id: number;
    sku: string;
    name: string;
    category: string | null;
    unit_label: string;
    unit_price_cents: number;
    advance_price_cents: number | null;
    is_active: boolean;
};

type Category = { id: number; name: string };

type Props = { items: Item[]; categories: Category[] };

function usd(cents: number | null): string {
    if (cents === null) {
        return '-';
    }

    return (cents / 100).toLocaleString(undefined, {
        style: 'currency',
        currency: 'USD',
    });
}

const blank = (categoryId: number) => ({
    equipment_category_id: categoryId,
    sku: '',
    name: '',
    description: '',
    unit_label: 'each',
    unit_price: '',
    advance_price: '',
    is_active: true as boolean,
});

export default function EquipmentItemsIndex({ items, categories }: Props) {
    const [editing, setEditing] = useState<Item | null>(null);
    const { data, setData, post, put, processing, errors, reset } = useForm(
        blank(categories[0]?.id ?? 0),
    );

    const startEdit = (item: Item) => {
        setEditing(item);
        setData({
            equipment_category_id:
                categories.find((c) => c.name === item.category)?.id ??
                categories[0]?.id ??
                0,
            sku: item.sku,
            name: item.name,
            description: '',
            unit_label: item.unit_label,
            unit_price: String((item.unit_price_cents / 100).toFixed(2)),
            advance_price:
                item.advance_price_cents !== null
                    ? String((item.advance_price_cents / 100).toFixed(2))
                    : '',
            is_active: item.is_active,
        });
    };

    const cancel = () => {
        setEditing(null);
        reset();
        setData('equipment_category_id', categories[0]?.id ?? 0);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        if (editing) {
            put(`/admin/equipment-items/${editing.sku}`, {
                preserveScroll: true,
                onSuccess: cancel,
            });
        } else {
            post('/admin/equipment-items', {
                preserveScroll: true,
                onSuccess: () => reset(),
            });
        }
    };

    const toggle = (item: Item) => {
        router.patch(
            `/admin/equipment-items/${item.sku}/toggle`,
            {},
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Equipment catalog · Admin" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <header className="flex flex-col gap-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Equipment catalog
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Items and services exhibitors can order. Set a standard
                        price and an optional advance (early-order) price.
                    </p>
                </header>

                <Card>
                    <CardContent className="p-4">
                        <form
                            onSubmit={submit}
                            data-tour-id="ec-form"
                            className="grid gap-3 sm:grid-cols-3 sm:items-end"
                        >
                            <div className="grid gap-1">
                                <Label htmlFor="cat">Category</Label>
                                <select
                                    id="cat"
                                    data-tour-id="ec-category"
                                    className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                                    value={data.equipment_category_id}
                                    onChange={(e) =>
                                        setData(
                                            'equipment_category_id',
                                            Number(e.target.value),
                                        )
                                    }
                                >
                                    {categories.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="sku">SKU</Label>
                                <Input
                                    id="sku"
                                    value={data.sku}
                                    onChange={(e) =>
                                        setData('sku', e.target.value)
                                    }
                                />
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
                                />
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="unit">Unit label</Label>
                                <Input
                                    id="unit"
                                    value={data.unit_label}
                                    onChange={(e) =>
                                        setData('unit_label', e.target.value)
                                    }
                                />
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="price">
                                    Standard price ($)
                                </Label>
                                <Input
                                    id="price"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={data.unit_price}
                                    onChange={(e) =>
                                        setData('unit_price', e.target.value)
                                    }
                                />
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="adv">
                                    Advance price ($, optional)
                                </Label>
                                <Input
                                    id="adv"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={data.advance_price}
                                    onChange={(e) =>
                                        setData('advance_price', e.target.value)
                                    }
                                />
                            </div>
                            <div className="flex gap-2 sm:col-span-3">
                                <Button
                                    type="submit"
                                    data-tour-id="ec-add-item"
                                    disabled={processing}
                                >
                                    {editing ? 'Save changes' : 'Add item'}
                                </Button>
                                {editing && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        onClick={cancel}
                                    >
                                        Cancel
                                    </Button>
                                )}
                            </div>
                        </form>
                        {(errors.sku || errors.name || errors.unit_price) && (
                            <p className="mt-2 text-xs text-destructive">
                                {errors.sku ?? errors.name ?? errors.unit_price}
                            </p>
                        )}
                    </CardContent>
                </Card>

                <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table data-tour-id="ec-table" className="w-full text-sm">
                        <thead className="bg-muted/50">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium">
                                    Item
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Category
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Standard
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Advance
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Active
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {items.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-4 py-6 text-center text-muted-foreground"
                                    >
                                        No catalog items yet.
                                    </td>
                                </tr>
                            ) : (
                                items.map((item) => (
                                    <tr
                                        key={item.id}
                                        className={
                                            item.is_active ? '' : 'opacity-50'
                                        }
                                    >
                                        <td className="px-4 py-2">
                                            <div className="font-medium">
                                                {item.name}
                                            </div>
                                            <div className="font-mono text-xs text-muted-foreground">
                                                {item.sku} · per{' '}
                                                {item.unit_label}
                                            </div>
                                        </td>
                                        <td className="px-4 py-2">
                                            {item.category ?? '-'}
                                        </td>
                                        <td className="px-4 py-2 text-right tabular-nums">
                                            {usd(item.unit_price_cents)}
                                        </td>
                                        <td className="px-4 py-2 text-right tabular-nums">
                                            {usd(item.advance_price_cents)}
                                        </td>
                                        <td className="px-4 py-2">
                                            <button
                                                type="button"
                                                data-tour-id="ec-toggle-active"
                                                onClick={() => toggle(item)}
                                                className={`rounded-full px-2 py-0.5 text-xs font-medium ${item.is_active ? 'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-100' : 'bg-neutral-200 text-neutral-700 dark:bg-neutral-700 dark:text-neutral-200'}`}
                                            >
                                                {item.is_active
                                                    ? 'Active'
                                                    : 'Inactive'}
                                            </button>
                                        </td>
                                        <td className="px-4 py-2 text-right">
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                data-tour-id="ec-edit-row"
                                                onClick={() => startEdit(item)}
                                            >
                                                Edit
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

EquipmentItemsIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin/equipment-items' },
        { title: 'Equipment catalog', href: '#' },
    ],
};
