import { Head } from '@inertiajs/react';
import { update } from '@/routes/admin/rate-packages';
import RatePackageForm from './_form';
import type { RatePackageFormData } from './_form';

type Option = { value: number; label: string };
type SpaceOption = Option & { venue_id: number };
type StrOption = { value: string; label: string };

type Item = {
    kind: 'space' | 'equipment' | 'service';
    space_id: number | null;
    equipment_sku: string | null;
    label: string | null;
    quantity: number;
    unit: string | null;
    notes: string | null;
};

type Pkg = {
    id: number;
    name: string;
    kind: string;
    price: number;
    effective_from: string;
    effective_to: string | null;
    is_active: boolean;
    description: string | null;
    venue_id: number;
    items: Item[];
};

type Props = {
    package: Pkg;
    venues: Option[];
    spaces: SpaceOption[];
    equipment: StrOption[];
    kinds: StrOption[];
    units: StrOption[];
};

export default function RatePackagesEdit({ package: pkg, ...props }: Props) {
    const initial: RatePackageFormData = {
        venue_id: pkg.venue_id,
        name: pkg.name,
        kind: pkg.kind,
        price: String(pkg.price),
        effective_from: pkg.effective_from,
        effective_to: pkg.effective_to ?? '',
        is_active: pkg.is_active,
        description: pkg.description ?? '',
        items: pkg.items.map((i) => ({
            kind: i.kind,
            space_id: i.space_id ?? '',
            equipment_sku: i.equipment_sku ?? '',
            label: i.label ?? '',
            quantity: String(i.quantity),
            unit: i.unit ?? '',
            notes: i.notes ?? '',
        })),
    };

    return (
        <>
            <Head title={`Edit ${pkg.name} · Admin`} />
            <div className="mx-auto w-full max-w-3xl p-4">
                <h1 className="mb-6 text-2xl font-semibold tracking-tight">
                    Edit package
                </h1>
                <RatePackageForm
                    {...props}
                    initial={initial}
                    submitUrl={update(pkg.id).url}
                    method="put"
                    submitLabel="Save package"
                />
            </div>
        </>
    );
}

RatePackagesEdit.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin/rate-packages' },
        { title: 'Packages', href: '/admin/rate-packages' },
        { title: 'Edit', href: '#' },
    ],
};
