import { Head } from '@inertiajs/react';
import { ExportTemplateForm } from '@/components/admin/export-template-form';
import type {
    Metadata,
    TemplateDraft,
} from '@/components/admin/export-template-form';
import { update } from '@/routes/admin/export-templates';

type Props = {
    metadata: Metadata;
    template: TemplateDraft & { slug: string };
    preview: string;
};

export default function ExportTemplatesEdit({
    metadata,
    template,
    preview,
}: Props) {
    return (
        <>
            <Head title={`Edit ${template.name}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <header className="flex flex-col gap-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Edit export template
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {template.name} ·{' '}
                        <span className="font-mono">{template.slug}</span>
                    </p>
                </header>

                <ExportTemplateForm
                    initial={template}
                    metadata={metadata}
                    initialPreview={preview}
                    submitUrl={update({ template: template.slug }).url}
                    submitMethod="put"
                    submitLabel="Save changes"
                />
            </div>
        </>
    );
}

ExportTemplatesEdit.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin/export-templates' },
        { title: 'Export templates', href: '/admin/export-templates' },
        { title: 'Edit', href: '#' },
    ],
};
