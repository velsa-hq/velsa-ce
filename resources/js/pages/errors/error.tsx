import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { dashboard, home } from '@/routes';

type ErrorCopy = {
    title: string;
    message: string;
};

// copy per HTTP status; kept client-side so the page renders even when the db or boot is down
const COPY: Record<number, ErrorCopy> = {
    403: {
        title: 'Access denied',
        message:
            "You're signed in, but your role doesn't have permission for this area. If you believe this is a mistake, contact an administrator.",
    },
    404: {
        title: 'Not found',
        message:
            "We couldn't find that page or record - it may have been moved or removed.",
    },
    419: {
        title: 'Session expired',
        message:
            'Your session timed out for security. Refresh the page and sign in again to continue.',
    },
    429: {
        title: 'Too many requests',
        message:
            "You've made too many attempts in a short window. Please wait a moment and try again.",
    },
    500: {
        title: 'Something went wrong',
        message:
            'An unexpected error occurred on our end. It has been logged and the team has been notified.',
    },
    503: {
        title: 'Down for maintenance',
        message:
            'Velsa is briefly unavailable while we perform maintenance. Please try again in a few minutes.',
    },
};

const FALLBACK: ErrorCopy = {
    title: 'Something went wrong',
    message: 'An unexpected error occurred. Please try again.',
};

export default function ErrorPage({ status }: { status: number }) {
    const copy = COPY[status] ?? FALLBACK;
    const reloadable = status === 419 || status === 429 || status === 503;

    return (
        <div className="flex min-h-svh flex-col items-center justify-center gap-8 bg-background p-6 text-center">
            <Head title={`${status} - ${copy.title}`} />

            <Link
                href={home()}
                className="flex items-center justify-center rounded-full bg-primary p-3 shadow-lg ring-1 ring-black/5"
            >
                <img src="/favicon.svg" alt="Velsa" className="size-16" />
            </Link>

            <div className="flex flex-col items-center gap-3">
                <p className="text-5xl font-semibold tracking-tight text-primary">
                    {status}
                </p>
                <h1 className="text-xl font-semibold tracking-tight">
                    {copy.title}
                </h1>
                <p className="max-w-md text-sm leading-relaxed text-muted-foreground">
                    {copy.message}
                </p>
            </div>

            <div className="flex items-center gap-3">
                {reloadable ? (
                    <Button onClick={() => window.location.reload()}>
                        Try again
                    </Button>
                ) : (
                    <Button asChild>
                        <Link href={dashboard()}>Back to dashboard</Link>
                    </Button>
                )}
            </div>
        </div>
    );
}
