import { Head } from '@inertiajs/react';
import { TaxonomyAdmin } from '@/components/admin/taxonomy-admin';
import type { TaxonomyItem } from '@/components/admin/taxonomy-admin';
import {
    destroy,
    move,
    store,
    toggle,
    update,
} from '@/routes/admin/inventory-kinds';

type Props = { items: TaxonomyItem[]; colors: string[] };

export default function InventoryKindsIndex({ items, colors }: Props) {
    return (
        <>
            <Head title="Inventory kinds · Admin" />
            <TaxonomyAdmin
                title="Inventory kinds"
                description={
                    <>
                        The groupings used to classify{' '}
                        <strong>inventory</strong> resources (chairs, tables,
                        A/V...). Drives the <strong>Kind</strong> dropdown on
                        the resource form. <strong>Hide</strong> kinds you don't
                        use - they drop out of the picker but stay on any
                        resources already using them. System defaults can be
                        hidden, renamed, and reordered but not deleted.
                    </>
                }
                items={items}
                colors={colors}
                routes={{ store, update, toggle, move, destroy }}
                usageLabel="Resources"
                addPlaceholder="e.g. Drape"
                tourPrefix="inventory-kind"
                systemDeleteHint="System kinds can't be deleted - hide it instead"
                inUseDeleteHint="In use by resources"
            />
        </>
    );
}

InventoryKindsIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '#' },
        { title: 'Inventory kinds', href: '/admin/inventory-kinds' },
    ],
};
