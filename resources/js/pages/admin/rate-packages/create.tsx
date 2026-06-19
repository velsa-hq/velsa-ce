import { Head } from '@inertiajs/react';
import { store } from '@/routes/admin/rate-packages';
import RatePackageForm from './_form';
import type { RatePackageFormData } from './_form';

type Option = { value: number; label: string };
type SpaceOption = Option & { venue_id: number };
type StrOption = { value: string; label: string };

type Props = {
    venues: Option[];
    spaces: SpaceOption[];
    equipment: StrOption[];
    kinds: StrOption[];
    units: StrOption[];
};

export default function RatePackagesCreate(props: Props) {
    const initial: RatePackageFormData = {
        venue_id: '',
        name: '',
        kind: props.kinds[0]?.value ?? 'standard',
        price: '',
        effective_from: new Date().toISOString().slice(0, 10),
        effective_to: '',
        is_active: true,
        description: '',
        items: [],
    };

    return (
        <>
            <Head title="New package · Admin" />
            <div className="mx-auto w-full max-w-3xl p-4">
                <h1 className="mb-6 text-2xl font-semibold tracking-tight">
                    New package
                </h1>
                <RatePackageForm
                    {...props}
                    initial={initial}
                    submitUrl={store().url}
                    method="post"
                    submitLabel="Create package"
                />
            </div>
        </>
    );
}

RatePackagesCreate.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin/rate-packages' },
        { title: 'Packages', href: '/admin/rate-packages' },
        { title: 'New', href: '#' },
    ],
};
