import { Link, router, usePage } from '@inertiajs/react';
import {
    BookOpen,
    LogOut,
    ShieldCheck,
    ShoppingBag,
    Stamp,
    Store,
} from 'lucide-react';
import type { ReactNode } from 'react';
import ReadOnlyBanner from '@/components/read-only-banner';
import SafeModeBanner from '@/components/safe-mode-banner';
import { Button } from '@/components/ui/button';

type SharedAuth = {
    auth: {
        exhibitor: {
            id: number;
            company_name: string;
            contact_name: string;
            email: string;
        } | null;
    };
};

type Props = { children: ReactNode };

export default function PortalLayout({ children }: Props) {
    const { auth } = usePage<SharedAuth>().props;
    const exhibitor = auth.exhibitor;

    const handleLogout = () => {
        router.post('/portal/logout');
    };

    return (
        <div className="flex min-h-screen flex-col bg-muted/30">
            <header className="border-b border-border bg-card">
                <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3">
                    <div className="flex items-center gap-3">
                        <Store className="size-6 text-primary" />
                        <Link
                            href="/portal"
                            className="text-lg font-semibold tracking-tight"
                        >
                            Exhibitor Portal
                        </Link>
                    </div>

                    {exhibitor ? (
                        <nav className="flex items-center gap-1 sm:gap-3">
                            <Link
                                href="/portal"
                                className="px-3 py-1.5 text-sm hover:text-primary"
                            >
                                Dashboard
                            </Link>
                            <Link
                                href="/portal/catalog"
                                className="flex items-center gap-1.5 px-3 py-1.5 text-sm hover:text-primary"
                            >
                                <ShoppingBag className="size-4" />
                                <span>Catalog</span>
                            </Link>
                            <Link
                                href="/portal/insurance"
                                className="flex items-center gap-1.5 px-3 py-1.5 text-sm hover:text-primary"
                            >
                                <ShieldCheck className="size-4" />
                                <span>Insurance</span>
                            </Link>
                            <Link
                                href="/portal/handbook"
                                className="flex items-center gap-1.5 px-3 py-1.5 text-sm hover:text-primary"
                            >
                                <BookOpen className="size-4" />
                                <span>Handbook</span>
                            </Link>
                            <Link
                                href="/portal/permits"
                                className="flex items-center gap-1.5 px-3 py-1.5 text-sm hover:text-primary"
                            >
                                <Stamp className="size-4" />
                                <span>Permits</span>
                            </Link>

                            <div className="hidden flex-col items-end pl-2 text-right sm:flex">
                                <span className="text-sm font-medium">
                                    {exhibitor.company_name}
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    {exhibitor.contact_name}
                                </span>
                            </div>

                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={handleLogout}
                                aria-label="Sign out"
                            >
                                <LogOut className="size-4" />
                            </Button>
                        </nav>
                    ) : (
                        <span className="text-sm text-muted-foreground">
                            Not signed in
                        </span>
                    )}
                </div>
            </header>

            <main className="mx-auto w-full max-w-6xl flex-1 p-4">
                {children}
            </main>

            <footer className="border-t border-border bg-card">
                <div className="mx-auto max-w-6xl px-4 py-3 text-xs text-muted-foreground">
                    Velsa · Exhibitor Portal
                </div>
            </footer>
            <SafeModeBanner />
            <ReadOnlyBanner />
        </div>
    );
}
