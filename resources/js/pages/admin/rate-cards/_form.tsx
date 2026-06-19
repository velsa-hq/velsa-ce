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

type Entry = {
    kind: 'space' | 'equipment';
    space_id: number | '';
    equipment_sku: string;
    unit: string;
    rate: string;
    min_charge: string;
    included_hours: string;
};

export type RateCardFormData = {
    venue_id: number | '';
    name: string;
    kind: string;
    effective_from: string;
    effective_to: string;
    is_active: boolean;
    notes: string;
    entries: Entry[];
};

type Props = {
    venues: Option[];
    spaces: SpaceOption[];
    equipment: StrOption[];
    kinds: StrOption[];
    units: StrOption[];
    initial: RateCardFormData;
    submitUrl: string;
    method: 'post' | 'put';
    submitLabel: string;
};

const selectClass =
    'rounded-md border border-border bg-background px-2 py-1 text-sm';

export default function RateCardForm({
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
        useForm<RateCardFormData>(initial);

    const venueSpaces = useMemo(
        () => spaces.filter((s) => s.venue_id === data.venue_id),
        [spaces, data.venue_id],
    );

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        (method === 'put' ? put : post)(submitUrl, { preserveScroll: true });
    };

    const setEntry = (i: number, patch: Partial<Entry>) =>
        setData(
            'entries',
            data.entries.map((row, idx) =>
                idx === i ? { ...row, ...patch } : row,
            ),
        );
    const addEntry = () =>
        setData('entries', [
            ...data.entries,
            {
                kind: 'space',
                space_id: '',
                equipment_sku: '',
                unit: units[0]?.value ?? 'hourly',
                rate: '',
                min_charge: '',
                included_hours: '',
            },
        ]);
    const removeEntry = (i: number) =>
        setData(
            'entries',
            data.entries.filter((_, idx) => idx !== i),
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
                <div className="flex items-end gap-2">
                    <input
                        id="is_active"
                        type="checkbox"
                        checked={data.is_active}
                        onChange={(e) => setData('is_active', e.target.checked)}
                    />
                    <Label htmlFor="is_active">Active</Label>
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
            </div>

            <div>
                <div className="mb-2 flex items-center justify-between">
                    <Label>Rate entries</Label>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={addEntry}
                    >
                        Add entry
                    </Button>
                </div>
                {data.entries.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        No entries yet - add rental or equipment rates.
                    </p>
                )}
                <div className="space-y-2">
                    {data.entries.map((row, i) => (
                        <div
                            key={i}
                            className="flex flex-wrap items-end gap-2 rounded-md border border-border p-2"
                        >
                            <select
                                className={selectClass}
                                value={row.kind}
                                onChange={(e) =>
                                    setEntry(i, {
                                        kind: e.target.value as Entry['kind'],
                                    })
                                }
                            >
                                <option value="space">Space</option>
                                <option value="equipment">Equipment</option>
                            </select>
                            {row.kind === 'space' ? (
                                <select
                                    className={selectClass}
                                    value={row.space_id}
                                    onChange={(e) =>
                                        setEntry(i, {
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
                            ) : (
                                <select
                                    className={selectClass}
                                    value={row.equipment_sku}
                                    onChange={(e) =>
                                        setEntry(i, {
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
                            <select
                                className={selectClass}
                                value={row.unit}
                                onChange={(e) =>
                                    setEntry(i, { unit: e.target.value })
                                }
                            >
                                {units.map((u) => (
                                    <option key={u.value} value={u.value}>
                                        {u.label}
                                    </option>
                                ))}
                            </select>
                            <Input
                                className="w-28"
                                type="number"
                                min="0"
                                step="0.01"
                                placeholder="Rate $"
                                value={row.rate}
                                onChange={(e) =>
                                    setEntry(i, { rate: e.target.value })
                                }
                            />
                            <Input
                                className="w-28"
                                type="number"
                                min="0"
                                step="0.01"
                                placeholder="Min $"
                                value={row.min_charge}
                                onChange={(e) =>
                                    setEntry(i, { min_charge: e.target.value })
                                }
                            />
                            <Input
                                className="w-24"
                                type="number"
                                min="0"
                                placeholder="Incl. hrs"
                                value={row.included_hours}
                                onChange={(e) =>
                                    setEntry(i, {
                                        included_hours: e.target.value,
                                    })
                                }
                            />
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                onClick={() => removeEntry(i)}
                            >
                                Remove
                            </Button>
                        </div>
                    ))}
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="notes">Notes</Label>
                <textarea
                    id="notes"
                    rows={2}
                    className="rounded-md border border-border bg-background px-3 py-2 text-sm"
                    value={data.notes}
                    onChange={(e) => setData('notes', e.target.value)}
                />
            </div>

            <Button type="submit" disabled={processing}>
                {processing && <Spinner className="mr-2" />}
                {submitLabel}
            </Button>
        </form>
    );
}
