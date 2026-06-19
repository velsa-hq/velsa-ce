import { Form, Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/spaces';
import { show as venueShow } from '@/routes/venues';
import { SpaceFormFields } from './_form';
import type { Option, ParentOption } from './_form';

type Venue = { id: number; slug: string; name: string };

type Props = {
    venue: Venue;
    kinds: Option[];
    parents: ParentOption[];
    bookable_units: Option[];
};

export default function SpacesCreate({
    venue,
    kinds,
    parents,
    bookable_units,
}: Props) {
    return (
        <>
            <Head title={`Add space · ${venue.name}`} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <header className="flex flex-col gap-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Add a space
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {venue.name}
                    </p>
                </header>

                <Form
                    {...store.form(venue.slug)}
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
                            />

                            <div className="flex items-center gap-3">
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    data-tour-id="space-submit"
                                >
                                    {processing && <Spinner />}
                                    Add space
                                </Button>
                                <Link
                                    href={venueShow(venue.slug).url}
                                    className="text-sm text-muted-foreground hover:text-foreground"
                                >
                                    Cancel
                                </Link>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

SpacesCreate.layout = {
    breadcrumbs: [
        { title: 'Venues', href: '/venues' },
        { title: 'Add space', href: '#' },
    ],
};
