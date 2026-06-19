import { Head, Link, usePage } from '@inertiajs/react';
import { useRandomBackground } from '@/lib/random-background';
import { dashboard, licenses, login } from '@/routes';

type Branding = {
    app_name: string | null;
    app_title: string | null;
    app_subtitle: string | null;
    app_tagline: string | null;
    logo_path: string | null;
    logo_alt: string | null;
};

export default function Welcome() {
    const { auth, branding } = usePage().props as unknown as {
        auth: { user: { name: string } | null };
        branding: Branding;
    };
    const background = useRandomBackground();

    const appName = branding?.app_name ?? 'Velsa';
    const appTitle = branding?.app_title ?? '';
    const appSubtitle = branding?.app_subtitle ?? 'Velsa';
    const appTagline =
        branding?.app_tagline ??
        'One system for venues, bookings, contracts, and operations.';
    const logoPath = branding?.logo_path ?? '/favicon.svg';
    const logoAlt = branding?.logo_alt ?? 'Organization seal';

    return (
        <>
            <Head title={appName} />
            <div className="relative isolate flex min-h-screen flex-col text-foreground">
                {background ? (
                    <>
                        <div
                            className="pointer-events-none fixed inset-0 -z-20 bg-cover bg-center"
                            style={{ backgroundImage: `url('${background}')` }}
                            aria-hidden
                        />
                        <div
                            className="pointer-events-none fixed inset-0 -z-10 bg-background/65 backdrop-blur-[2px]"
                            aria-hidden
                        />
                    </>
                ) : (
                    <div
                        className="fixed inset-0 -z-10 bg-background"
                        aria-hidden
                    />
                )}
                <header className="border-b border-border">
                    <nav className="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-6 py-4">
                        <div className="flex items-center gap-2 text-sm font-medium">
                            <img
                                src={logoPath}
                                alt={logoAlt}
                                className="size-8 rounded"
                            />
                            {appTitle && (
                                <span className="hidden sm:inline">
                                    {appTitle}
                                </span>
                            )}
                        </div>
                        <div className="flex items-center gap-2">
                            <Link
                                href="/portal/access"
                                className="hidden rounded-md px-4 py-1.5 text-sm font-medium text-muted-foreground hover:text-foreground sm:inline"
                            >
                                Exhibitor portal
                            </Link>
                            {auth.user ? (
                                <Link
                                    href={dashboard()}
                                    className="rounded-md bg-primary px-4 py-1.5 text-sm font-medium text-primary-foreground hover:opacity-90"
                                >
                                    Open dashboard
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={login()}
                                        className="rounded-md px-4 py-1.5 text-sm font-medium text-foreground hover:bg-muted"
                                    >
                                        Sign in
                                    </Link>
                                </>
                            )}
                        </div>
                    </nav>
                </header>

                <main className="mx-auto flex w-full max-w-5xl flex-1 flex-col items-center justify-center gap-10 px-6 py-16 text-center">
                    <img
                        src={logoPath}
                        alt={logoAlt}
                        className="size-24 rounded-full bg-white/95 p-3 shadow-lg ring-1 ring-black/5 lg:size-32"
                    />

                    <div className="flex flex-col gap-3">
                        <h1 className="text-3xl font-semibold tracking-tight sm:text-4xl">
                            {appSubtitle}
                        </h1>
                        <p className="max-w-2xl text-base text-muted-foreground sm:text-lg">
                            {appTagline}
                        </p>
                    </div>

                    <div className="grid w-full max-w-3xl gap-3 text-left sm:grid-cols-3">
                        <div className="rounded-lg border border-border bg-card/80 p-4 backdrop-blur">
                            <h3 className="text-sm font-semibold">
                                Every venue, one calendar
                            </h3>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Booked space, holds, and conflicts at a glance
                                across every facility you operate.
                            </p>
                        </div>
                        <div className="rounded-lg border border-border bg-card/80 p-4 backdrop-blur">
                            <h3 className="text-sm font-semibold">
                                Sales through settlement
                            </h3>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Pipeline, contracts, exhibitor orders, payments,
                                and GL-ready journal exports - end to end.
                            </p>
                        </div>
                        <div className="rounded-lg border border-border bg-card/80 p-4 backdrop-blur">
                            <h3 className="text-sm font-semibold">
                                Ops in real time
                            </h3>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Run-of-show outlines, drag-drop floor plans,
                                recurring preventive maintenance, and a 2-week
                                ops board.
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-col items-center gap-2">
                        {auth.user ? (
                            <Link
                                href={dashboard()}
                                className="rounded-md bg-primary px-6 py-2.5 text-sm font-semibold text-primary-foreground shadow-sm hover:opacity-90"
                            >
                                Open dashboard
                            </Link>
                        ) : (
                            <Link
                                href={login()}
                                className="rounded-md bg-primary px-6 py-2.5 text-sm font-semibold text-primary-foreground shadow-sm hover:opacity-90"
                            >
                                Sign in to continue
                            </Link>
                        )}
                    </div>
                </main>

                <footer className="border-t border-border">
                    <div className="mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-4 text-xs text-muted-foreground">
                        <span>{appName}</span>
                        <div className="flex items-center gap-4">
                            <Link
                                href="/portal/access"
                                className="hover:text-foreground"
                            >
                                Exhibitor portal
                            </Link>
                            <Link
                                href={licenses().url}
                                className="hover:text-foreground"
                            >
                                Open source licenses
                            </Link>
                            <a
                                href="https://www.go-palladium.com"
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center gap-1.5 hover:text-foreground"
                            >
                                <span>Powered by</span>
                                <img
                                    src="/branding/avatar_small.png"
                                    alt=""
                                    aria-hidden
                                    className="size-4 rounded-sm"
                                />
                                <span className="font-medium">Palladium</span>
                            </a>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
