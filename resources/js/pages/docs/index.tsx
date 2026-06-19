import { Head, Link, usePage } from '@inertiajs/react';
import {
    BarChart3,
    BookOpen,
    Building2,
    Calculator,
    CalendarCheck,
    FileSignature,
    KanbanSquare,
    PenTool,
    Rocket,
    Scale,
    Settings,
    Store,
    TrendingUp,
    Users,
    Wrench,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';
import { show } from '@/routes/docs';

type NavDoc = { slug: string; title: string; order: number };
type NavSection = { section: string; docs: NavDoc[] };

type Props = {
    nav: NavSection[];
    first_slug: string | null;
};

// per-section icon; unlisted sections fall back to a generic book
const SECTION_ICONS: Record<string, LucideIcon> = {
    'Getting started': Rocket,
    Bookings: CalendarCheck,
    Accounting: Calculator,
    Admin: Settings,
    Operations: Wrench,
    Exhibitors: Store,
    Contracts: FileSignature,
    Venues: Building2,
    Reports: BarChart3,
    Sales: TrendingUp,
    Pipeline: KanbanSquare,
    Legal: Scale,
    Diagrams: PenTool,
    Clients: Users,
};

export default function DocsIndex({ nav, first_slug }: Props) {
    // brand name from HandleInertiaRequests (branding.app_name), falls back to configured name
    const name =
        (usePage().props as unknown as { name?: string }).name ?? 'Velsa';

    return (
        <>
            <Head title="Handbook" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <header className="flex flex-col gap-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Handbook
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Operating manual for {name}.
                        {first_slug ? (
                            <>
                                {' '}
                                Pick a topic below, or{' '}
                                <Link
                                    href={show(first_slug).url}
                                    className="font-medium text-primary hover:underline"
                                >
                                    start at the beginning
                                </Link>
                                .
                            </>
                        ) : null}
                    </p>
                </header>

                {nav.length === 0 ? (
                    <Card>
                        <CardContent className="p-6 text-sm text-muted-foreground">
                            No handbook pages have been written yet. Drop
                            markdown files into <code>docs/handbook/</code>.
                        </CardContent>
                    </Card>
                ) : (
                    // masonry-style columns so short sections don't stretch to match tall ones
                    <div className="columns-1 gap-4 sm:columns-2 lg:columns-3">
                        {nav.map((section) => {
                            const Icon =
                                SECTION_ICONS[section.section] ?? BookOpen;

                            return (
                                <Card
                                    key={section.section}
                                    className="mb-4 break-inside-avoid"
                                >
                                    <CardContent className="flex flex-col gap-3 p-4">
                                        <div className="flex items-center gap-2">
                                            <span className="grid size-8 shrink-0 place-items-center rounded-md bg-primary/10 text-primary">
                                                <Icon className="size-4" />
                                            </span>
                                            <h2 className="text-sm font-semibold">
                                                {section.section}
                                            </h2>
                                        </div>
                                        <ul className="flex flex-col gap-1 text-sm">
                                            {section.docs.map((doc) => (
                                                <li key={doc.slug}>
                                                    <Link
                                                        href={
                                                            show(doc.slug).url
                                                        }
                                                        className="text-muted-foreground hover:text-foreground hover:underline"
                                                    >
                                                        {doc.title}
                                                    </Link>
                                                </li>
                                            ))}
                                        </ul>
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                )}
            </div>
        </>
    );
}

DocsIndex.layout = {
    breadcrumbs: [{ title: 'Handbook', href: '/docs' }],
};
