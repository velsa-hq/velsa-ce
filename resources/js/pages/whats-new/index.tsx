import { Head } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

type Release = {
    version: string;
    date: string;
    title: string;
    summary: string;
    html: string;
};

type Props = {
    releases: Release[];
};

const PROSE = cn(
    'max-w-none text-sm leading-relaxed text-foreground',
    '[&_h3]:mt-5 [&_h3]:mb-2 [&_h3]:text-sm [&_h3]:font-semibold [&_h3]:tracking-wide [&_h3]:text-muted-foreground [&_h3]:uppercase',
    '[&_p]:my-3',
    '[&_a]:text-primary [&_a]:underline [&_a:hover]:opacity-80',
    '[&_strong]:font-semibold',
    '[&_ul]:my-3 [&_ul]:list-disc [&_ul]:pl-6',
    '[&_li]:my-1',
    '[&_hr]:my-6 [&_hr]:border-border',
);

function formatDate(iso: string): string {
    return new Date(`${iso}T00:00:00`).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
}

export default function WhatsNewIndex({ releases }: Props) {
    return (
        <>
            <Head title="What's New" />
            <div
                className="mx-auto w-full max-w-3xl p-4"
                data-tour-id="whats-new-feed"
            >
                <div className="mb-8">
                    <h1 className="text-3xl font-semibold tracking-tight">
                        What's New
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        New features, improvements, and fixes in each release.
                    </p>
                </div>

                {releases.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No release notes are available yet.
                    </p>
                ) : (
                    <ol className="space-y-10">
                        {releases.map((release) => (
                            <li
                                key={release.version}
                                data-tour-id="whats-new-entry"
                                className="border-l-2 border-border pl-6"
                            >
                                <div className="mb-1 flex items-center gap-2">
                                    <Badge variant="secondary">
                                        v{release.version}
                                    </Badge>
                                    <span className="text-xs text-muted-foreground">
                                        {formatDate(release.date)}
                                    </span>
                                </div>
                                <h2 className="text-xl font-semibold tracking-tight">
                                    {release.title}
                                </h2>
                                {release.summary && (
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {release.summary}
                                    </p>
                                )}
                                <div
                                    className={cn(PROSE, 'mt-4')}
                                    /* Trusted: rendered from first-party release-note
                                       markdown shipped in docs/whats-new/, not user input. */
                                    dangerouslySetInnerHTML={{
                                        __html: release.html,
                                    }}
                                />
                            </li>
                        ))}
                    </ol>
                )}
            </div>
        </>
    );
}

WhatsNewIndex.layout = {
    breadcrumbs: [{ title: "What's New", href: '/whats-new' }],
};
