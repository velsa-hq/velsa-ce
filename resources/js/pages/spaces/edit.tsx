import { Form, Head, Link, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { destroy, update } from '@/routes/spaces';
import { show as venueShow } from '@/routes/venues';
import { SpaceFormFields } from './_form';
import type { Option, ParentOption, SpaceDefaults } from './_form';

type Venue = { id: number; slug: string; name: string };
type Space = SpaceDefaults & { id: number; name: string };

type Props = {
    venue: Venue;
    space: Space;
    kinds: Option[];
    parents: ParentOption[];
    bookable_units: Option[];
};

export default function SpacesEdit({
    venue,
    space,
    kinds,
    parents,
    bookable_units,
}: Props) {
    const retire = () => {
        if (
            confirm(
                `Retire "${space.name}"? It will no longer be bookable. This can't be undone from the UI.`,
            )
        ) {
            router.delete(destroy(space.id).url, { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title={`Edit ${space.name}`} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <header className="flex flex-col gap-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Edit space
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {space.name} · {venue.name}
                    </p>
                </header>

                <Form
                    {...update.form(space.id)}
                    className="flex max-w-3xl flex-col gap-6"
                    options={{ preserveScroll: true }}
                >
                    {({ processing, errors }) => (
                        <>
                            <SpaceFormFields
                                errors={errors}
                                kinds={kinds}
                                parents={parents}
                                bookableUnits={bookable_units}
                                space={space}
                            />

                            <div className="flex items-center gap-3">
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    data-tour-id="space-submit"
                                >
                                    {processing && <Spinner />}
                                    Save changes
                                </Button>
                                <Link
                                    href={venueShow(venue.slug).url}
                                    className="text-sm text-muted-foreground hover:text-foreground"
                                >
                                    Cancel
                                </Link>
                                <button
                                    type="button"
                                    onClick={retire}
                                    className="ml-auto text-sm text-rose-600 hover:underline dark:text-rose-400"
                                    data-tour-id="space-retire"
                                >
                                    Retire space
                                </button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

SpacesEdit.layout = {
    breadcrumbs: [
        { title: 'Venues', href: '/venues' },
        { title: 'Edit space', href: '#' },
    ],
};
