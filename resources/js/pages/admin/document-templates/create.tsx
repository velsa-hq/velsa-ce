import { Head } from '@inertiajs/react';
import { TemplateForm } from './_form';
import type { FormOption, VenueOption } from './_form';

type Props = {
    kinds: FormOption[];
    venues: VenueOption[];
    preselect_kind: string;
};

export default function DocumentTemplateCreate({
    kinds,
    venues,
    preselect_kind,
}: Props) {
    return (
        <>
            <Head title="New template · Admin" />
            <TemplateForm
                initial={{ kind: preselect_kind }}
                kinds={kinds}
                venues={venues}
                submitUrl="/admin/document-templates"
                method="post"
                headline="New document template"
                submitLabel="Create template"
            />
        </>
    );
}

DocumentTemplateCreate.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin' },
        { title: 'Document templates', href: '/admin/document-templates' },
        { title: 'New', href: '#' },
    ],
};
