import { Head, Link, router } from '@inertiajs/react';
import HelpLink from '@/components/help-link';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { create, edit, destroy } from '@/routes/admin/rate-packages';

type Pkg = {
    id: number;
    name: string;
    kind: string;
    kind_label: string;
    price: number;
    effective_from: string;
    effective_to: string | null;
    is_active: boolean;
    venue: { id: number; name: string } | null;
    items_count: number;
};

type Props = { packages: Pkg[] };

function money(n: number): string {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'USD',
    }).format(n);
}

export default function RatePackagesIndex({ packages }: Props) {
    const remove = (id: number, name: string) => {
        if (window.confirm(`Delete package "${name}"?`)) {
            router.delete(destroy(id).url, { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title="Packages · Admin" />
            <div className="p-4">
                <div className="mb-4 flex items-center gap-2">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Packages &amp; bundles
                    </h1>
                    <HelpLink slug="admin/pricing" />
                    <Link href={create().url} className="ml-auto">
                        <Button data-tour-id="rate-package-new">
                            New package
                        </Button>
                    </Link>
                </div>
                <p className="mb-4 max-w-2xl text-sm text-muted-foreground">
                    Named bundles sold at a single package price that include
                    multiple components (space rental, equipment, services).
                    Venue-scoped and effective-dated.
                </p>

                {packages.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No packages yet.
                    </p>
                ) : (
                    <ul className="space-y-2">
                        {packages.map((p) => (
                            <li
                                key={p.id}
                                data-tour-id="rate-package-list-filter"
                                className="flex items-center gap-3 rounded-lg border border-border bg-card p-3"
                            >
                                <div className="min-w-0">
                                    <div className="flex items-center gap-2">
                                        <span className="font-medium">
                                            {p.name}
                                        </span>
                                        <Badge>{money(p.price)}</Badge>
                                        <Badge variant="outline">
                                            {p.kind_label}
                                        </Badge>
                                        {!p.is_active && (
                                            <Badge variant="secondary">
                                                Inactive
                                            </Badge>
                                        )}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {p.venue?.name ?? '-'} · effective{' '}
                                        {p.effective_from}
                                        {p.effective_to
                                            ? ` - ${p.effective_to}`
                                            : ' onward'}{' '}
                                        · {p.items_count} item
                                        {p.items_count === 1 ? '' : 's'}
                                    </div>
                                </div>
                                <div className="ml-auto flex gap-2">
                                    <Link href={edit(p.id).url}>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            data-tour-id="rate-package-edit-button"
                                        >
                                            Edit
                                        </Button>
                                    </Link>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        onClick={() => remove(p.id, p.name)}
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

RatePackagesIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin/rate-packages' },
        { title: 'Packages', href: '/admin/rate-packages' },
    ],
};
