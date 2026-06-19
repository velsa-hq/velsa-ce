import { Head } from '@inertiajs/react';
import { TaxonomyAdmin } from '@/components/admin/taxonomy-admin';
import type { TaxonomyItem } from '@/components/admin/taxonomy-admin';
import {
    destroy,
    move,
    store,
    toggle,
    update,
} from '@/routes/admin/space-kinds';

type Props = { items: TaxonomyItem[]; colors: string[] };

export default function SpaceKindsIndex({ items, colors }: Props) {
    return (
        <>
            <Head title="Space kinds · Admin" />
            <TaxonomyAdmin
                title="Space kinds"
                description={
                    <>
                        The taxonomy used to classify spaces (room, ballroom,
                        arena...). Drives the space form and the Find-a-space
                        filter. <strong>Hide</strong> kinds your org doesn't use
                        - they drop out of the pickers but stay on any spaces
                        already using them. System defaults can be hidden,
                        renamed, and reordered but not deleted.
                    </>
                }
                items={items}
                colors={colors}
                routes={{ store, update, toggle, move, destroy }}
                usageLabel="In use"
                addPlaceholder="e.g. Chapel"
                tourPrefix="space-kind"
                systemDeleteHint="System kinds can't be deleted - hide it instead"
                inUseDeleteHint="In use by spaces"
            />
        </>
    );
}

SpaceKindsIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '#' },
        { title: 'Space kinds', href: '/admin/space-kinds' },
    ],
};
