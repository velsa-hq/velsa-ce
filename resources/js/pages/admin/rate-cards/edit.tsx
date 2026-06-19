import { Head } from '@inertiajs/react';
import { update } from '@/routes/admin/rate-cards';
import RateCardForm from './_form';
import type { RateCardFormData } from './_form';

type Option = { value: number; label: string };
type SpaceOption = Option & { venue_id: number };
type StrOption = { value: string; label: string };

type Entry = {
    kind: 'space' | 'equipment';
    space_id: number | null;
    equipment_sku: string | null;
    unit: string | null;
    rate: number;
    min_charge: number;
    included_hours: number | null;
};

type Card = {
    id: number;
    name: string;
    kind: string;
    effective_from: string;
    effective_to: string | null;
    is_active: boolean;
    notes: string | null;
    venue_id: number;
    entries: Entry[];
};

type Props = {
    card: Card;
    venues: Option[];
    spaces: SpaceOption[];
    equipment: StrOption[];
    kinds: StrOption[];
    units: StrOption[];
};

export default function RateCardsEdit({ card, ...props }: Props) {
    const initial: RateCardFormData = {
        venue_id: card.venue_id,
        name: card.name,
        kind: card.kind,
        effective_from: card.effective_from,
        effective_to: card.effective_to ?? '',
        is_active: card.is_active,
        notes: card.notes ?? '',
        entries: card.entries.map((e) => ({
            kind: e.kind,
            space_id: e.space_id ?? '',
            equipment_sku: e.equipment_sku ?? '',
            unit: e.unit ?? props.units[0]?.value ?? 'hourly',
            rate: String(e.rate),
            min_charge: String(e.min_charge),
            included_hours:
                e.included_hours !== null ? String(e.included_hours) : '',
        })),
    };

    return (
        <>
            <Head title={`Edit ${card.name} · Admin`} />
            <div className="mx-auto w-full max-w-3xl p-4">
                <h1 className="mb-6 text-2xl font-semibold tracking-tight">
                    Edit rate card
                </h1>
                <RateCardForm
                    {...props}
                    initial={initial}
                    submitUrl={update(card.id).url}
                    method="put"
                    submitLabel="Save rate card"
                />
            </div>
        </>
    );
}

RateCardsEdit.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin/rate-cards' },
        { title: 'Rate cards', href: '/admin/rate-cards' },
        { title: 'Edit', href: '#' },
    ],
};
