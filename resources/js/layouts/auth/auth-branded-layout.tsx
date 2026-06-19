import { Link, usePage } from '@inertiajs/react';
import { Card, CardContent } from '@/components/ui/card';
import { useRandomBackground } from '@/lib/random-background';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

type Branding = {
    app_name: string | null;
    app_title: string | null;
    app_subtitle: string | null;
    app_tagline: string | null;
    logo_path: string | null;
    logo_alt: string | null;
};

export default function AuthBrandedLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const background = useRandomBackground();
    const { branding } = usePage().props as unknown as { branding: Branding };

    const logoSrc = branding?.logo_path ?? '/favicon.svg';
    const logoAlt = branding?.logo_alt ?? 'Organization seal';
    const heroTitle = branding?.app_title ?? 'Your organization';
    const heroSubtitle = branding?.app_subtitle ?? 'Velsa';
    const heroTagline = branding?.app_tagline ?? '';

    return (
        <div className="grid min-h-svh lg:grid-cols-2">
            <div className="order-2 flex items-center justify-center p-6 lg:order-1 lg:p-10">
                <Card className="w-full max-w-[400px]">
                    <CardContent className="p-6">
                        <div className="flex flex-col gap-6">
                            {(title || description) && (
                                <div className="flex flex-col gap-2 text-center">
                                    {title && (
                                        <h1 className="text-xl font-semibold tracking-tight">
                                            {title}
                                        </h1>
                                    )}
                                    {description && (
                                        <p className="text-sm text-muted-foreground">
                                            {description}
                                        </p>
                                    )}
                                </div>
                            )}
                            {children}
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div
                className="relative order-1 overflow-hidden bg-primary text-primary-foreground lg:order-2 lg:m-5 lg:rounded-xl"
                style={
                    background
                        ? {
                              backgroundImage: `url('${background}')`,
                              backgroundSize: 'cover',
                              backgroundPosition: 'center',
                          }
                        : undefined
                }
            >
                <div className="absolute inset-0 bg-primary/70 mix-blend-multiply" />
                <div className="absolute inset-0 bg-gradient-to-b from-black/10 via-transparent to-black/40" />

                <div className="relative flex h-full flex-col items-center justify-center gap-8 p-10 text-center lg:p-16">
                    <Link
                        href={home()}
                        className="flex items-center justify-center rounded-full bg-white/95 p-3 shadow-lg ring-1 ring-black/5"
                    >
                        <img
                            src={logoSrc}
                            alt={logoAlt}
                            className="size-24 lg:size-32"
                        />
                    </Link>

                    <div className="flex flex-col gap-3 drop-shadow-md">
                        <h2 className="text-2xl font-semibold lg:text-3xl">
                            {heroTitle}
                        </h2>
                        <p className="text-base font-medium opacity-95 lg:text-lg">
                            {heroSubtitle}
                        </p>
                    </div>

                    {heroTagline && (
                        <p className="max-w-md text-sm leading-relaxed opacity-90 drop-shadow lg:text-base">
                            {heroTagline}
                        </p>
                    )}
                </div>

                <a
                    href="https://www.go-palladium.com"
                    target="_blank"
                    rel="noopener noreferrer"
                    className="absolute right-4 bottom-4 inline-flex items-center gap-1.5 text-xs opacity-80 hover:opacity-100"
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
    );
}
