import { useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Option = { value: number; label: string };
type SpaceOption = Option & { venue_id: number };
type StrOption = { value: string; label: string };

type Item = {
    kind: 'space' | 'equipment' | 'service';
    space_id: number | '';
    equipment_sku: string;
    label: string;
    quantity: string;
    unit: string;
    notes: string;
};

export type RatePackageFormData = {
    venue_id: number | '';
    name: string;
    kind: string;
    price: string;
    effective_from: string;
    effective_to: string;
    is_active: boolean;
    description: string;
    items: Item[];
};

type Props = {
    venues: Option[];
    spaces: SpaceOption[];
    equipment: StrOption[];
    kinds: StrOption[];
    units: StrOption[];
    initial: RatePackageFormData;
    submitUrl: string;
    method: 'post' | 'put';
    submitLabel: string;
};

const selectClass =
    'rounded-md border border-border bg-background px-2 py-1 text-sm';

export default function RatePackageForm({
    venues,
    spaces,
    equipment,
    kinds,
    units,
    initial,
    submitUrl,
    method,
    submitLabel,
}: Props) {
    const { data, setData, post, put, processing, errors } =
        useForm<RatePackageFormData>(initial);

    const venueSpaces = useMemo(
        () => spaces.filter((s) => s.venue_id === data.venue_id),
        [spaces, data.venue_id],
    );

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        (method === 'put' ? put : post)(submitUrl, { preserveScroll: true });
    };

    const setItem = (i: number, patch: Partial<Item>) =>
        setData(
            'items',
            data.items.map((row, idx) =>
                idx === i ? { ...row, ...patch } : row,
            ),
        );
    const addItem = () =>
        setData('items', [
            ...data.items,
            {
                kind: 'service',
                space_id: '',
                equipment_sku: '',
                label: '',
                quantity: '1',
                unit: '',
                notes: '',
            },
        ]);
    const removeItem = (i: number) =>
        setData(
            'items',
            data.items.filter((_, idx) => idx !== i),
        );

    return (
        <form onSubmit={submit} className="space-y-5">
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="venue_id">Venue</Label>
                    <select
                        id="venue_id"
                        className={selectClass}
                        value={data.venue_id}
                        onChange={(e) =>
                            setData(
                                'venue_id',
                                e.target.value ? Number(e.target.value) : '',
                            )
                        }
                    >
                        <option value="">Select...</option>
                        {venues.map((v) => (
                            <option key={v.value} value={v.value}>
                                {v.label}
                            </option>
                        ))}
                    </select>
                    <InputError message={errors.venue_id} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="name">Name</Label>
                    <Input
                        id="name"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                    />
                    <InputError message={errors.name} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="kind">Kind</Label>
                    <select
                        id="kind"
                        className={selectClass}
                        value={data.kind}
                        onChange={(e) => setData('kind', e.target.value)}
                    >
                        {kinds.map((k) => (
                            <option key={k.value} value={k.value}>
                                {k.label}
                            </option>
                        ))}
                    </select>
                    <InputError message={errors.kind} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="price">Package price (USD)</Label>
                    <Input
                        id="price"
                        type="number"
                        min="0"
                        step="0.01"
                        value={data.price}
                        onChange={(e) => setData('price', e.target.value)}
                    />
                    <InputError message={errors.price} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="effective_from">Effective from</Label>
                    <Input
                        id="effective_from"
                        type="date"
                        value={data.effective_from}
                        onChange={(e) =>
                            setData('effective_from', e.target.value)
                        }
                    />
                    <InputError message={errors.effective_from} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="effective_to">
                        Effective to (optional)
                    </Label>
                    <Input
                        id="effective_to"
                        type="date"
                        value={data.effective_to}
                        onChange={(e) =>
                            setData('effective_to', e.target.value)
                        }
                    />
                    <InputError message={errors.effective_to} />
                </div>
                <div className="flex items-end gap-2">
                    <input
                        id="is_active"
                        type="checkbox"
                        checked={data.is_active}
                        onChange={(e) => setData('is_active', e.target.checked)}
                    />
                    <Label htmlFor="is_active">Active</Label>
                </div>
            </div>

            <div>
                <div className="mb-2 flex items-center justify-between">
                    <Label>Included items</Label>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={addItem}
                    >
                        Add item
                    </Button>
                </div>
                {data.items.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        No items yet - add the spaces, equipment, and services
                        this bundle includes.
                    </p>
                )}
                <div className="space-y-2">
                    {data.items.map((row, i) => (
                        <div
                            key={i}
                            className="flex flex-wrap items-end gap-2 rounded-md border border-border p-2"
                        >
                            <select
                                className={selectClass}
                                value={row.kind}
                                onChange={(e) =>
                                    setItem(i, {
                                        kind: e.target.value as Item['kind'],
                                    })
                                }
                            >
                                <option value="space">Space</option>
                                <option value="equipment">Equipment</option>
                                <option value="service">Service</option>
                            </select>
                            {row.kind === 'space' && (
                                <select
                                    className={selectClass}
                                    value={row.space_id}
                                    onChange={(e) =>
                                        setItem(i, {
                                            space_id: e.target.value
                                                ? Number(e.target.value)
                                                : '',
                                        })
                                    }
                                >
                                    <option value="">Space...</option>
                                    {venueSpaces.map((s) => (
                                        <option key={s.value} value={s.value}>
                                            {s.label}
                                        </option>
                                    ))}
                                </select>
                            )}
                            {row.kind === 'equipment' && (
                                <select
                                    className={selectClass}
                                    value={row.equipment_sku}
                                    onChange={(e) =>
                                        setItem(i, {
                                            equipment_sku: e.target.value,
                                        })
                                    }
                                >
                                    <option value="">Equipment...</option>
                                    {equipment.map((eq) => (
                                        <option key={eq.value} value={eq.value}>
                                            {eq.label}
                                        </option>
                                    ))}
                                </select>
                            )}
                            {row.kind === 'service' && (
                                <Input
                                    className="w-48"
                                    placeholder="Service description"
                                    value={row.label}
                                    onChange={(e) =>
                                        setItem(i, { label: e.target.value })
                                    }
                                />
                            )}
                            <Input
                                className="w-20"
                                type="number"
                                min="1"
                                placeholder="Qty"
                                value={row.quantity}
                                onChange={(e) =>
                                    setItem(i, { quantity: e.target.value })
                                }
                            />
                            <select
                                className={selectClass}
                                value={row.unit}
                                onChange={(e) =>
                                    setItem(i, { unit: e.target.value })
                                }
                            >
                                <option value="">Unit...</option>
                                {units.map((u) => (
                                    <option key={u.value} value={u.value}>
                                        {u.label}
                                    </option>
                                ))}
                            </select>
                            <Input
                                className="w-40"
                                placeholder="Notes"
                                value={row.notes}
                                onChange={(e) =>
                                    setItem(i, { notes: e.target.value })
                                }
                            />
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                onClick={() => removeItem(i)}
                            >
                                Remove
                            </Button>
                        </div>
                    ))}
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="description">Description</Label>
                <textarea
                    id="description"
                    rows={2}
                    className="rounded-md border border-border bg-background px-3 py-2 text-sm"
                    value={data.description}
                    onChange={(e) => setData('description', e.target.value)}
                />
            </div>

            <Button type="submit" disabled={processing}>
                {processing && <Spinner className="mr-2" />}
                {submitLabel}
            </Button>
        </form>
    );
}
