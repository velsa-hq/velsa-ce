import { Head } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';
import { PalettePicker } from '@/components/palette-picker';
import { edit as editAppearance } from '@/routes/appearance';

export default function Appearance() {
    return (
        <>
            <Head title="Appearance settings" />

            <h1 className="sr-only">Appearance settings</h1>

            <div className="space-y-8">
                <Heading
                    variant="small"
                    title="Appearance settings"
                    description="Update the appearance settings for your account"
                />

                <section className="flex flex-col gap-2">
                    <h2 className="text-sm font-medium">Mode</h2>
                    <p className="text-xs text-muted-foreground">
                        Choose light, dark, or follow your system preference.
                    </p>
                    <AppearanceTabs />
                </section>

                <section className="flex flex-col gap-2">
                    <h2 className="text-sm font-medium">Accent palette</h2>
                    <p className="text-xs text-muted-foreground">
                        Picks the brand colour used for buttons, focus rings,
                        charts, and active-state highlights. Dark-mode variants
                        flip automatically. Iris is the default.
                    </p>
                    <PalettePicker />
                </section>
            </div>
        </>
    );
}

Appearance.layout = {
    breadcrumbs: [
        {
            title: 'Appearance settings',
            href: editAppearance(),
        },
    ],
};
