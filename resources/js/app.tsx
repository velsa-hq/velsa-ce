import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import { initializePalette } from '@/hooks/use-palette';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import PortalLayout from '@/layouts/portal-layout';
import SettingsLayout from '@/layouts/settings/layout';

// @axe-core/react reads window at import-time and crashes the SSR
// warm-up; gate on typeof window so it only loads in the browser
if (import.meta.env.DEV && typeof window !== 'undefined') {
    void import('react-dom').then(async (ReactDOM) => {
        const React = await import('react');
        const axe = await import('@axe-core/react');
        axe.default(React, ReactDOM, 1000);
    });
}

const appName = import.meta.env.VITE_APP_NAME || 'Velsa';

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
            case name === 'licenses':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('portal/'):
                return PortalLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// light/dark mode on load
initializeTheme();
// accent palette (default: Iris)
initializePalette();
