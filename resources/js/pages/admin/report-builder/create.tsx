import { Head } from '@inertiajs/react';
import { ReportBuilderForm } from '@/components/admin/report-builder-form';
import type {
    CatalogEntry,
    Definition,
} from '@/components/admin/report-builder-form';

type Props = { catalog: CatalogEntry[]; definition: Definition };

export default function ReportBuilderCreate({ catalog, definition }: Props) {
    return (
        <>
            <Head title="New report · Admin" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <header>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        New report
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Build a report from any of the available datasources.
                        Saved reports appear in the main Reports list.
                    </p>
                </header>

                <ReportBuilderForm
                    initial={definition}
                    catalog={catalog}
                    submitUrl="/admin/report-builder"
                    submitMethod="post"
                    submitLabel="Create report"
                />
            </div>
        </>
    );
}

ReportBuilderCreate.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin/report-builder' },
        { title: 'Report builder', href: '/admin/report-builder' },
        { title: 'New', href: '#' },
    ],
};
