import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

type Props = {
    venue_name: string;
    handbook_html: string;
    acknowledged_at: string | null;
};

export default function PortalHandbook({
    venue_name,
    handbook_html,
    acknowledged_at,
}: Props) {
    const [acknowledging, setAcknowledging] = useState(false);

    const acknowledge = () => {
        setAcknowledging(true);
        router.post(
            '/portal/handbook/acknowledge',
            {},
            {
                preserveScroll: true,
                onFinish: () => setAcknowledging(false),
            },
        );
    };

    return (
        <>
            <Head title="Exhibitor handbook" />

            <div className="flex flex-col gap-6">
                <header>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {venue_name} - exhibitor handbook
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Please review the venue's rules and policies, then
                        acknowledge below.
                    </p>
                </header>

                <article
                    className="prose prose-sm dark:prose-invert max-w-none rounded-xl border border-border bg-card p-5"
                    dangerouslySetInnerHTML={{ __html: handbook_html }}
                />

                {acknowledged_at ? (
                    <div className="rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/50 dark:bg-emerald-900/30 dark:text-emerald-100">
                        ✓ Acknowledged on{' '}
                        {new Date(acknowledged_at).toLocaleDateString(
                            undefined,
                            {
                                month: 'long',
                                day: 'numeric',
                                year: 'numeric',
                            },
                        )}
                        .
                    </div>
                ) : (
                    <div className="flex items-center gap-3">
                        <Button
                            onClick={acknowledge}
                            disabled={acknowledging}
                            data-tour-id="portal-handbook-acknowledge"
                        >
                            {acknowledging && <Spinner className="mr-2" />}I
                            acknowledge
                        </Button>
                        <span className="text-xs text-muted-foreground">
                            Confirms you've read the venue's exhibitor policies.
                        </span>
                    </div>
                )}
            </div>
        </>
    );
}
