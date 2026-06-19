import { Link, usePage } from '@inertiajs/react';

type Branding = {
    app_name: string | null;
};

export default function AppFooter() {
    const { branding, version } = usePage().props as unknown as {
        branding: Branding;
        version: string | null;
    };
    const appName = branding?.app_name ?? 'Velsa';
    const year = new Date().getFullYear();
    // Build-stamped release (e.g. "1.0.8"); unset in local dev.
    const versionLabel = version ? `v${version}` : 'dev';

    return (
        <footer className="fixed inset-x-0 bottom-0 z-30 border-t border-border bg-neutral-200 px-6 py-2 text-xs text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="flex flex-wrap items-center gap-2">
                    <span>
                        © {year} {appName}. All rights reserved.
                    </span>
                    <span aria-hidden>·</span>
                    <Link
                        href="/docs/license"
                        className="hover:text-foreground hover:underline"
                    >
                        License
                    </Link>
                    <span aria-hidden>·</span>
                    <span
                        className="font-mono text-[11px] text-muted-foreground"
                        title="App version"
                    >
                        {versionLabel}
                    </span>
                </div>
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
                        className="size-5 rounded-sm"
                    />
                    <span className="font-medium">Palladium</span>
                </a>
            </div>
        </footer>
    );
}
