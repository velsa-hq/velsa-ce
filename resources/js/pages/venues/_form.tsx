import { ImagePicker } from '@/components/image-picker';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type VenueDefaults = {
    name?: string;
    building?: string | null;
    street?: string | null;
    city?: string | null;
    state?: string | null;
    zip?: string | null;
    phone?: string | null;
    website?: string | null;
    timezone?: string;
    summary?: string | null;
    is_active?: boolean;
    enforce_setup_buffers?: boolean;
    exhibitor_handbook_md?: string | null;
    exhibitor_handbook_published?: boolean;
    image_url?: string | null;
};

/**
 * Shared field set for the venue create + edit forms. Rendered inside an
 * Inertia <Form>, so inputs are uncontrolled (name + defaultValue).
 */
export function VenueFields({
    errors,
    venue,
}: {
    errors: Record<string, string>;
    venue?: VenueDefaults;
}) {
    return (
        <div className="grid gap-4 sm:grid-cols-6">
            <div className="grid gap-2 sm:col-span-6">
                <Label htmlFor="name">Name</Label>
                <Input
                    id="name"
                    name="name"
                    type="text"
                    required
                    defaultValue={venue?.name ?? ''}
                    data-tour-id="venue-name"
                />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-2 sm:col-span-3">
                <Label htmlFor="building">Building / suite</Label>
                <Input
                    id="building"
                    name="building"
                    type="text"
                    defaultValue={venue?.building ?? ''}
                    placeholder="e.g. Bldg C, Ste 200"
                />
                <InputError message={errors.building} />
            </div>

            <div className="grid gap-2 sm:col-span-3">
                <Label htmlFor="street">Street</Label>
                <Input
                    id="street"
                    name="street"
                    type="text"
                    defaultValue={venue?.street ?? ''}
                />
                <InputError message={errors.street} />
            </div>

            <div className="grid gap-2 sm:col-span-3">
                <Label htmlFor="city">City</Label>
                <Input
                    id="city"
                    name="city"
                    type="text"
                    defaultValue={venue?.city ?? ''}
                />
                <InputError message={errors.city} />
            </div>

            <div className="grid gap-2 sm:col-span-1">
                <Label htmlFor="state">State</Label>
                <Input
                    id="state"
                    name="state"
                    type="text"
                    maxLength={2}
                    defaultValue={venue?.state ?? ''}
                    placeholder="FL"
                />
                <InputError message={errors.state} />
            </div>

            <div className="grid gap-2 sm:col-span-2">
                <Label htmlFor="zip">ZIP</Label>
                <Input
                    id="zip"
                    name="zip"
                    type="text"
                    defaultValue={venue?.zip ?? ''}
                />
                <InputError message={errors.zip} />
            </div>

            <div className="grid gap-2 sm:col-span-3">
                <Label htmlFor="phone">Phone</Label>
                <Input
                    id="phone"
                    name="phone"
                    type="tel"
                    defaultValue={venue?.phone ?? ''}
                />
                <InputError message={errors.phone} />
            </div>

            <div className="grid gap-2 sm:col-span-3">
                <Label htmlFor="website">Website</Label>
                <Input
                    id="website"
                    name="website"
                    type="text"
                    defaultValue={venue?.website ?? ''}
                    placeholder="https://..."
                />
                <InputError message={errors.website} />
            </div>

            <div className="grid gap-2 sm:col-span-3">
                <Label htmlFor="timezone">Time zone</Label>
                <Input
                    id="timezone"
                    name="timezone"
                    type="text"
                    required
                    defaultValue={venue?.timezone ?? 'America/Chicago'}
                    placeholder="America/Chicago"
                />
                <InputError message={errors.timezone} />
            </div>

            <label
                htmlFor="is_active"
                className="flex items-center gap-2 self-end rounded-md border border-border p-2 text-sm has-[:checked]:bg-muted sm:col-span-3"
            >
                <input
                    id="is_active"
                    type="checkbox"
                    name="is_active"
                    value="1"
                    defaultChecked={venue?.is_active ?? false}
                    className="size-4 rounded border-border accent-primary"
                />
                <span className="font-medium">Active venue</span>
                <span className="text-xs text-muted-foreground">
                    Inactive venues are hidden from dropdowns + calendar.
                </span>
            </label>

            <label
                htmlFor="enforce_setup_buffers"
                className="flex items-center gap-2 self-end rounded-md border border-border p-2 text-sm has-[:checked]:bg-muted sm:col-span-3"
            >
                <input
                    id="enforce_setup_buffers"
                    type="checkbox"
                    name="enforce_setup_buffers"
                    value="1"
                    defaultChecked={venue?.enforce_setup_buffers ?? false}
                    className="size-4 rounded border-border accent-primary"
                />
                <span className="font-medium">Reserve setup/teardown</span>
                <span className="text-xs text-muted-foreground">
                    Count each booking's setup + teardown time as occupied when
                    checking for conflicts.
                </span>
            </label>

            <div className="grid gap-2 sm:col-span-6">
                <Label htmlFor="summary">Summary</Label>
                <textarea
                    id="summary"
                    name="summary"
                    rows={4}
                    defaultValue={venue?.summary ?? ''}
                    placeholder="Short description shown on the venues index."
                    className="rounded-md border border-border bg-background px-3 py-2 text-sm"
                />
                <InputError message={errors.summary} />
            </div>

            <div className="grid gap-2 sm:col-span-6">
                <Label htmlFor="exhibitor_handbook_md">
                    Exhibitor handbook (Markdown)
                </Label>
                <textarea
                    id="exhibitor_handbook_md"
                    name="exhibitor_handbook_md"
                    rows={10}
                    data-tour-id="venue-exhibitor-handbook"
                    defaultValue={venue?.exhibitor_handbook_md ?? ''}
                    placeholder="Rules and policies shown to this venue's exhibitors (prohibited items, load-in/out, smoking, storage, damage charges)..."
                    className="rounded-md border border-border bg-background px-3 py-2 font-mono text-sm"
                />
                <InputError message={errors.exhibitor_handbook_md} />
                <label
                    htmlFor="exhibitor_handbook_published"
                    className="flex items-center gap-2 rounded-md border border-border p-2 text-sm has-[:checked]:bg-muted"
                >
                    <input
                        id="exhibitor_handbook_published"
                        type="checkbox"
                        name="exhibitor_handbook_published"
                        value="1"
                        data-tour-id="venue-handbook-publish"
                        defaultChecked={
                            venue?.exhibitor_handbook_published ?? false
                        }
                        className="size-4 rounded border-border accent-primary"
                    />
                    <span className="font-medium">
                        Publish exhibitor handbook
                    </span>
                    <span className="text-xs text-muted-foreground">
                        Show it to this venue's exhibitors in their portal.
                    </span>
                </label>
            </div>

            <div className="sm:col-span-6">
                <ImagePicker
                    currentUrl={venue?.image_url}
                    label="Venue image"
                />
                <InputError message={errors.photo} />
            </div>
        </div>
    );
}
