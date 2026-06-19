import { Head } from '@inertiajs/react';
import { store } from '@/routes/admin/rate-cards';
import RateCardForm from './_form';
import type { RateCardFormData } from './_form';

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

export default function RateCardsCreate(props: Props) {
    const initial: RateCardFormData = {
        venue_id: '',
        name: '',
        kind: props.kinds[0]?.value ?? 'standard',
        effective_from: new Date().toISOString().slice(0, 10),
        effective_to: '',
        is_active: true,
        notes: '',
        entries: [],
    };

    return (
        <>
            <Head title="New rate card · Admin" />
            <div className="mx-auto w-full max-w-3xl p-4">
                <h1 className="mb-6 text-2xl font-semibold tracking-tight">
                    New rate card
                </h1>
                <RateCardForm
                    {...props}
                    initial={initial}
                    submitUrl={store().url}
                    method="post"
                    submitLabel="Create rate card"
                />
            </div>
        </>
    );
}

RateCardsCreate.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin/rate-cards' },
        { title: 'Rate cards', href: '/admin/rate-cards' },
        { title: 'New', href: '#' },
    ],
};
