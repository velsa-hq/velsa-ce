import { Head } from '@inertiajs/react';
import { TaxonomyAdmin } from '@/components/admin/taxonomy-admin';
import type { TaxonomyItem } from '@/components/admin/taxonomy-admin';
import {
    destroy,
    move,
    store,
    toggle,
    update,
} from '@/routes/admin/departments';

type Props = { items: TaxonomyItem[]; colors: string[]; roles: string[] };

export default function DepartmentsIndex({ items, colors, roles }: Props) {
    return (
        <>
            <Head title="Departments · Admin" />
            <TaxonomyAdmin
                title="Departments"
                description={
                    <>
                        The operations departments that run-of-show outline
                        items are bucketed into and the Ops board is sliced by
                        (Setup, A/V, Catering...). Each has a color used for its
                        chips on the board. <strong>Hide</strong> departments
                        your org doesn't use - they drop out of the pickers,
                        board columns, and report filters but stay on any items
                        already using them. System defaults can be hidden,
                        renamed, recolored, and reordered but not deleted.
                    </>
                }
                items={items}
                colors={colors}
                routes={{ store, update, toggle, move, destroy }}
                usageLabel="In use"
                addPlaceholder="e.g. Medical"
                tourPrefix="department"
                extraField={{
                    name: 'default_role',
                    label: 'Default crew role',
                    options: roles,
                    emptyLabel: '- Unassigned -',
                }}
                systemDeleteHint="System departments can't be deleted - hide it instead"
                inUseDeleteHint="In use by outline items"
            />
        </>
    );
}

DepartmentsIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '#' },
        { title: 'Departments', href: '/admin/departments' },
    ],
};
