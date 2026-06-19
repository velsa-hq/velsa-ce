import { Head } from '@inertiajs/react';
import { TemplateForm } from './_form';
import type { FormOption, VenueOption } from './_form';

type Template = {
    id: number;
    name: string;
    kind: string;
    venue_id: number | null;
    body_html: string;
    is_active: boolean;
    version: number;
    contracts_count: number;
};

type Props = {
    template: Template;
    kinds: FormOption[];
    venues: VenueOption[];
};

export default function DocumentTemplateEdit({
    template,
    kinds,
    venues,
}: Props) {
    return (
        <>
            <Head title={`${template.name} · Admin`} />
            <TemplateForm
                initial={{
                    kind: template.kind,
                    venue_id: template.venue_id,
                    name: template.name,
                    body_html: template.body_html,
                    is_active: template.is_active,
                }}
                kinds={kinds}
                venues={venues}
                submitUrl={`/admin/document-templates/${template.id}`}
                method="put"
                headline={`Edit ${template.name} · v${template.version}`}
                submitLabel="Save template"
            />
        </>
    );
}

DocumentTemplateEdit.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin' },
        { title: 'Document templates', href: '/admin/document-templates' },
        { title: 'Edit', href: '#' },
    ],
};
