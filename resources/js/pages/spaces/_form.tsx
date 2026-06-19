import { ImagePicker } from '@/components/image-picker';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useMeasurement } from '@/hooks/use-measurement';

export type Option = { value: string; label: string };
export type ParentOption = { id: number; name: string };

export type SpaceDefaults = {
    name?: string;
    kind?: string | null;
    capacity?: number | null;
    sqft?: number | null;
    bookable_unit?: string | null;
    parent_space_id?: number | null;
    image_url?: string | null;
};

const selectClass =
    'rounded-md border border-border bg-background px-3 py-2 text-sm';

/**
 * Shared field set for the Space create + edit forms. Rendered inside an
 * Inertia <Form>, so inputs are uncontrolled (name + defaultValue).
 */
export function SpaceFormFields({
    errors,
    kinds,
    parents,
    bookableUnits,
    space,
}: {
    errors: Record<string, string>;
    kinds: Option[];
    parents: ParentOption[];
    bookableUnits: Option[];
    space?: SpaceDefaults;
}) {
    const { unit, fromSqft } = useMeasurement();

    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <div className="grid gap-2 sm:col-span-2">
                <Label htmlFor="name">Name</Label>
                <Input
                    id="name"
                    name="name"
                    type="text"
                    required
                    defaultValue={space?.name ?? ''}
                    placeholder="Grand Ballroom A"
                    data-tour-id="space-name"
                />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="kind">Kind</Label>
                <select
                    id="kind"
                    name="kind"
                    required
                    defaultValue={space?.kind ?? ''}
                    className={selectClass}
                    data-tour-id="space-kind"
                >
                    <option value="" disabled>
                        Select a kind...
                    </option>
                    {kinds.map((k) => (
                        <option key={k.value} value={k.value}>
                            {k.label}
                        </option>
                    ))}
                </select>
                <InputError message={errors.kind} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="bookable_unit">Bookable unit</Label>
                <select
                    id="bookable_unit"
                    name="bookable_unit"
                    required
                    defaultValue={space?.bookable_unit ?? 'daily'}
                    className={selectClass}
                    data-tour-id="space-bookable-unit"
                >
                    {bookableUnits.map((u) => (
                        <option key={u.value} value={u.value}>
                            {u.label}
                        </option>
                    ))}
                </select>
                <InputError message={errors.bookable_unit} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="capacity">Capacity</Label>
                <Input
                    id="capacity"
                    name="capacity"
                    type="number"
                    min={0}
                    defaultValue={space?.capacity ?? ''}
                />
                <InputError message={errors.capacity} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="sqft">Area ({unit})</Label>
                <Input
                    id="sqft"
                    name="sqft"
                    type="number"
                    min={0}
                    defaultValue={
                        space?.sqft != null ? fromSqft(space.sqft) : ''
                    }
                />
                <InputError message={errors.sqft} />
            </div>

            <div className="grid gap-2 sm:col-span-2">
                <Label htmlFor="parent_space_id">Parent space (optional)</Label>
                <select
                    id="parent_space_id"
                    name="parent_space_id"
                    defaultValue={space?.parent_space_id ?? ''}
                    className={selectClass}
                    data-tour-id="space-parent"
                >
                    <option value="">- None (top-level) -</option>
                    {parents.map((p) => (
                        <option key={p.id} value={p.id}>
                            {p.name}
                        </option>
                    ))}
                </select>
                <p className="text-xs text-muted-foreground">
                    Sub-spaces (e.g. ballroom sections) sit under a parent and
                    drive the partition / adjacency rules.
                </p>
                <InputError message={errors.parent_space_id} />
            </div>

            <div className="sm:col-span-2">
                <ImagePicker
                    currentUrl={space?.image_url}
                    label="Space image"
                />
                <InputError message={errors.photo} />
            </div>
        </div>
    );
}
