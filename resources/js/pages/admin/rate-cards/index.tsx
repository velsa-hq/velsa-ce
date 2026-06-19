import { Head, Link, router } from '@inertiajs/react';
import HelpLink from '@/components/help-link';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { create, edit, destroy } from '@/routes/admin/rate-cards';

type Card = {
    id: number;
    name: string;
    kind: string;
    kind_label: string;
    effective_from: string;
    effective_to: string | null;
    is_active: boolean;
    venue: { id: number; name: string } | null;
    entries_count: number;
};

type Props = { cards: Card[] };

export default function RateCardsIndex({ cards }: Props) {
    const remove = (id: number, name: string) => {
        if (window.confirm(`Delete rate card "${name}"?`)) {
            router.delete(destroy(id).url, { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title="Rate cards · Admin" />
            <div className="p-4">
                <div className="mb-4 flex items-center gap-2">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Rate cards
                    </h1>
                    <HelpLink slug="admin/pricing" />
                    <Link href={create().url} className="ml-auto">
                        <Button data-tour-id="rate-card-new">
                            New rate card
                        </Button>
                    </Link>
                </div>
                <p className="mb-4 max-w-2xl text-sm text-muted-foreground">
                    Venue-scoped, effective-dated price lists for space rental
                    (by hour, day, multi-day, or time-slot) and equipment, by
                    client/season kind.
                </p>

                {cards.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No rate cards yet.
                    </p>
                ) : (
                    <ul className="space-y-2">
                        {cards.map((c) => (
                            <li
                                key={c.id}
                                data-tour-id="rate-card-list-filter"
                                className="flex items-center gap-3 rounded-lg border border-border bg-card p-3"
                            >
                                <div className="min-w-0">
                                    <div className="flex items-center gap-2">
                                        <span className="font-medium">
                                            {c.name}
                                        </span>
                                        <Badge variant="outline">
                                            {c.kind_label}
                                        </Badge>
                                        {!c.is_active && (
                                            <Badge variant="secondary">
                                                Inactive
                                            </Badge>
                                        )}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {c.venue?.name ?? '-'} · effective{' '}
                                        {c.effective_from}
                                        {c.effective_to
                                            ? ` - ${c.effective_to}`
                                            : ' onward'}{' '}
                                        · {c.entries_count} entr
                                        {c.entries_count === 1 ? 'y' : 'ies'}
                                    </div>
                                </div>
                                <div className="ml-auto flex gap-2">
                                    <Link href={edit(c.id).url}>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            data-tour-id="rate-card-edit-button"
                                        >
                                            Edit
                                        </Button>
                                    </Link>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        onClick={() => remove(c.id, c.name)}
                                    >
                                        Delete
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

RateCardsIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin/rate-cards' },
        { title: 'Rate cards', href: '/admin/rate-cards' },
    ],
};
