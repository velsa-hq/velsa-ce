import { Link, usePage } from '@inertiajs/react';
import { AppSearch } from '@/components/app-search';
import { TopBarUserMenu } from '@/components/top-bar-user-menu';
import { dashboard } from '@/routes';

type Branding = {
    app_title: string | null;
    app_subtitle: string | null;
    logo_path: string | null;
    logo_alt: string | null;
};

export default function AppTopBar() {
    const { branding } = usePage().props as unknown as { branding: Branding };

    const logoSrc = branding?.logo_path ?? '/favicon.svg';
    const logoAlt = branding?.logo_alt ?? '';
    const title = branding?.app_title ?? '';
    const subtitle = branding?.app_subtitle ?? '';

    return (
        <header className="fixed inset-x-0 top-0 z-40 grid h-12 grid-cols-[1fr_minmax(0,48rem)_1fr] items-center gap-4 border-b border-primary/30 bg-primary px-4 text-primary-foreground shadow-sm">
            <Link
                href={dashboard().url}
                prefetch
                className="flex min-w-0 items-center gap-2 truncate transition-opacity hover:opacity-90"
            >
                <img
                    src={logoSrc}
                    alt={logoAlt}
                    aria-hidden={!logoAlt}
                    className="size-7 shrink-0 rounded"
                />
                <div className="hidden min-w-0 flex-col leading-tight sm:flex">
                    {title && (
                        <span className="truncate text-sm font-semibold">
                            {title}
                        </span>
                    )}
                    {subtitle && (
                        <span className="truncate text-[10px] opacity-80">
                            {subtitle}
                        </span>
                    )}
                </div>
            </Link>
            <div className="w-full min-w-0">
                <AppSearch />
            </div>
            <div className="flex justify-end">
                <TopBarUserMenu />
            </div>
        </header>
    );
}
