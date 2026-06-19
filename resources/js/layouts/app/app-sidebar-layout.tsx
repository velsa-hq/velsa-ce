import AppFooter from '@/components/app-footer';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import AppTopBar from '@/components/app-top-bar';
import ReadOnlyBanner from '@/components/read-only-banner';
import SafeModeBanner from '@/components/safe-mode-banner';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return (
        <div className="flex min-h-screen w-full flex-col bg-background">
            <AppTopBar />
            <AppSidebar />
            <main className="flex flex-1 flex-col overflow-x-hidden pt-12 pb-10 pl-[var(--rail-width,13rem)] transition-[padding] duration-200">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                <div className="flex-1">{children}</div>
            </main>
            <AppFooter />
            <SafeModeBanner />
            <ReadOnlyBanner />
        </div>
    );
}
