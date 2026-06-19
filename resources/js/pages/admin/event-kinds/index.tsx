import { Head } from '@inertiajs/react';
import { TaxonomyAdmin } from '@/components/admin/taxonomy-admin';
import type { TaxonomyItem } from '@/components/admin/taxonomy-admin';
import {
    destroy,
    move,
    store,
    toggle,
    update,
} from '@/routes/admin/event-kinds';

type Props = { items: TaxonomyItem[]; colors: string[] };

export default function EventKindsIndex({ items, colors }: Props) {
    return (
        <>
            <Head title="Event kinds · Admin" />
            <TaxonomyAdmin
                title="Event kinds"
                description={
                    <>
                        The taxonomy used to classify bookings (wedding,
                        conference, expo...). Drives the{' '}
                        <strong>Event kind</strong> dropdown on the booking
                        form. <strong>Hide</strong> kinds your org doesn't run -
                        they drop out of the picker but stay on any bookings
                        already using them. System defaults can be hidden,
                        renamed, and reordered but not deleted.
                    </>
                }
                items={items}
                colors={colors}
                routes={{ store, update, toggle, move, destroy }}
                usageLabel="Bookings"
                addPlaceholder="e.g. Gala"
                tourPrefix="event-kind"
                systemDeleteHint="System kinds can't be deleted - hide it instead"
                inUseDeleteHint="In use by bookings"
            />
        </>
    );
}

EventKindsIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '#' },
        { title: 'Event kinds', href: '/admin/event-kinds' },
    ],
};
