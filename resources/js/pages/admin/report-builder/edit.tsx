import { Head } from '@inertiajs/react';
import { ReportBuilderForm } from '@/components/admin/report-builder-form';
import type {
    CatalogEntry,
    Definition,
} from '@/components/admin/report-builder-form';

type Props = { catalog: CatalogEntry[]; definition: Definition };

export default function ReportBuilderEdit({ catalog, definition }: Props) {
    return (
        <>
            <Head title={`Edit ${definition.name}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <header>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Edit report
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {definition.name}
                    </p>
                </header>

                <ReportBuilderForm
                    initial={definition}
                    catalog={catalog}
                    submitUrl={`/admin/report-builder/${definition.slug}`}
                    submitMethod="put"
                    submitLabel="Save changes"
                />
            </div>
        </>
    );
}

ReportBuilderEdit.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin/report-builder' },
        { title: 'Report builder', href: '/admin/report-builder' },
        { title: 'Edit', href: '#' },
    ],
};
