import { Head, Link } from '@inertiajs/react';

type Handler = {
    slug: string;
    title: string;
    description: string;
};

type Group = {
    category: string;
    handlers: Handler[];
};

type Props = {
    groups: Group[];
};

export default function ReportsIndex({ groups }: Props) {
    const totalReports = groups.reduce((acc, g) => acc + g.handlers.length, 0);

    return (
        <>
            <Head title="Reports" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <header className="flex flex-col gap-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Reports
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {totalReports} reports available · click any to run with
                        filters and download as CSV
                    </p>
                </header>

                {groups.map((group) => (
                    <section
                        key={group.category}
                        className="flex flex-col gap-2"
                    >
                        <h2 className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                            {group.category}
                        </h2>
                        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                            {group.handlers.map((handler) => (
                                <Link
                                    key={handler.slug}
                                    href={`/reports/${handler.slug}`}
                                    className="flex flex-col gap-1 rounded-xl border border-sidebar-border/70 bg-background p-4 transition-colors hover:bg-muted dark:border-sidebar-border"
                                >
                                    <h3 className="text-sm font-semibold">
                                        {handler.title}
                                    </h3>
                                    <p className="text-xs text-muted-foreground">
                                        {handler.description}
                                    </p>
                                </Link>
                            ))}
                        </div>
                    </section>
                ))}
            </div>
        </>
    );
}

ReportsIndex.layout = {
    breadcrumbs: [{ title: 'Reports', href: '/reports' }],
};
