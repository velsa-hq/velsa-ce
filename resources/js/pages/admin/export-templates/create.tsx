import { Head } from '@inertiajs/react';
import { ExportTemplateForm } from '@/components/admin/export-template-form';
import type {
    Metadata,
    TemplateDraft,
} from '@/components/admin/export-template-form';
import { store } from '@/routes/admin/export-templates';

type Props = {
    metadata: Metadata;
    template: TemplateDraft;
};

export default function ExportTemplatesCreate({ metadata, template }: Props) {
    return (
        <>
            <Head title="New export template" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <header className="flex flex-col gap-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        New export template
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Define columns, format masks, and packaging. The export
                        will use this shape when an admin triggers an export
                        with this template selected.
                    </p>
                </header>

                <ExportTemplateForm
                    initial={template}
                    metadata={metadata}
                    submitUrl={store().url}
                    submitMethod="post"
                    submitLabel="Create template"
                />
            </div>
        </>
    );
}

ExportTemplatesCreate.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin/export-templates' },
        { title: 'Export templates', href: '/admin/export-templates' },
        { title: 'New', href: '#' },
    ],
};
