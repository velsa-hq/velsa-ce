import { Form, Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { index, store } from '@/routes/venues';
import { VenueFields } from './_form';

export default function VenuesCreate() {
    return (
        <>
            <Head title="New venue" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <header className="flex flex-col gap-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        New venue
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Create a facility. You can add its spaces afterward from
                        the venue page.
                    </p>
                </header>

                <Form
                    {...store.form()}
                    className="flex max-w-3xl flex-col gap-6"
                    options={{ preserveScroll: true }}
                >
                    {({ processing, errors }) => (
                        <>
                            <VenueFields errors={errors} />

                            <div className="flex items-center gap-3">
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    data-tour-id="venue-submit"
                                >
                                    {processing && <Spinner />}
                                    Create venue
                                </Button>
                                <Link
                                    href={index().url}
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

VenuesCreate.layout = {
    breadcrumbs: [
        { title: 'Venues', href: '/venues' },
        { title: 'New', href: '#' },
    ],
};
