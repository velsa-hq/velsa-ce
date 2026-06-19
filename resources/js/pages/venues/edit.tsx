import { Form, Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { show, update } from '@/routes/venues';
import { VenueFields } from './_form';
import type { VenueDefaults } from './_form';

type Venue = VenueDefaults & { id: number; slug: string; name: string };

type Props = { venue: Venue };

export default function VenuesEdit({ venue }: Props) {
    return (
        <>
            <Head title={`Edit ${venue.name}`} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <header className="flex flex-col gap-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Edit venue
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {venue.name}
                    </p>
                </header>

                <Form
                    {...update.form(venue.slug)}
                    className="flex max-w-3xl flex-col gap-6"
                    options={{ preserveScroll: true }}
                >
                    {({ processing, errors }) => (
                        <>
                            <VenueFields errors={errors} venue={venue} />

                            <div className="flex items-center gap-3">
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    data-tour-id="venue-submit"
                                >
                                    {processing && <Spinner />}
                                    Save changes
                                </Button>
                                <Link
                                    href={show(venue.slug).url}
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

VenuesEdit.layout = {
    breadcrumbs: [
        { title: 'Venues', href: '/venues' },
        { title: 'Edit', href: '#' },
    ],
};
